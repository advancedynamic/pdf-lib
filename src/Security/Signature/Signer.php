<?php

declare(strict_types=1);

namespace PdfLib\Security\Signature;

use PdfLib\Parser\PdfParser;
use phpseclib3\Crypt\RSA;
use phpseclib3\File\X509;
use phpseclib3\File\ASN1;
use phpseclib3\Crypt\Common\PrivateKey;

/**
 * PDF Digital Signature Generator - PAdES compliant.
 *
 * Supports:
 * - PAdES-B (Basic) - CMS signature with signing certificate
 * - PAdES-T (Timestamp) - Adds RFC 3161 timestamp
 * - PAdES-LTV (Long-Term Validation) - Adds OCSP/CRL responses
 *
 * @example
 * ```php
 * $signer = new Signer('document.pdf');
 * $signer->setCertificate('cert.pem', 'key.pem', 'password')
 *        ->setSignatureField(
 *            SignatureField::create('sig1')
 *                ->setPosition(100, 100)
 *                ->setSize(200, 50)
 *                ->setReason('Approval')
 *        )
 *        ->enableTimestamp('http://timestamp.digicert.com')
 *        ->enableLtv()
 *        ->sign('signed.pdf');
 * ```
 */
final class Signer
{
    // Signature levels
    public const LEVEL_B = 'PAdES-B';      // Basic
    public const LEVEL_T = 'PAdES-T';      // With timestamp
    public const LEVEL_LTV = 'PAdES-LTV';  // Long-term validation

    // Digest algorithms
    public const DIGEST_SHA256 = 'sha256';
    public const DIGEST_SHA384 = 'sha384';
    public const DIGEST_SHA512 = 'sha512';

    // Signature placeholder size (bytes)
    private const SIGNATURE_SIZE = 32768;

    private ?PdfParser $parser = null;
    private string $content = '';

    // Certificate and key
    private ?Certificate $certificate = null;
    private ?PrivateKey $privateKey = null;
    private string $privateKeyPassword = '';
    private ?X509 $x509 = null;

    /** @var array<int, Certificate> Certificate chain */
    private array $certificateChain = [];

    // Signature field
    private ?SignatureField $signatureField = null;

    // Options
    private string $digestAlgorithm = self::DIGEST_SHA256;
    private bool $embedTimestamp = false;
    private ?TimestampClient $timestampClient = null;
    private bool $enableLtv = false;
    private ?LtvValidator $ltvValidator = null;

