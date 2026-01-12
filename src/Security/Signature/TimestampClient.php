<?php

declare(strict_types=1);

namespace PdfLib\Security\Signature;

/**
 * RFC 3161 Time-Stamp Protocol client.
 *
 * Requests timestamp tokens from Time Stamping Authorities (TSA).
 *
 * @example
 * ```php
 * $tsa = new TimestampClient('http://timestamp.digicert.com');
 * $token = $tsa->getTimestamp($signatureData);
 * ```
 */
final class TimestampClient
{
    // OIDs for hash algorithms
    private const OID_SHA256 = '2.16.840.1.101.3.4.2.1';
    private const OID_SHA384 = '2.16.840.1.101.3.4.2.2';
    private const OID_SHA512 = '2.16.840.1.101.3.4.2.3';
    private const OID_SHA1 = '1.3.14.3.2.26';

    // TSA status codes
    public const STATUS_GRANTED = 0;
    public const STATUS_GRANTED_WITH_MODS = 1;
    public const STATUS_REJECTION = 2;
    public const STATUS_WAITING = 3;
    public const STATUS_REVOCATION_WARNING = 4;
    public const STATUS_REVOCATION_NOTIFICATION = 5;

    private string $tsaUrl;
    private string $hashAlgorithm = 'sha256';
    private ?string $username = null;
    private ?string $password = null;
    private int $timeout = 30;
    private bool $requestCertificate = true;

    /** @var array<string, string> Custom HTTP headers */
    private array $headers = [];

    public function __construct(string $tsaUrl)
    {
        $this->tsaUrl = $tsaUrl;
    }

    /**
     * Create a new TSA client.
     */
    public static function create(string $tsaUrl): self
    {
        return new self($tsaUrl);
    }

    /**
     * Set hash algorithm.
     */
    public function setHashAlgorithm(string $algorithm): self
    {
        $algorithm = strtolower($algorithm);
        if (!in_array($algorithm, ['sha256', 'sha384', 'sha512', 'sha1'], true)) {
            throw new \InvalidArgumentException("Unsupported hash algorithm: $algorithm");
        }
        $this->hashAlgorithm = $algorithm;
        return $this;
    }

    /**
     * Set authentication credentials.
     */
    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Set request timeout.
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /**
     * Set whether to request TSA certificate in response.
     */
    public function setRequestCertificate(bool $request): self
    {
        $this->requestCertificate = $request;
        return $this;
    }

    /**
     * Add custom HTTP header.
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get timestamp token for data.
     *
     * @param string $data The data to timestamp (usually signature value)
     * @return string DER-encoded timestamp token
     * @throws \RuntimeException on failure
     */
    public function getTimestamp(string $data): string
    {
        // Hash the data
        $hash = hash($this->hashAlgorithm, $data, true);

        // Build timestamp request
        $request = $this->buildTimeStampReq($hash);

        // Send request to TSA
        $response = $this->sendRequest($request);

        // Parse response
        return $this->parseTimeStampResp($response);
    }

    /**
     * Get timestamp token for pre-computed hash.
     *
     * @param string $hash Binary hash value
     * @return string DER-encoded timestamp token
     */
    public function getTimestampForHash(string $hash): string
    {
        $request = $this->buildTimeStampReq($hash);
        $response = $this->sendRequest($request);
        return $this->parseTimeStampResp($response);
    }

    /**
     * Build RFC 3161 TimeStampReq structure.
     */
    private function buildTimeStampReq(string $hash): string
    {
        // Get OID for hash algorithm
        $hashOid = $this->getHashOid();

        // Build MessageImprint
        $messageImprint = $this->buildMessageImprint($hashOid, $hash);

        // Generate nonce (random for replay protection)
        $nonce = $this->encodeInteger($this->generateNonce());

        // Build TimeStampReq
        // version INTEGER (1)
        $version = $this->encodeInteger(1);

        // certReq BOOLEAN
        $certReq = $this->requestCertificate ? "\x01\x01\xFF" : "\x01\x01\x00";

        // Combine: version + messageImprint + nonce + certReq
        $content = $version . $messageImprint . $nonce . $certReq;

        // Wrap in SEQUENCE
        return $this->wrapInSequence($content);
    }

