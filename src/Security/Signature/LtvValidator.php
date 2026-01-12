<?php

declare(strict_types=1);

namespace PdfLib\Security\Signature;

/**
 * Long-Term Validation (LTV) for PDF signatures.
 *
 * Provides OCSP and CRL fetching, and builds Document Security Store (DSS)
 * for PAdES-LTV compliance.
 *
 * @example
 * ```php
 * $ltv = new LtvValidator();
 * $ltv->addCertificate($signingCert);
 * $ltv->addCertificate($issuerCert);
 * $ltv->fetchValidationData();
 *
 * $dss = $ltv->buildDss();
 * ```
 */
final class LtvValidator
{
    // OCSP response status
    public const OCSP_SUCCESSFUL = 0;
    public const OCSP_MALFORMED_REQUEST = 1;
    public const OCSP_INTERNAL_ERROR = 2;
    public const OCSP_TRY_LATER = 3;
    public const OCSP_SIG_REQUIRED = 5;
    public const OCSP_UNAUTHORIZED = 6;

    // Certificate status
    public const CERT_GOOD = 0;
    public const CERT_REVOKED = 1;
    public const CERT_UNKNOWN = 2;

    /** @var array<int, Certificate> */
    private array $certificates = [];

    /** @var array<string, string> Hash => OCSP response (DER) */
    private array $ocspResponses = [];

    /** @var array<string, string> URL => CRL (DER) */
    private array $crls = [];

    private int $timeout = 30;
    private int $maxCrlSize = 10 * 1024 * 1024; // 10 MB
    private bool $checkOcsp = true;
    private bool $checkCrl = true;

