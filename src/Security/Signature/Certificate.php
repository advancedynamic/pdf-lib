<?php

declare(strict_types=1);

namespace PdfLib\Security\Signature;

/**
 * X.509 Certificate handling for PDF digital signatures.
 *
 * @example
 * ```php
 * $cert = Certificate::fromFile('certificate.pem');
 * echo $cert->getSubjectName();
 * echo $cert->getIssuerName();
 *
 * // Check validity
 * if ($cert->isValid()) {
 *     echo "Certificate is valid";
 * }
 * ```
 */
final class Certificate
{
    private string $pemContent;
    private string $derContent;

    /** @var array<string, mixed> */
    private array $parsed = [];

    private string $subjectName = '';
    private string $issuerName = '';
    private string $serialNumber = '';
    private ?\DateTimeImmutable $validFrom = null;
    private ?\DateTimeImmutable $validTo = null;

    /** @var array<int, string> */
    private array $keyUsage = [];

    private ?string $ocspUrl = null;

    /** @var array<int, string> */
    private array $crlUrls = [];

    private function __construct(string $pemContent)
    {
        $this->pemContent = $pemContent;
        $this->derContent = $this->pemToDer($pemContent);
        $this->parse();
    }

    /**
     * Create from PEM-encoded certificate string.
     */
    public static function fromPem(string $pem): self
    {
        if (!str_contains($pem, '-----BEGIN CERTIFICATE-----')) {
            throw new \InvalidArgumentException('Invalid PEM certificate format');
        }
        return new self($pem);
    }

    /**
     * Create from DER-encoded certificate bytes.
     */
    public static function fromDer(string $der): self
    {
        $pem = "-----BEGIN CERTIFICATE-----\n";
        $pem .= chunk_split(base64_encode($der), 64, "\n");
        $pem .= "-----END CERTIFICATE-----\n";
        return new self($pem);
    }