    /**
     * Build MessageImprint structure.
     */
    private function buildMessageImprint(string $hashOid, string $hash): string
    {
        // AlgorithmIdentifier SEQUENCE
        $oidEncoded = $this->encodeOid($hashOid);
        // NULL parameters for hash algorithms
        $algParams = "\x05\x00";
        $algId = $this->wrapInSequence($oidEncoded . $algParams);

        // hashedMessage OCTET STRING
        $hashedMessage = $this->encodeOctetString($hash);

        // MessageImprint SEQUENCE
        return $this->wrapInSequence($algId . $hashedMessage);
    }

    /**
     * Send request to TSA server.
     */
    private function sendRequest(string $request): string
    {
        $ch = curl_init($this->tsaUrl);

        if ($ch === false) {
            throw new \RuntimeException('Could not initialize cURL');
        }

        $headers = [
            'Content-Type: application/timestamp-query',
            'Accept: application/timestamp-reply',
        ];

        foreach ($this->headers as $name => $value) {
            $headers[] = "$name: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($this->username !== null && $this->password !== null) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("TSA request failed: $error");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("TSA returned HTTP $httpCode");
        }

        return $response;
    }

    /**
     * Parse TimeStampResp and extract token.
     */
    private function parseTimeStampResp(string $response): string
    {
        // TimeStampResp ::= SEQUENCE {
        //   status          PKIStatusInfo,
        //   timeStampToken  ContentInfo OPTIONAL
        // }

        $offset = 0;

        // Parse outer SEQUENCE
        $seq = $this->parseAsn1($response, $offset);
        if ($seq['tag'] !== 0x30) {
            throw new \RuntimeException('Invalid timestamp response: not a SEQUENCE');
        }

        $content = $seq['value'];
        $innerOffset = 0;

        // Parse PKIStatusInfo
        $statusInfo = $this->parseAsn1($content, $innerOffset);
        if ($statusInfo['tag'] !== 0x30) {
            throw new \RuntimeException('Invalid PKIStatusInfo');
        }

        // Parse status INTEGER from PKIStatusInfo
        $statusContent = $statusInfo['value'];
        $statusOffset = 0;
        $status = $this->parseAsn1($statusContent, $statusOffset);

        if ($status['tag'] !== 0x02) {
            throw new \RuntimeException('Invalid status in PKIStatusInfo');
        }

        $statusValue = $this->decodeInteger($status['value']);

        if ($statusValue !== self::STATUS_GRANTED && $statusValue !== self::STATUS_GRANTED_WITH_MODS) {
            // Try to extract error message
            $errorMsg = "TSA request rejected with status $statusValue";
            throw new \RuntimeException($errorMsg);
        }

        // Parse timeStampToken (ContentInfo)
        if ($innerOffset >= strlen($content)) {
            throw new \RuntimeException('No timestamp token in response');
        }

        $tokenStart = $innerOffset;
        $token = $this->parseAsn1($content, $innerOffset);

        // Return the raw DER-encoded ContentInfo
        return substr($content, $tokenStart, $token['length'] + ($innerOffset - $tokenStart - $token['length']));
    }

    /**
     * Get OID for current hash algorithm.
     */
    private function getHashOid(): string
    {
        return match ($this->hashAlgorithm) {
            'sha256' => self::OID_SHA256,
            'sha384' => self::OID_SHA384,
            'sha512' => self::OID_SHA512,
            'sha1' => self::OID_SHA1,
            default => throw new \RuntimeException("Unknown hash algorithm: {$this->hashAlgorithm}"),
        };
    }