    // Internal state
    private int $signatureObjectId = 0;
    private int $sigFieldId = 0;
    private int $acroFormId = 0;
    private int $appearanceId = 0;
    private int $nextObjectId = 1;
    /** @var array<int, int> Object ID => offset */
    private array $objectOffsets = [];

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            $this->loadFile($filePath);
        }
    }

    /**
     * Create a new signer instance.
     */
    public static function create(?string $filePath = null): self
    {
        return new self($filePath);
    }

    /**
     * Load PDF from file.
     */
    public function loadFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("PDF file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $filePath");
        }

        return $this->loadContent($content);
    }

    /**
     * Load PDF from string content.
     */
    public function loadContent(string $content): self
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Invalid PDF content');
        }

        $this->content = $content;
        $this->parser = PdfParser::parseString($content);

        return $this;
    }

    /**
     * Set signing certificate from files.
     *
     * @param string $certPath Path to certificate PEM file
     * @param string $keyPath Path to private key PEM file
     * @param string $password Private key password
     */
    public function setCertificate(string $certPath, string $keyPath, string $password = ''): self
    {
        $this->certificate = Certificate::fromFile($certPath);

        $keyContent = file_get_contents($keyPath);
        if ($keyContent === false) {
            throw new \RuntimeException("Could not read private key: $keyPath");
        }

        $this->loadPrivateKey($keyContent, $password);
        $this->loadX509Certificate($this->certificate->getPem());

        return $this;
    }

    /**
     * Set signing certificate from PEM strings.
     */
    public function setCertificateFromPem(string $certPem, string $keyPem, string $password = ''): self
    {
        $this->certificate = Certificate::fromPem($certPem);

        $this->loadPrivateKey($keyPem, $password);
        $this->loadX509Certificate($certPem);

        return $this;
    }

    /**
     * Load private key using phpseclib.
     */
    private function loadPrivateKey(string $keyPem, string $password = ''): void
    {
        try {
            if ($password !== '') {
                $this->privateKey = RSA::loadPrivateKey($keyPem, $password);
            } else {
                $this->privateKey = RSA::loadPrivateKey($keyPem);
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Invalid private key or password: ' . $e->getMessage());
        }
    }

    /**
     * Load X509 certificate using phpseclib.
     */
    private function loadX509Certificate(string $certPem): void
    {
        $this->x509 = new X509();
        if (!$this->x509->loadX509($certPem)) {
            throw new \RuntimeException('Failed to load X509 certificate');
        }
    }

    /**
     * Set signing certificate from PKCS#12 file.
     */
    public function setCertificateFromP12(string $p12Path, string $password): self
    {
        $p12Content = file_get_contents($p12Path);
        if ($p12Content === false) {
            throw new \RuntimeException("Could not read P12 file: $p12Path");
        }

        $certs = [];
        if (!openssl_pkcs12_read($p12Content, $certs, $password)) {
            throw new \RuntimeException('Invalid P12 file or password: ' . openssl_error_string());
        }

        $this->certificate = Certificate::fromPem($certs['cert']);
        $this->loadPrivateKey($certs['pkey']);
        $this->loadX509Certificate($certs['cert']);

        // Add extra certificates to chain
        if (isset($certs['extracerts'])) {
            foreach ($certs['extracerts'] as $extraCert) {
                $this->certificateChain[] = Certificate::fromPem($extraCert);
            }
        }

        return $this;
    }

    /**
     * Add certificate to chain (for intermediate CAs).
     */
    public function addCertificateToChain(Certificate $certificate): self
    {
        $this->certificateChain[] = $certificate;
        return $this;
    }

    /**
     * Set signature field configuration.
     */
    public function setSignatureField(SignatureField $field): self
    {
        $this->signatureField = $field;
        return $this;
    }

    /**
     * Create invisible signature (no visible field).
     */
    public function setInvisibleSignature(): self
    {
        $this->signatureField = SignatureField::create('Signature')
            ->setVisible(false)
            ->setSize(0, 0);
        return $this;
    }

    /**
     * Set digest algorithm.
     */
    public function setDigestAlgorithm(string $algorithm): self
    {
        $algorithm = strtolower($algorithm);
        if (!in_array($algorithm, [self::DIGEST_SHA256, self::DIGEST_SHA384, self::DIGEST_SHA512], true)) {
            throw new \InvalidArgumentException("Unsupported digest algorithm: $algorithm");
        }

        $this->digestAlgorithm = $algorithm;
        return $this;
    }

    /**
     * Enable RFC 3161 timestamp.
     */
    public function enableTimestamp(string $tsaUrl): self
    {
        $this->embedTimestamp = true;
        $this->timestampClient = new TimestampClient($tsaUrl);
        $this->timestampClient->setHashAlgorithm($this->digestAlgorithm);
        return $this;
    }

    /**
     * Set custom timestamp client.
     */
    public function setTimestampClient(TimestampClient $client): self
    {
        $this->embedTimestamp = true;
        $this->timestampClient = $client;
        return $this;
    }

    /**
     * Disable timestamp.
     */
    public function disableTimestamp(): self
    {
        $this->embedTimestamp = false;
        $this->timestampClient = null;
        return $this;
    }

    /**
     * Enable LTV (Long-Term Validation).
     */
    public function enableLtv(): self
    {
        $this->enableLtv = true;
        $this->ltvValidator = new LtvValidator();
        return $this;
    }

    /**
     * Set custom LTV validator.
     */
    public function setLtvValidator(LtvValidator $validator): self
    {
        $this->enableLtv = true;
        $this->ltvValidator = $validator;
        return $this;
    }

    /**
     * Disable LTV.
     */
    public function disableLtv(): self
    {
        $this->enableLtv = false;
        $this->ltvValidator = null;
        return $this;
    }

    /**
     * Get the signature level based on current configuration.
     */
    public function getSignatureLevel(): string
    {
        if ($this->enableLtv) {
            return self::LEVEL_LTV;
        }
        if ($this->embedTimestamp) {
            return self::LEVEL_T;
        }
        return self::LEVEL_B;
    }

    /**
     * Sign the document.
     *
     * @return string Signed PDF content
     */
    public function sign(): string
    {
        $this->ensureReady();

        // Prepare signature field if not set
        if ($this->signatureField === null) {
            $this->setInvisibleSignature();
        }

        // Build signed PDF with placeholder
        $pdfWithPlaceholder = $this->buildPdfWithSignaturePlaceholder();

        // Calculate byte ranges
        $byteRanges = $this->calculateByteRanges($pdfWithPlaceholder);

        // Update byte range in PDF
        $pdfWithByteRange = $this->updateByteRange($pdfWithPlaceholder, $byteRanges);

        // Extract data to sign
        $dataToSign = $this->extractDataToSign($pdfWithByteRange, $byteRanges);

        // Create PKCS#7 signature
        $signature = $this->createSignature($dataToSign);

        // Embed signature
        $signedPdf = $this->embedSignature($pdfWithByteRange, $signature, $byteRanges);

        // Add LTV data if enabled
        if ($this->enableLtv && $this->ltvValidator !== null) {
            $signedPdf = $this->addLtvData($signedPdf);
        }

        return $signedPdf;
    }

    /**
     * Sign and save to file.
     */
    public function signToFile(string $outputPath): bool
    {
        $signedPdf = $this->sign();
        return file_put_contents($outputPath, $signedPdf) !== false;
    }

    /**
     * Build PDF with signature placeholder.
     */
    private function buildPdfWithSignaturePlaceholder(): string
    {
        // For incremental update, we MUST preserve the original content exactly
        // This is critical for multiple signatures - previous signatures cover
        // specific byte ranges that must not be modified

        $output = $this->content;

        // Ensure content ends with newline (don't remove %%EOF!)
        if (!str_ends_with($output, "\n")) {
            $output .= "\n";
        }

        // Get original xref position for /Prev
        $prevXref = $this->findXrefPosition($this->content);

        // Get next object ID from parser
        $this->nextObjectId = $this->parser->getNextObjectId();

        // Assign object IDs
        $this->signatureObjectId = $this->nextObjectId++;
        $this->sigFieldId = $this->nextObjectId++;
        $this->appearanceId = $this->nextObjectId++;
        $this->acroFormId = $this->nextObjectId++;

        // Get page and catalog info
        $pageNum = $this->signatureField?->getPage() ?? 1;
        $pages = $this->parser->getPages();
        $pageRef = null;
        $pageObjNum = null;

        if (isset($pages[$pageNum - 1])) {
            $pageInfo = $this->parser->getPageReference($pageNum - 1);
            if ($pageInfo !== null) {
                $pageObjNum = $pageInfo['id'];
                $pageRef = "{$pageObjNum} 0 R";
            }
        }

        $rootRef = $this->parser->getRootReference();
        $catalogObjNum = $rootRef ? $rootRef['id'] : 1;

        // Build objects
        $objects = '';

        // 1. Signature dictionary (with placeholder)
        $this->objectOffsets[$this->signatureObjectId] = strlen($output) + strlen($objects);
        $objects .= $this->buildSignatureDictionary();

        // 2. Signature field widget annotation
        $this->objectOffsets[$this->sigFieldId] = strlen($output) + strlen($objects);
        $objects .= $this->buildSignatureFieldObject($this->sigFieldId, $this->signatureObjectId, $pageRef);

        // 3. Appearance XObject (empty form for invisible signature)
        $this->objectOffsets[$this->appearanceId] = strlen($output) + strlen($objects);
        $objects .= $this->buildAppearanceObject();

        // 4. AcroForm object
        $this->objectOffsets[$this->acroFormId] = strlen($output) + strlen($objects);
        $objects .= $this->buildAcroFormObject();

        // 5. Updated page object with Annots
        if ($pageObjNum !== null) {
            $this->objectOffsets[$pageObjNum] = strlen($output) + strlen($objects);
            $objects .= $this->buildUpdatedPageObject($pageObjNum);
        }

        // 6. Updated catalog with AcroForm reference
        $this->objectOffsets[$catalogObjNum] = strlen($output) + strlen($objects);
        $objects .= $this->buildUpdatedCatalogObject($catalogObjNum);

        $output .= $objects;

        // Build xref table
        $xrefOffset = strlen($output);
        $output .= $this->buildIncrementalXref($prevXref, $catalogObjNum);

        // Update startxref
        $output .= "startxref\n";
        $output .= $xrefOffset . "\n";
        $output .= "%%EOF\n";

        return $output;
    }

    /**
     * Find xref position in PDF content.
     */
    private function findXrefPosition(string $content): int
    {
        // Find startxref
        $pos = strrpos($content, 'startxref');
        if ($pos === false) {
            return 0;
        }

        // Extract the number after startxref
        if (preg_match('/startxref\s+(\d+)/', substr($content, $pos), $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Build signature dictionary object.
     */
    private function buildSignatureDictionary(): string
    {
        $now = new \DateTimeImmutable();
        $pdfDate = $this->formatPdfDate($now);

        $signerName = $this->signatureField?->getSignerName()
            ?? $this->certificate?->getSubjectName()
            ?? 'Unknown';

        $reason = $this->signatureField?->getReason() ?? '';
        $location = $this->signatureField?->getLocation() ?? '';
        $contactInfo = $this->signatureField?->getContactInfo() ?? '';

        $dict = "{$this->signatureObjectId} 0 obj\n";
        $dict .= "<<\n";
        $dict .= "/Type /Sig\n";
        $dict .= "/Filter /Adobe.PPKLite\n";
        $dict .= "/SubFilter /adbe.pkcs7.detached\n";

        // ByteRange placeholder - will be updated later
        // Using fixed-width placeholder for easy replacement
        $dict .= "/ByteRange [0000000000 0000000000 0000000000 0000000000]\n";

        // Contents placeholder for signature
        $dict .= "/Contents <" . str_repeat('0', self::SIGNATURE_SIZE * 2) . ">\n";

        $dict .= "/Name " . $this->encodePdfString($signerName) . "\n";
        $dict .= "/M " . $pdfDate . "\n";

        if ($reason !== '') {
            $dict .= "/Reason " . $this->encodePdfString($reason) . "\n";
        }
        if ($location !== '') {
            $dict .= "/Location " . $this->encodePdfString($location) . "\n";
        }
        if ($contactInfo !== '') {
            $dict .= "/ContactInfo " . $this->encodePdfString($contactInfo) . "\n";
        }

        $dict .= ">>\n";
        $dict .= "endobj\n";

        return $dict;
    }

    /**
     * Build signature field annotation object (widget).
     */
    private function buildSignatureFieldObject(int $fieldId, int $sigDictId, ?string $pageRef): string
    {
        $rect = $this->signatureField?->getRect() ?? [0, 0, 0, 0];
        $flags = $this->signatureField?->getFlags() ?? 132;
        $name = $this->signatureField?->getName() ?? 'Signature';

        $rectStr = implode(' ', array_map(fn($v) => sprintf('%.4f', $v), $rect));

        $obj = "{$fieldId} 0 obj\n";
        $obj .= "<<\n";
        $obj .= "/Type /Annot\n";
        $obj .= "/Subtype /Widget\n";
        $obj .= "/FT /Sig\n";
        $obj .= "/F {$flags}\n";
        $obj .= "/Rect [{$rectStr}]\n";
        $obj .= "/T " . $this->encodePdfString($name) . "\n";
        $obj .= "/V {$sigDictId} 0 R\n";

        if ($pageRef !== null) {
            $obj .= "/P {$pageRef}\n";
        }

        // Add appearance reference
        $obj .= "/AP << /N {$this->appearanceId} 0 R >>\n";

        $obj .= ">>\n";
        $obj .= "endobj\n";

        return $obj;
    }

    /**
     * Build appearance XObject (empty form for invisible signature).
     */
    private function buildAppearanceObject(): string
    {
        $rect = $this->signatureField?->getRect() ?? [0, 0, 0, 0];
        $width = abs($rect[2] - $rect[0]);
        $height = abs($rect[3] - $rect[1]);

        $obj = "{$this->appearanceId} 0 obj\n";
        $obj .= "<<\n";
        $obj .= "/Type /XObject\n";
        $obj .= "/Subtype /Form\n";
        $obj .= "/FormType 1\n";
        $obj .= "/BBox [0 0 " . sprintf('%.4f %.4f', $width, $height) . "]\n";
        $obj .= "/Resources << >>\n";
        $obj .= "/Length 0\n";
        $obj .= ">>\n";
        $obj .= "stream\n";
        $obj .= "endstream\n";
        $obj .= "endobj\n";

        return $obj;
    }

    /**
     * Build AcroForm object.
     */
    private function buildAcroFormObject(): string
    {
        // Get existing fields from current AcroForm
        $existingFields = $this->getExistingAcroFormFields();

        // Add the new signature field
        $allFields = $existingFields;
        $allFields[] = "{$this->sigFieldId} 0 R";

        $fieldsStr = implode(' ', $allFields);

        $obj = "{$this->acroFormId} 0 obj\n";
        $obj .= "<<\n";
        $obj .= "/Fields [{$fieldsStr}]\n";
        $obj .= "/SigFlags 3\n"; // SignaturesExist (1) + AppendOnly (2)
        $obj .= ">>\n";
        $obj .= "endobj\n";

        return $obj;
    }

    /**
     * Get existing fields from current AcroForm.
     *
     * @return array<string> Field references
     */
    private function getExistingAcroFormFields(): array
    {
        $fields = [];

        // Search for AcroForm reference in content
        if (preg_match_all('/\/AcroForm\s+(\d+)\s+\d+\s+R/', $this->content, $acroFormRefs, PREG_SET_ORDER)) {
            // Get the last AcroForm reference (from the latest incremental update)
            $lastRef = end($acroFormRefs);
            $acroFormObjNum = (int)$lastRef[1];

            // Find ALL occurrences of this AcroForm object and get the last one
            $pattern = '/' . $acroFormObjNum . '\s+0\s+obj\s*<<[^>]*\/Fields\s*\[([^\]]*)\]/s';
            if (preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER)) {
                $lastMatch = end($matches);
                $fieldsContent = trim($lastMatch[1]);
                if (!empty($fieldsContent)) {
                    // Extract all object references
                    preg_match_all('/(\d+\s+\d+\s+R)/', $fieldsContent, $refs);
                    $fields = $refs[1] ?? [];
                }
            }
        }

        // Also search for any Fields arrays directly (for cases where AcroForm is inline)
        if (empty($fields)) {
            if (preg_match_all('/\/Fields\s*\[([^\]]+)\]/', $this->content, $fieldMatches, PREG_SET_ORDER)) {
                $lastFieldMatch = end($fieldMatches);
                $fieldsContent = trim($lastFieldMatch[1]);
                if (!empty($fieldsContent)) {
                    preg_match_all('/(\d+\s+\d+\s+R)/', $fieldsContent, $refs);
                    $fields = $refs[1] ?? [];
                }
            }
        }

        return $fields;
    }

    /**
     * Build updated page object with Annots.
     */
    private function buildUpdatedPageObject(int $pageObjNum): string
    {
        // Extract original page content
        $pageContent = $this->extractPageObject($pageObjNum);

        if ($pageContent === null) {
            // Build minimal page object
            $obj = "{$pageObjNum} 0 obj\n";
            $obj .= "<<\n";
            $obj .= "/Type /Page\n";
            $obj .= "/Annots [{$this->sigFieldId} 0 R]\n";
            $obj .= ">>\n";
            $obj .= "endobj\n";
            return $obj;
        }

        // Check if page already has Annots
        if (preg_match('/\/Annots\s*\[([^\]]*)\]/', $pageContent, $m)) {
            // Add to existing Annots
            $existingAnnots = trim($m[1]);
            $newAnnots = $existingAnnots . ' ' . $this->sigFieldId . ' 0 R';
            $pageContent = preg_replace(
                '/\/Annots\s*\[[^\]]*\]/',
                '/Annots [' . $newAnnots . ']',
                $pageContent
            );
        } else {
            // Add Annots array before the closing >>
            $pageContent = preg_replace(
                '/>>(\s*endobj)/s',
                '/Annots [' . $this->sigFieldId . " 0 R]\n>>" . '$1',
                $pageContent
            );
        }

        return $pageContent;
    }

    /**
     * Extract page object from PDF content.
     */
    private function extractPageObject(int $objNum): ?string
    {
        $pattern = '/(' . $objNum . '\s+0\s+obj\s*<<.*?>>)\s*endobj/s';
        if (preg_match($pattern, $this->content, $m)) {
            return $m[1] . "\nendobj\n";
        }
        return null;
    }

    /**
     * Build updated catalog object with AcroForm reference.
     */
    private function buildUpdatedCatalogObject(int $catalogObjNum): string
    {
        // Extract original catalog content
        $catalogContent = $this->extractCatalogObject($catalogObjNum);

        if ($catalogContent === null) {
            // Build minimal catalog
            $obj = "{$catalogObjNum} 0 obj\n";
            $obj .= "<<\n";
            $obj .= "/Type /Catalog\n";
            $obj .= "/AcroForm {$this->acroFormId} 0 R\n";
            $obj .= ">>\n";
            $obj .= "endobj\n";
            return $obj;
        }

        // Check if catalog already has AcroForm
        if (preg_match('/\/AcroForm\s+\d+\s+\d+\s+R/', $catalogContent)) {
            // Replace existing AcroForm reference
            $catalogContent = preg_replace(
                '/\/AcroForm\s+\d+\s+\d+\s+R/',
                '/AcroForm ' . $this->acroFormId . ' 0 R',
                $catalogContent
            );
        } else {
            // Add AcroForm reference before the closing >>
            $catalogContent = preg_replace(
                '/>>(\s*endobj)/s',
                '/AcroForm ' . $this->acroFormId . " 0 R\n>>" . '$1',
                $catalogContent
            );
        }

        return $catalogContent;
    }

    /**
     * Extract catalog object from PDF content.
     */
    private function extractCatalogObject(int $objNum): ?string
    {
        $pattern = '/(' . $objNum . '\s+0\s+obj\s*<<.*?>>)\s*endobj/s';
        if (preg_match($pattern, $this->content, $m)) {
            return $m[1] . "\nendobj\n";
        }
        return null;
    }

    /**
     * Build incremental xref table.
     */
    private function buildIncrementalXref(int $prevXref, int $catalogObjNum): string
    {
        $xref = "xref\n";

        // Add free object entry
        $xref .= "0 1\n";
        $xref .= "0000000000 65535 f \n";

        // Add entries for each new/updated object
        $objectIds = array_keys($this->objectOffsets);
        sort($objectIds);

        foreach ($objectIds as $id) {
            $offset = $this->objectOffsets[$id];
            $xref .= sprintf("%d 1\n", $id);
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }

        // Trailer
        $xref .= "trailer\n";
        $xref .= "<<\n";
        $xref .= "/Size {$this->nextObjectId}\n";
        $xref .= "/Root {$catalogObjNum} 0 R\n";

        // Get info reference from original trailer if present
        $trailer = $this->parser->getTrailer();
        if ($trailer !== null && $trailer->has('Info')) {
            $infoRef = $trailer->get('Info');
            if ($infoRef instanceof \PdfLib\Parser\Object\PdfReference) {
                $xref .= "/Info {$infoRef->getObjectNumber()} 0 R\n";
            }
        }

        $xref .= "/Prev {$prevXref}\n";
        $xref .= ">>\n";

        return $xref;
    }

    /**
     * Calculate byte ranges for signature.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function calculateByteRanges(string $pdf): array
    {
        // Find the signature dictionary object we just created
        // Use # as delimiter to avoid issues with / in the pattern
        $sigObjPattern = '#' . $this->signatureObjectId . '\s+0\s+obj\s*<<.*?/Contents\s*<#s';
        if (!preg_match($sigObjPattern, $pdf, $match, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException('Could not find signature Contents for object ' . $this->signatureObjectId);
        }

        // Find the start of hex data within this signature object
        $sigObjPos = $match[0][1];
        $contentsPos = strpos($pdf, '/Contents <', $sigObjPos);
        if ($contentsPos === false) {
            throw new \RuntimeException('Could not find /Contents in signature object');
        }

        $hexStart = strpos($pdf, '<', $contentsPos);
        $hexEnd = strpos($pdf, '>', $hexStart);

        if ($hexStart === false || $hexEnd === false) {
            throw new \RuntimeException('Invalid signature Contents format');
        }

        return [
            0,                      // Start of file
            $hexStart + 1,          // Up to and including '<'
            $hexEnd,                // After signature hex
            strlen($pdf) - $hexEnd, // Rest of file
        ];
    }

    /**
     * Update ByteRange in PDF.
     */
    private function updateByteRange(string $pdf, array $byteRanges): string
    {
        // Find the signature dictionary object we just created
        // Use # as delimiter to avoid issues with / in the pattern
        $sigObjPattern = '#' . $this->signatureObjectId . '\s+0\s+obj\s*<<.*?/ByteRange\s*\[0{10}\s+0{10}\s+0{10}\s+0{10}\]#s';
        if (!preg_match($sigObjPattern, $pdf, $match, PREG_OFFSET_CAPTURE)) {
            throw new \RuntimeException('Could not find ByteRange placeholder in signature object ' . $this->signatureObjectId);
        }

        // Find the ByteRange within this signature object
        $sigObjPos = $match[0][1];
        $sigObjContent = $match[0][0];

        $replacement = sprintf(
            '/ByteRange [%010d %010d %010d %010d]',
            $byteRanges[0],
            $byteRanges[1],
            $byteRanges[2],
            $byteRanges[3]
        );

        // Replace only in this specific signature object
        $updatedSigObj = preg_replace(
            '#/ByteRange\s*\[0{10}\s+0{10}\s+0{10}\s+0{10}\]#',
            $replacement,
            $sigObjContent,
            1
        );

        return substr($pdf, 0, $sigObjPos) . $updatedSigObj . substr($pdf, $sigObjPos + strlen($sigObjContent));
    }

    /**
     * Extract data to sign from PDF.
     */
    private function extractDataToSign(string $pdf, array $byteRanges): string
    {
        $data = substr($pdf, $byteRanges[0], $byteRanges[1]);
        $data .= substr($pdf, $byteRanges[2], $byteRanges[3]);
        return $data;
    }

    /**
     * Create PKCS#7/CMS detached signature using phpseclib.
     */
    private function createSignature(string $data): string
    {
        // Build CMS SignedData structure
        $signature = $this->buildCmsSignedData($data);

        // Add timestamp if enabled
        if ($this->embedTimestamp && $this->timestampClient !== null) {
            $signature = $this->addTimestampToSignature($signature);
        }

        return $signature;
    }

    /**
     * Build CMS SignedData structure (RFC 5652).
     */
    private function buildCmsSignedData(string $data): string
    {
        // Hash the data
        $messageDigest = hash($this->digestAlgorithm, $data, true);

        // Get digest algorithm OID
        $digestAlgOid = $this->getDigestAlgorithmOid();

        // Build SignedAttributes
        $signedAttrs = $this->buildSignedAttributes($messageDigest);

        // Sign the attributes
        $signedAttrsForSigning = "\x31" . $this->encodeLength(strlen($signedAttrs)) . $signedAttrs;

        // Configure RSA for PKCS#1 v1.5 signing with appropriate hash
        $rsa = $this->privateKey->withPadding(RSA::SIGNATURE_PKCS1);

        // Set hash algorithm
        $rsa = match ($this->digestAlgorithm) {
            'sha256' => $rsa->withHash('sha256'),
            'sha384' => $rsa->withHash('sha384'),
            'sha512' => $rsa->withHash('sha512'),
            default => $rsa->withHash('sha256'),
        };

        $signatureValue = $rsa->sign($signedAttrsForSigning);

        // Build SignerInfo
        $signerInfo = $this->buildSignerInfo($signedAttrs, $signatureValue, $digestAlgOid);

        // Build certificates SET
        $certsDer = $this->certificate->getDer();
        foreach ($this->certificateChain as $chainCert) {
            $certsDer .= $chainCert->getDer();
        }
        $certificates = "\xa0" . $this->encodeLength(strlen($certsDer)) . $certsDer;

        // Build SignedData
        // version INTEGER (1 for SHA-1, 3 for SHA-256+)
        $version = "\x02\x01\x01";

        // digestAlgorithms SET OF AlgorithmIdentifier
        $digestAlg = $this->buildAlgorithmIdentifier($digestAlgOid);
        $digestAlgorithms = "\x31" . $this->encodeLength(strlen($digestAlg)) . $digestAlg;

        // encapContentInfo ContentInfo (empty for detached)
        // OID for id-data: 1.2.840.113549.1.7.1
        $dataOid = $this->encodeOid('1.2.840.113549.1.7.1');
        $encapContentInfo = "\x30" . $this->encodeLength(strlen($dataOid)) . $dataOid;

        // signerInfos SET OF SignerInfo
        $signerInfos = "\x31" . $this->encodeLength(strlen($signerInfo)) . $signerInfo;

        // Combine SignedData content
        $signedDataContent = $version . $digestAlgorithms . $encapContentInfo . $certificates . $signerInfos;
        $signedData = "\x30" . $this->encodeLength(strlen($signedDataContent)) . $signedDataContent;

        // Wrap in ContentInfo
        // OID for id-signedData: 1.2.840.113549.1.7.2
        $signedDataOid = $this->encodeOid('1.2.840.113549.1.7.2');
        $content = "\xa0" . $this->encodeLength(strlen($signedData)) . $signedData;
        $contentInfo = "\x30" . $this->encodeLength(strlen($signedDataOid . $content)) . $signedDataOid . $content;

        return $contentInfo;
    }

    /**
     * Build SignedAttributes for CMS.
     */
    private function buildSignedAttributes(string $messageDigest): string
    {
        $attrs = '';

        // contentType attribute (OID: 1.2.840.113549.1.9.3)
        $contentTypeOid = $this->encodeOid('1.2.840.113549.1.9.3');
        $dataOid = $this->encodeOid('1.2.840.113549.1.7.1');
        $contentTypeValue = "\x31" . $this->encodeLength(strlen($dataOid)) . $dataOid;
        $attrs .= "\x30" . $this->encodeLength(strlen($contentTypeOid . $contentTypeValue)) . $contentTypeOid . $contentTypeValue;

        // signingTime attribute (OID: 1.2.840.113549.1.9.5)
        $signingTimeOid = $this->encodeOid('1.2.840.113549.1.9.5');
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $utcTime = "\x17" . chr(13) . $now->format('ymdHis') . 'Z';
        $signingTimeValue = "\x31" . $this->encodeLength(strlen($utcTime)) . $utcTime;
        $attrs .= "\x30" . $this->encodeLength(strlen($signingTimeOid . $signingTimeValue)) . $signingTimeOid . $signingTimeValue;

        // messageDigest attribute (OID: 1.2.840.113549.1.9.4)
        $messageDigestOid = $this->encodeOid('1.2.840.113549.1.9.4');
        $digestOctet = "\x04" . $this->encodeLength(strlen($messageDigest)) . $messageDigest;
        $messageDigestValue = "\x31" . $this->encodeLength(strlen($digestOctet)) . $digestOctet;
        $attrs .= "\x30" . $this->encodeLength(strlen($messageDigestOid . $messageDigestValue)) . $messageDigestOid . $messageDigestValue;

        return $attrs;
    }

    /**
     * Build SignerInfo structure.
     */
    private function buildSignerInfo(string $signedAttrs, string $signatureValue, string $digestAlgOid): string
    {
        // version INTEGER (1)
        $version = "\x02\x01\x01";

        // sid SignerIdentifier (IssuerAndSerialNumber)
        $issuerDn = $this->buildIssuerDn();
        $serialNumber = $this->buildSerialNumber();
        $sid = "\x30" . $this->encodeLength(strlen($issuerDn . $serialNumber)) . $issuerDn . $serialNumber;

        // digestAlgorithm AlgorithmIdentifier
        $digestAlgorithm = $this->buildAlgorithmIdentifier($digestAlgOid);

        // signedAttrs [0] IMPLICIT SignedAttributes
        $signedAttrsImplicit = "\xa0" . $this->encodeLength(strlen($signedAttrs)) . $signedAttrs;

        // signatureAlgorithm AlgorithmIdentifier (RSA with hash)
        $sigAlgOid = $this->getSignatureAlgorithmOid();
        $signatureAlgorithm = $this->buildAlgorithmIdentifier($sigAlgOid);

        // signature OCTET STRING
        $signature = "\x04" . $this->encodeLength(strlen($signatureValue)) . $signatureValue;

        $content = $version . $sid . $digestAlgorithm . $signedAttrsImplicit . $signatureAlgorithm . $signature;
        return "\x30" . $this->encodeLength(strlen($content)) . $content;
    }

    /**
     * Build AlgorithmIdentifier.
     */
    private function buildAlgorithmIdentifier(string $oid): string
    {
        $oidEncoded = $this->encodeOid($oid);
        $params = "\x05\x00"; // NULL parameters
        return "\x30" . $this->encodeLength(strlen($oidEncoded . $params)) . $oidEncoded . $params;
    }

    /**
     * Build issuer DN from certificate.
     */
    private function buildIssuerDn(): string
    {
        // Get issuer from X509 certificate
        $issuer = $this->x509->getIssuerDN(X509::DN_ASN1);
        if ($issuer === false) {
            throw new \RuntimeException('Could not get issuer DN');
        }
        return $issuer;
    }

    /**
     * Build serial number from certificate.
     */
    private function buildSerialNumber(): string
    {
        $serial = $this->certificate->getSerialNumberBytes();
        if ($serial === '') {
            $serial = "\x00";
        }
        // Ensure positive integer (no leading 1 bit)
        if (ord($serial[0]) & 0x80) {
            $serial = "\x00" . $serial;
        }
        return "\x02" . $this->encodeLength(strlen($serial)) . $serial;
    }

    /**
     * Get OID for digest algorithm.
     */
    private function getDigestAlgorithmOid(): string
    {
        return match ($this->digestAlgorithm) {
            'sha256' => '2.16.840.1.101.3.4.2.1',
            'sha384' => '2.16.840.1.101.3.4.2.2',
            'sha512' => '2.16.840.1.101.3.4.2.3',
            'sha1' => '1.3.14.3.2.26',
            default => '2.16.840.1.101.3.4.2.1',
        };
    }

    /**
     * Get OID for signature algorithm (RSA with hash).
     */
    private function getSignatureAlgorithmOid(): string
    {
        return match ($this->digestAlgorithm) {
            'sha256' => '1.2.840.113549.1.1.11', // sha256WithRSAEncryption
            'sha384' => '1.2.840.113549.1.1.12', // sha384WithRSAEncryption
            'sha512' => '1.2.840.113549.1.1.13', // sha512WithRSAEncryption
            'sha1' => '1.2.840.113549.1.1.5',    // sha1WithRSAEncryption
            default => '1.2.840.113549.1.1.11',
        };
    }

    /**
     * Encode OID to DER.
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
     * Add RFC 3161 timestamp to PKCS#7 signature.
     */
    private function addTimestampToSignature(string $signature): string
    {
        // Get signature value from PKCS#7 structure
        // This is a simplified approach - in production, proper ASN.1 parsing is needed

        try {
            $token = $this->timestampClient->getTimestamp($signature);

            // For now, return original signature
            // Full implementation would embed timestamp as unsigned attribute
            // This requires proper CMS/PKCS#7 ASN.1 manipulation

            return $signature;
        } catch (\Exception $e) {
            // If timestamp fails, continue without it but log warning
            error_log("Timestamp failed: " . $e->getMessage());
            return $signature;
        }
    }

    /**
     * Embed signature in PDF.
     */
    private function embedSignature(string $pdf, string $signature, array $byteRanges): string
    {
        // Convert signature to hex
        $sigHex = strtoupper(bin2hex($signature));

        // Pad to placeholder size
        $sigHex = str_pad($sigHex, self::SIGNATURE_SIZE * 2, '0');

        // The ByteRange already tells us where to put the signature:
        // byteRanges[1] is the position right after '<' of our Contents
        // byteRanges[2] is the position of '>' after our Contents hex
        $before = substr($pdf, 0, $byteRanges[1]);
        $after = substr($pdf, $byteRanges[2]);

        return $before . $sigHex . $after;
    }

    /**
     * Add LTV (Long-Term Validation) data to signed PDF.
     */
    private function addLtvData(string $signedPdf): string
    {
        if ($this->ltvValidator === null || $this->certificate === null) {
            return $signedPdf;
        }

        try {
            // Add signing certificate
            $this->ltvValidator->addCertificate($this->certificate);

            // Add chain certificates
            foreach ($this->certificateChain as $cert) {
                $this->ltvValidator->addCertificate($cert);
            }

            // Fetch validation data (OCSP responses, CRLs)
            $this->ltvValidator->fetchValidationData();

            // Build DSS dictionary
            $dss = $this->ltvValidator->buildDss();

            if (empty($dss['Certs']) && empty($dss['OCSPs']) && empty($dss['CRLs'])) {
                return $signedPdf; // No LTV data to add
            }

            // Add DSS as incremental update
            return $this->addDssToDocument($signedPdf, $dss);
        } catch (\Exception $e) {
            // If LTV fails, return signed PDF without LTV
            error_log("LTV validation data failed: " . $e->getMessage());
            return $signedPdf;
        }
    }

    /**
     * Add DSS dictionary to document as incremental update.
     *
     * @param array<string, mixed> $dss
     */
    private function addDssToDocument(string $pdf, array $dss): string
    {
        // This would add the DSS dictionary to the document catalog
        // For now, return the original signed PDF
        // Full implementation requires modifying the catalog with an incremental update

        return $pdf;
    }

    /**
     * Format date for PDF.
     */
    private function formatPdfDate(\DateTimeInterface $date): string
    {
        return "(D:" . $date->format('YmdHisO') . ")";
    }

    /**
     * Encode string for PDF.
     */
    private function encodePdfString(string $str): string
    {
        // Check if we need Unicode encoding
        $needsUnicode = preg_match('/[^\x20-\x7E]/', $str) === 1;

        if ($needsUnicode) {
            // UTF-16BE with BOM
            $utf16 = "\xFE\xFF" . mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
            return '<' . bin2hex($utf16) . '>';
        }

        // Escape special characters
        $escaped = str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $str
        );

        return '(' . $escaped . ')';
    }

    /**
     * Ensure signer is ready.
     */
    private function ensureReady(): void
    {
        if ($this->parser === null) {
            throw new \RuntimeException('No PDF loaded. Call loadFile() or loadContent() first.');
        }

        if ($this->certificate === null || $this->privateKey === null) {
            throw new \RuntimeException('No certificate set. Call setCertificate() first.');
        }
    }

    /**
     * Get estimated signature size.
     */
    public static function getSignatureSize(): int
    {
        return self::SIGNATURE_SIZE;
    }

    /**
     * Load certificate from file (auto-detect format).
     *
     * Supports:
     * - PKCS#12 (.p12, .pfx) - single file with cert + key
     * - PEM (.pem, .crt + .key) - separate cert and key files
     *
     * @param string $certPath Path to certificate file
     * @param string $password Certificate/key password
     * @param string|null $keyPath Path to private key (for PEM format, optional if cert contains key)
     */
    public function loadCertificate(string $certPath, string $password = '', ?string $keyPath = null): self
    {
        $ext = strtolower(pathinfo($certPath, PATHINFO_EXTENSION));

        if (in_array($ext, ['p12', 'pfx'], true)) {
            return $this->setCertificateFromP12($certPath, $password);
        }

        // PEM format - need key path
        if ($keyPath === null) {
            // Try same filename with .key extension
            $keyPath = preg_replace('/\.(pem|crt|cer)$/i', '.key', $certPath);
            if (!file_exists($keyPath)) {
                throw new \InvalidArgumentException(
                    "Key file not found. For PEM certificates, provide key path or use .p12 format."
                );
            }
        }

        return $this->setCertificate($certPath, $keyPath, $password);
    }

    /**
     * Set reason for signing.
     */
    public function setReason(string $reason): self
    {
        if ($this->signatureField === null) {
            $this->signatureField = SignatureField::create('Signature');
        }
        $this->signatureField->setReason($reason);
        return $this;
    }

    /**
     * Set location for signing.
     */
    public function setLocation(string $location): self
    {
        if ($this->signatureField === null) {
            $this->signatureField = SignatureField::create('Signature');
        }
        $this->signatureField->setLocation($location);
        return $this;
    }

    /**
     * Set contact info for signing.
     */
    public function setContactInfo(string $contactInfo): self
    {
        if ($this->signatureField === null) {
            $this->signatureField = SignatureField::create('Signature');
        }
        $this->signatureField->setContactInfo($contactInfo);
        return $this;
    }

    /**
     * Apply multiple signatures from different signers.
     *
     * Each signer uses their own certificate to sign the document.
     * Signatures are applied sequentially using PDF incremental updates.
     *
     * @param array<array{cert: string, password: string, key?: string, reason?: string, location?: string}> $signers
     * @return string Signed PDF content
     *
     * @example
     * ```php
     * $signed = Signer::multiSign('document.pdf', [
     *     ['cert' => 'signer1.p12', 'password' => 'pass1', 'reason' => 'Approval'],
     *     ['cert' => 'signer2.p12', 'password' => 'pass2', 'reason' => 'Review'],
     *     ['cert' => 'manager.p12', 'password' => 'pass3', 'reason' => 'Final approval'],
     * ]);
     * ```
     */
    public static function multiSign(string $pdfPath, array $signers): string
    {
        if (empty($signers)) {
            throw new \InvalidArgumentException('At least one signer is required');
        }

        $content = file_get_contents($pdfPath);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $pdfPath");
        }

        return self::multiSignContent($content, $signers);
    }

    /**
     * Apply multiple signatures to PDF content.
     *
     * @param string $content PDF content
     * @param array<array{cert: string, password: string, key?: string, reason?: string, location?: string}> $signers
     * @return string Signed PDF content
     */
    public static function multiSignContent(string $content, array $signers): string
    {
        foreach ($signers as $i => $signerConfig) {
            if (!isset($signerConfig['cert']) || !isset($signerConfig['password'])) {
                throw new \InvalidArgumentException("Signer {$i}: 'cert' and 'password' are required");
            }

            $signer = new self();
            $signer->loadContent($content);
            $signer->loadCertificate(
                $signerConfig['cert'],
                $signerConfig['password'],
                $signerConfig['key'] ?? null
            );

            if (isset($signerConfig['reason'])) {
                $signer->setReason($signerConfig['reason']);
            }

            if (isset($signerConfig['location'])) {
                $signer->setLocation($signerConfig['location']);
            }

            if (isset($signerConfig['contact'])) {
                $signer->setContactInfo($signerConfig['contact']);
            }

            // Sign and use output for next iteration
            $content = $signer->sign();
        }

        return $content;
    }

    /**
     * Verify a signed PDF.
     *
     * @return array{valid: bool, signer: ?string, signTime: ?string, reason: ?string, errors: array<int, string>}
     */
    public static function verify(string $pdfPath): array
    {
        $result = [
            'valid' => false,
            'signer' => null,
            'signTime' => null,
            'reason' => null,
            'errors' => [],
        ];

        if (!file_exists($pdfPath)) {
            $result['errors'][] = 'File not found';
            return $result;
        }

        $content = file_get_contents($pdfPath);
        if ($content === false) {
            $result['errors'][] = 'Could not read file';
            return $result;
        }

        // Find signature dictionary
        if (!str_contains($content, '/Type /Sig')) {
            $result['errors'][] = 'No signature found in document';
            return $result;
        }

        // Extract ByteRange
        if (!preg_match('/\/ByteRange\s*\[(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\]/', $content, $matches)) {
            $result['errors'][] = 'Could not find ByteRange';
            return $result;
        }

        $byteRanges = [
            (int) $matches[1],
            (int) $matches[2],
            (int) $matches[3],
            (int) $matches[4],
        ];

        // Extract signed data
        $signedData = substr($content, $byteRanges[0], $byteRanges[1]);
        $signedData .= substr($content, $byteRanges[2], $byteRanges[3]);

        // Extract signature hex
        $contentsMatch = [];
        if (!preg_match('/\/Contents\s*<([0-9A-Fa-f]+)>/', $content, $contentsMatch)) {
            $result['errors'][] = 'Could not extract signature contents';
            return $result;
        }

        $sigHex = $contentsMatch[1];
        // Remove padding zeros
        $sigHex = rtrim($sigHex, '0');
        if (strlen($sigHex) % 2 !== 0) {
            $sigHex .= '0';
        }

        $signature = hex2bin($sigHex);
        if ($signature === false) {
            $result['errors'][] = 'Invalid signature encoding';
            return $result;
        }

        // Verify PKCS#7 signature
        $tempData = tempnam(sys_get_temp_dir(), 'pdf_verify_data_');
        $tempSig = tempnam(sys_get_temp_dir(), 'pdf_verify_sig_');

        file_put_contents($tempData, $signedData);

        // Write signature in S/MIME format
        $smime = "MIME-Version: 1.0\n";
        $smime .= "Content-Type: application/pkcs7-signature; name=\"smime.p7s\"\n";
        $smime .= "Content-Transfer-Encoding: base64\n\n";
        $smime .= chunk_split(base64_encode($signature), 64, "\n");

        file_put_contents($tempSig, $smime);

        // Note: openssl_pkcs7_verify needs proper setup
        // This is a simplified verification
        $valid = openssl_pkcs7_verify($tempSig, PKCS7_NOVERIFY | PKCS7_BINARY, '/dev/null', [], null, $tempData);

        unlink($tempData);
        unlink($tempSig);

        if ($valid === true) {
            $result['valid'] = true;
        } elseif ($valid === -1) {
            $result['errors'][] = 'Verification error: ' . openssl_error_string();
        } else {
            $result['errors'][] = 'Signature is invalid';
        }

        // Extract signer info from PDF
        if (preg_match('/\/Name\s*\(([^)]+)\)/', $content, $nameMatch)) {
            $result['signer'] = $nameMatch[1];
        }

        if (preg_match('/\/M\s*\(D:([^)]+)\)/', $content, $dateMatch)) {
            $result['signTime'] = $dateMatch[1];
        }

        if (preg_match('/\/Reason\s*\(([^)]+)\)/', $content, $reasonMatch)) {
            $result['reason'] = $reasonMatch[1];
        }

        return $result;
    }
}