    /**
     * Create from file path.
     */
    public static function fromFile(string $filePath): self
    {
        $path = $filePath;
        if (str_starts_with($filePath, 'file://')) {
            $path = substr($filePath, 7);
        }

        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Certificate file not found: $path");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read certificate file: $path");
        }

        // Detect format
        if (str_contains($content, '-----BEGIN CERTIFICATE-----')) {
            return self::fromPem($content);
        }

        // Assume DER format
        return self::fromDer($content);
    }

    /**
     * Get PEM-encoded certificate.
     */
    public function getPem(): string
    {
        return $this->pemContent;
    }

    /**
     * Get DER-encoded certificate.
     */
    public function getDer(): string
    {
        return $this->derContent;
    }

    /**
     * Get subject name (CN or full DN).
     */
    public function getSubjectName(): string
    {
        return $this->subjectName;
    }

    /**
     * Get issuer name (CN or full DN).
     */
    public function getIssuerName(): string
    {
        return $this->issuerName;
    }

    /**
     * Get serial number as hex string.
     */
    public function getSerialNumber(): string
    {
        return $this->serialNumber;
    }

    /**
     * Get serial number as bytes (DER integer).
     */
    public function getSerialNumberBytes(): string
    {
        $hex = $this->serialNumber;
        // Ensure even length for hex2bin
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }
        return hex2bin($hex) ?: '';
    }

    /**
     * Get validity start date.
     */
    public function getValidFrom(): ?\DateTimeImmutable
    {
        return $this->validFrom;
    }

    /**
     * Get validity end date.
     */
    public function getValidTo(): ?\DateTimeImmutable
    {
        return $this->validTo;
    }

    /**
     * Check if certificate is currently valid.
     */
    public function isValid(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? new \DateTimeImmutable();

        if ($this->validFrom === null || $this->validTo === null) {
            return false;
        }

        return $at >= $this->validFrom && $at <= $this->validTo;
    }

    /**
     * Check if certificate is expired.
     */
    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        $at = $at ?? new \DateTimeImmutable();

        if ($this->validTo === null) {
            return true;
        }

        return $at > $this->validTo;
    }

    /**
     * Check if certificate is self-signed.
     */
    public function isSelfSigned(): bool
    {
        return $this->subjectName === $this->issuerName;
    }

    /**
     * Get key usage extensions.
     *
     * @return array<int, string>
     */
    public function getKeyUsage(): array
    {
        return $this->keyUsage;
    }

    /**
     * Check if certificate can be used for digital signatures.
     */
    public function canSign(): bool
    {
        if (empty($this->keyUsage)) {
            return true; // No restrictions
        }

        return in_array('digitalSignature', $this->keyUsage, true)
            || in_array('nonRepudiation', $this->keyUsage, true);
    }

    /**
     * Get OCSP responder URL.
     */
    public function getOcspUrl(): ?string
    {
        return $this->ocspUrl;
    }

    /**
     * Get CRL distribution point URLs.
     *
     * @return array<int, string>
     */
    public function getCrlUrls(): array
    {
        return $this->crlUrls;
    }

    /**
     * Get parsed certificate data.
     *
     * @return array<string, mixed>
     */
    public function getParsedData(): array
    {
        return $this->parsed;
    }

    /**
     * Get issuer hash (for OCSP requests).
     */
    public function getIssuerNameHash(string $algorithm = 'sha1'): string
    {
        // Hash the DER-encoded issuer DN
        $issuerDn = $this->parsed['issuer'] ?? [];
        $issuerString = $this->dnToString($issuerDn);
        return hash($algorithm, $issuerString, true);
    }

    /**
     * Convert PEM to DER.
     */
    private function pemToDer(string $pem): string
    {
        $pem = preg_replace('/-----BEGIN .*?-----/', '', $pem);
        $pem = preg_replace('/-----END .*?-----/', '', $pem);
        $pem = preg_replace('/\s+/', '', $pem);

        $der = base64_decode($pem, true);
        if ($der === false) {
            throw new \InvalidArgumentException('Invalid certificate encoding');
        }

        return $der;
    }

    /**
     * Parse certificate using OpenSSL.
     */
    private function parse(): void
    {
        $cert = openssl_x509_read($this->pemContent);
        if ($cert === false) {
            throw new \InvalidArgumentException('Could not parse certificate');
        }

        $parsed = openssl_x509_parse($cert);
        if ($parsed === false) {
            throw new \InvalidArgumentException('Could not parse certificate data');
        }

        $this->parsed = $parsed;

        // Extract subject name
        $subject = $parsed['subject'] ?? [];
        $this->subjectName = $this->extractCommonName($subject);

        // Extract issuer name
        $issuer = $parsed['issuer'] ?? [];
        $this->issuerName = $this->extractCommonName($issuer);

        // Extract serial number
        $this->serialNumber = $parsed['serialNumberHex'] ?? '';

        // Extract validity dates
        if (isset($parsed['validFrom_time_t'])) {
            $this->validFrom = (new \DateTimeImmutable())->setTimestamp($parsed['validFrom_time_t']);
        }
        if (isset($parsed['validTo_time_t'])) {
            $this->validTo = (new \DateTimeImmutable())->setTimestamp($parsed['validTo_time_t']);
        }

        // Extract key usage
        $this->parseKeyUsage($parsed);

        // Extract OCSP URL
        $this->parseOcspUrl($parsed);

        // Extract CRL URLs
        $this->parseCrlUrls($parsed);
    }

    /**
     * Extract common name from subject/issuer array.
     */
    private function extractCommonName(array $dn): string
    {
        if (isset($dn['CN'])) {
            return is_array($dn['CN']) ? $dn['CN'][0] : $dn['CN'];
        }

        // Build DN string
        return $this->dnToString($dn);
    }

    /**
     * Convert DN array to string.
     */
    private function dnToString(array $dn): string
    {
        $parts = [];
        foreach ($dn as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $parts[] = "$key=$v";
                }
            } else {
                $parts[] = "$key=$value";
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Parse key usage from certificate extensions.
     */
    private function parseKeyUsage(array $parsed): void
    {
        $extensions = $parsed['extensions'] ?? [];

        if (isset($extensions['keyUsage'])) {
            $usage = $extensions['keyUsage'];
            $this->keyUsage = array_map('trim', explode(',', $usage));
        }
    }

    /**
     * Parse OCSP URL from Authority Information Access.
     */
    private function parseOcspUrl(array $parsed): void
    {
        $extensions = $parsed['extensions'] ?? [];

        if (isset($extensions['authorityInfoAccess'])) {
            $aia = $extensions['authorityInfoAccess'];
            // Look for OCSP URL
            if (preg_match('/OCSP\s*-\s*URI:(\S+)/i', $aia, $matches)) {
                $this->ocspUrl = $matches[1];
            }
        }
    }

    /**
     * Parse CRL distribution points.
     */
    private function parseCrlUrls(array $parsed): void
    {
        $extensions = $parsed['extensions'] ?? [];

        if (isset($extensions['crlDistributionPoints'])) {
            $cdp = $extensions['crlDistributionPoints'];
            // Extract URLs
            if (preg_match_all('/URI:(\S+)/i', $cdp, $matches)) {
                $this->crlUrls = $matches[1];
            }
        }
    }

    /**
     * Verify this certificate was issued by the given issuer certificate.
     */
    public function isIssuedBy(Certificate $issuer): bool
    {
        $cert = openssl_x509_read($this->pemContent);
        $issuerCert = openssl_x509_read($issuer->getPem());

        if ($cert === false || $issuerCert === false) {
            return false;
        }

        $issuerKey = openssl_pkey_get_public($issuerCert);
        if ($issuerKey === false) {
            return false;
        }

        // Verify signature
        return openssl_x509_verify($cert, $issuerKey) === 1;
    }

    /**
     * Get certificate fingerprint.
     */
    public function getFingerprint(string $algorithm = 'sha256'): string
    {
        return hash($algorithm, $this->derContent);
    }
}