    /**
     * Generate random nonce for replay protection.
     */
    private function generateNonce(): int
    {
        return random_int(1, PHP_INT_MAX);
    }

    // ASN.1 DER encoding helpers

    /**
     * Encode OID.
     */
    private function encodeOid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));

        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid OID');
        }

        // First two components encoded specially
        $bytes = chr($parts[0] * 40 + $parts[1]);

        // Remaining components
        for ($i = 2; $i < count($parts); $i++) {
            $bytes .= $this->encodeOidComponent($parts[$i]);
        }

        return "\x06" . chr(strlen($bytes)) . $bytes;
    }

    /**
     * Encode single OID component.
     */
    private function encodeOidComponent(int $value): string
    {
        if ($value < 128) {
            return chr($value);
        }

        $bytes = '';
        $temp = $value;
        $first = true;

        while ($temp > 0) {
            $byte = $temp & 0x7F;
            if (!$first) {
                $byte |= 0x80;
            }
            $bytes = chr($byte) . $bytes;
            $temp >>= 7;
            $first = false;
        }

        return $bytes;
    }

    /**
     * Encode INTEGER.
     */
    private function encodeInteger(int $value): string
    {
        if ($value === 0) {
            return "\x02\x01\x00";
        }

        $bytes = '';
        $temp = $value;
        $negative = $temp < 0;

        while ($temp !== 0 && $temp !== -1) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        // Add sign byte if needed
        if (!$negative && (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        } elseif ($negative && !(ord($bytes[0]) & 0x80)) {
            $bytes = "\xFF" . $bytes;
        }

        return "\x02" . chr(strlen($bytes)) . $bytes;
    }

    /**
     * Decode INTEGER.
     */
    private function decodeInteger(string $bytes): int
    {
        $value = 0;
        $negative = (ord($bytes[0]) & 0x80) !== 0;

        for ($i = 0; $i < strlen($bytes); $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        if ($negative) {
            $value -= (1 << (strlen($bytes) * 8));
        }

        return $value;
    }

    /**
     * Encode OCTET STRING.
     */
    private function encodeOctetString(string $data): string
    {
        return "\x04" . $this->encodeLength(strlen($data)) . $data;
    }

    /**
     * Wrap content in SEQUENCE.
     */
    private function wrapInSequence(string $content): string
    {
        return "\x30" . $this->encodeLength(strlen($content)) . $content;
    }

    /**
     * Encode ASN.1 length.
     */
    private function encodeLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        $temp = $length;

        while ($temp > 0) {
            $bytes = chr($temp & 0xFF) . $bytes;
            $temp >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    /**
     * Parse ASN.1 element.
     *
     * @return array{tag: int, length: int, value: string}
     */
    private function parseAsn1(string $data, int &$offset): array
    {
        if ($offset >= strlen($data)) {
            throw new \RuntimeException('Unexpected end of ASN.1 data');
        }

        $tag = ord($data[$offset++]);

        // Parse length
        $lengthByte = ord($data[$offset++]);
        if ($lengthByte < 128) {
            $length = $lengthByte;
        } else {
            $numBytes = $lengthByte & 0x7F;
            $length = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $length = ($length << 8) | ord($data[$offset++]);
            }
        }

        $value = substr($data, $offset, $length);
        $offset += $length;

        return [
            'tag' => $tag,
            'length' => $length,
            'value' => $value,
        ];
    }

    /**
     * Get well-known TSA URLs.
     *
     * @return array<string, string>
     */
    public static function getWellKnownTsaUrls(): array
    {
        return [
            'DigiCert' => 'http://timestamp.digicert.com',
            'Sectigo' => 'http://timestamp.sectigo.com',
            'GlobalSign' => 'http://timestamp.globalsign.com/tsa/r6advanced1',
            'FreeTSA' => 'https://freetsa.org/tsr',
            'Comodo' => 'http://timestamp.comodoca.com',
        ];
    }
}