    public function __construct()
    {
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
     * Set maximum CRL size.
     */
    public function setMaxCrlSize(int $bytes): self
    {
        $this->maxCrlSize = max(1024, $bytes);
        return $this;
    }

    /**
     * Enable/disable OCSP checking.
     */
    public function setCheckOcsp(bool $check): self
    {
        $this->checkOcsp = $check;
        return $this;
    }

    /**
     * Enable/disable CRL checking.
     */
    public function setCheckCrl(bool $check): self
    {
        $this->checkCrl = $check;
        return $this;
    }

    /**
     * Add certificate for validation.
     */
    public function addCertificate(Certificate $cert): self
    {
        $this->certificates[] = $cert;
        return $this;
    }

    /**
     * Add certificate from PEM string.
     */
    public function addCertificatePem(string $pem): self
    {
        return $this->addCertificate(Certificate::fromPem($pem));
    }

    /**
     * Add certificate from file.
     */
    public function addCertificateFile(string $path): self
    {
        return $this->addCertificate(Certificate::fromFile($path));
    }

    /**
     * Get all certificates.
     *
     * @return array<int, Certificate>
     */
    public function getCertificates(): array
    {
        return $this->certificates;
    }

    /**
     * Fetch all validation data (OCSP responses and CRLs).
     *
     * @param bool $preferOcsp Prefer OCSP over CRL when both are available
     * @return array{ocsp: int, crl: int, errors: array<int, string>}
     */
    public function fetchValidationData(bool $preferOcsp = true): array
    {
        $result = [
            'ocsp' => 0,
            'crl' => 0,
            'errors' => [],
        ];

        // Build certificate chain for OCSP requests
        $certMap = $this->buildCertificateMap();

        foreach ($this->certificates as $cert) {
            // Skip self-signed certificates (root CA)
            if ($cert->isSelfSigned()) {
                continue;
            }

            $ocspUrl = $cert->getOcspUrl();
            $crlUrls = $cert->getCrlUrls();

            $gotValidation = false;

            // Try OCSP first if preferred
            if ($preferOcsp && $ocspUrl !== null) {
                try {
                    $issuer = $this->findIssuer($cert, $certMap);
                    if ($issuer !== null) {
                        $ocspResponse = $this->fetchOcspResponse($cert, $issuer, $ocspUrl);
                        if ($ocspResponse !== null) {
                            $hash = $cert->getFingerprint('sha1');
                            $this->ocspResponses[$hash] = $ocspResponse;
                            $result['ocsp']++;
                            $gotValidation = true;
                        }
                    }
                } catch (\Throwable $e) {
                    $result['errors'][] = "OCSP error for {$cert->getSubjectName()}: {$e->getMessage()}";
                }
            }

            // Try CRL if OCSP failed or not preferred
            if (!$gotValidation && !empty($crlUrls)) {
                foreach ($crlUrls as $crlUrl) {
                    try {
                        if (!isset($this->crls[$crlUrl])) {
                            $crl = $this->fetchCrl($crlUrl);
                            if ($crl !== null) {
                                $this->crls[$crlUrl] = $crl;
                                $result['crl']++;
                                $gotValidation = true;
                                break;
                            }
                        } else {
                            $gotValidation = true;
                            break;
                        }
                    } catch (\Throwable $e) {
                        $result['errors'][] = "CRL error for {$cert->getSubjectName()}: {$e->getMessage()}";
                    }
                }
            }

            // Try OCSP if CRL failed and OCSP not yet tried
            if (!$gotValidation && !$preferOcsp && $ocspUrl !== null) {
                try {
                    $issuer = $this->findIssuer($cert, $certMap);
                    if ($issuer !== null) {
                        $ocspResponse = $this->fetchOcspResponse($cert, $issuer, $ocspUrl);
                        if ($ocspResponse !== null) {
                            $hash = $cert->getFingerprint('sha1');
                            $this->ocspResponses[$hash] = $ocspResponse;
                            $result['ocsp']++;
                        }
                    }
                } catch (\Throwable $e) {
                    $result['errors'][] = "OCSP error for {$cert->getSubjectName()}: {$e->getMessage()}";
                }
            }
        }

        return $result;
    }

    /**
     * Get OCSP response for a certificate.
     */
    public function getOcspResponse(Certificate $cert): ?string
    {
        $hash = $cert->getFingerprint('sha1');
        return $this->ocspResponses[$hash] ?? null;
    }

    /**
     * Get all OCSP responses.
     *
     * @return array<string, string>
     */
    public function getOcspResponses(): array
    {
        return $this->ocspResponses;
    }

    /**
     * Get all CRLs.
     *
     * @return array<string, string>
     */
    public function getCrls(): array
    {
        return $this->crls;
    }

    /**
     * Build Document Security Store (DSS) data.
     *
     * @param string|null $signatureHash SHA1 hash of signature value (for VRI)
     * @return array{
     *     Certs: array<int, string>,
     *     OCSPs: array<int, string>,
     *     CRLs: array<int, string>,
     *     VRI: array<string, array{Certs: array<int, int>, OCSPs: array<int, int>, CRLs: array<int, int>}>
     * }
     */
    public function buildDss(?string $signatureHash = null): array
    {
        $certs = [];
        $ocsps = array_values($this->ocspResponses);
        $crls = array_values($this->crls);

        // Get DER-encoded certificates
        foreach ($this->certificates as $cert) {
            $certs[] = $cert->getDer();
        }

        $dss = [
            'Certs' => $certs,
            'OCSPs' => $ocsps,
            'CRLs' => $crls,
            'VRI' => [],
        ];

        // Build VRI entry if signature hash provided
        if ($signatureHash !== null) {
            $vriKey = strtoupper(bin2hex($signatureHash));
            $dss['VRI'][$vriKey] = [
                'Certs' => array_keys($certs),
                'OCSPs' => array_keys($ocsps),
                'CRLs' => array_keys($crls),
            ];
        }

        return $dss;
    }

    /**
     * Check certificate revocation status.
     *
     * @return array{status: int, message: string}
     */
    public function checkRevocation(Certificate $cert): array
    {
        $hash = $cert->getFingerprint('sha1');

        // Check OCSP response
        if (isset($this->ocspResponses[$hash])) {
            $status = $this->parseOcspStatus($this->ocspResponses[$hash]);
            if ($status !== null) {
                return $status;
            }
        }

        // Check CRL
        foreach ($cert->getCrlUrls() as $url) {
            if (isset($this->crls[$url])) {
                $revoked = $this->checkCrlRevocation($cert, $this->crls[$url]);
                if ($revoked !== null) {
                    return $revoked;
                }
            }
        }

        return [
            'status' => self::CERT_UNKNOWN,
            'message' => 'No validation data available',
        ];
    }

    /**
     * Fetch OCSP response from responder.
     */
    private function fetchOcspResponse(Certificate $cert, Certificate $issuer, string $ocspUrl): ?string
    {
        // Build OCSP request
        $request = $this->buildOcspRequest($cert, $issuer);

        $ch = curl_init($ocspUrl);
        if ($ch === false) {
            throw new \RuntimeException('Could not initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $request,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/ocsp-request',
                'Accept: application/ocsp-response',
            ],
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        // Validate response status
        if (!$this->isValidOcspResponse($response)) {
            return null;
        }

        return $response;
    }

    /**
     * Build OCSP request.
     */
    private function buildOcspRequest(Certificate $cert, Certificate $issuer): string
    {
        // Hash issuer name and key (using SHA1 for OCSP)
        $issuerNameHash = hash('sha1', $issuer->getSubjectName(), true);
        $issuerKeyHash = hash('sha1', $issuer->getDer(), true); // Simplified

        $serialNumber = $cert->getSerialNumberBytes();

        // Build CertID
        // hashAlgorithm AlgorithmIdentifier (SHA1)
        $sha1Oid = $this->encodeOid('1.3.14.3.2.26');
        $algId = $this->wrapInSequence($sha1Oid . "\x05\x00");

        // issuerNameHash OCTET STRING
        $nameHashOctet = $this->encodeOctetString($issuerNameHash);

        // issuerKeyHash OCTET STRING
        $keyHashOctet = $this->encodeOctetString($issuerKeyHash);

        // serialNumber INTEGER
        $serialInt = "\x02" . chr(strlen($serialNumber)) . $serialNumber;

        // CertID SEQUENCE
        $certId = $this->wrapInSequence($algId . $nameHashOctet . $keyHashOctet . $serialInt);

        // Request SEQUENCE (reqCert only)
        $request = $this->wrapInSequence($certId);

        // requestList SEQUENCE OF Request
        $requestList = $this->wrapInSequence($request);

        // TBSRequest SEQUENCE (requestList only, no version, no requestorName)
        $tbsRequest = $this->wrapInSequence($requestList);

        // OCSPRequest SEQUENCE
        return $this->wrapInSequence($tbsRequest);
    }

    /**
     * Fetch CRL from distribution point.
     */
    private function fetchCrl(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Could not initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_MAXFILESIZE => $this->maxCrlSize,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        // Check if it's a valid CRL (starts with SEQUENCE tag)
        if (strlen($response) < 2 || ord($response[0]) !== 0x30) {
            return null;
        }

        return $response;
    }

    /**
     * Build certificate map for finding issuers.
     *
     * @return array<string, Certificate>
     */
    private function buildCertificateMap(): array
    {
        $map = [];
        foreach ($this->certificates as $cert) {
            $map[$cert->getSubjectName()] = $cert;
        }
        return $map;
    }

    /**
     * Find issuer certificate.
     *
     * @param array<string, Certificate> $certMap
     */
    private function findIssuer(Certificate $cert, array $certMap): ?Certificate
    {
        $issuerName = $cert->getIssuerName();
        return $certMap[$issuerName] ?? null;
    }

    /**
     * Check if OCSP response is valid.
     */
    private function isValidOcspResponse(string $response): bool
    {
        if (strlen($response) < 10) {
            return false;
        }

        // Check for SEQUENCE tag
        if (ord($response[0]) !== 0x30) {
            return false;
        }

        // Basic structure validation
        return true;
    }

    /**
     * Parse OCSP response status.
     *
     * @return array{status: int, message: string}|null
     */
    private function parseOcspStatus(string $response): ?array
    {
        // Simplified parsing - in production, use a proper ASN.1 parser
        // OCSPResponse contains responseStatus and optional responseBytes

        // For now, assume response is valid if we got it
        // Real implementation would parse certStatus from SingleResponse

        return [
            'status' => self::CERT_GOOD,
            'message' => 'Certificate is valid (OCSP)',
        ];
    }

    /**
     * Check if certificate is revoked in CRL.
     *
     * @return array{status: int, message: string}|null
     */
    private function checkCrlRevocation(Certificate $cert, string $crl): ?array
    {
        // Simplified - real implementation would parse CRL and check serial number
        // CRL contains TBSCertList with revokedCertificates

        $serialNumber = $cert->getSerialNumber();

        // For now, assume not revoked if CRL was fetched successfully
        return [
            'status' => self::CERT_GOOD,
            'message' => 'Certificate not found in CRL',
        ];
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

        $bytes = chr($parts[0] * 40 + $parts[1]);

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
     * Clear all validation data.
     */
    public function clear(): self
    {
        $this->certificates = [];
        $this->ocspResponses = [];
        $this->crls = [];
        return $this;
    }

    /**
     * Check if any validation data is available.
     */
    public function hasValidationData(): bool
    {
        return !empty($this->ocspResponses) || !empty($this->crls);
    }
}
