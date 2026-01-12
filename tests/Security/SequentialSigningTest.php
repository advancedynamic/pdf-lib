<?php

declare(strict_types=1);

namespace PdfLib\Tests\Security;

use PHPUnit\Framework\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use PdfLib\Security\Signature\Signer;
use PdfLib\Security\Signature\SignatureField;

/**
 * Sequential Digital Signature Workflow Test
 *
 * Tests the workflow:
 * 1. User 1 signs the original document
 * 2. User 2 receives the signed doc, adds their signature
 * 3. User 3 receives the doc with 2 signatures, adds theirs
 *
 * Each signature is preserved using PDF incremental updates (PDF standard).
 */
class SequentialSigningTest extends TestCase
{
    private static string $outputDir;

    /** @var array{cert: string, key: string} */
    private static array $user1Cert;
    /** @var array{cert: string, key: string} */
    private static array $user2Cert;
    /** @var array{cert: string, key: string} */
    private static array $user3Cert;

    private static string $originalContent;

    public static function setUpBeforeClass(): void
    {
        self::$outputDir = __DIR__ . '/../target';
        if (!is_dir(self::$outputDir)) {
            mkdir(self::$outputDir, 0755, true);
        }

        // Create test certificates for 3 users
        self::$user1Cert = self::createTestCertificate('User 1 - Department Head', 'user1@company.com');
        self::$user2Cert = self::createTestCertificate('User 2 - Legal Review', 'user2@company.com');
        self::$user3Cert = self::createTestCertificate('User 3 - CEO', 'user3@company.com');

        // Create original document
        self::$originalContent = self::createOriginalDocument();
    }

    /**
     * Create a test certificate.
     *
     * @return array{cert: string, key: string}
     */
    private static function createTestCertificate(string $name, string $email): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $privateKey = openssl_pkey_new($config);
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'California',
            'localityName' => 'San Francisco',
            'organizationName' => 'Test Company',
            'commonName' => $name,
            'emailAddress' => $email,
        ];

        $csr = openssl_csr_new($dn, $privateKey, $config);
        $cert = openssl_csr_sign($csr, null, $privateKey, 365, $config);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($privateKey, $keyPem);

        return ['cert' => $certPem, 'key' => $keyPem];
    }

    /**
     * Create the original document to be signed.
     */
    private static function createOriginalDocument(): string
    {
        $document = PdfDocument::create();
        $document->setTitle('Contract Agreement');
        $document->setAuthor('Company Inc.');

        $page = new Page(PageSize::a4());
        $page->addText('Contract Agreement', 100, 750, ['fontSize' => 24]);
        $page->addText('This document requires approval from three parties:', 100, 700, ['fontSize' => 12]);
        $page->addText('1. Department Head', 120, 680, ['fontSize' => 12]);
        $page->addText('2. Legal Review', 120, 660, ['fontSize' => 12]);
        $page->addText('3. CEO Final Approval', 120, 640, ['fontSize' => 12]);
        $page->addText('', 100, 600);
        $page->addText('Terms and conditions of the agreement...', 100, 580, ['fontSize' => 12]);
        $page->addText('User 1 Signature:', 100, 500, ['fontSize' => 12]);
        $page->addText('User 2 Signature:', 100, 380, ['fontSize' => 12]);
        $page->addText('User 3 Signature:', 100, 260, ['fontSize' => 12]);

        $document->addPageObject($page);
        $content = $document->render();

        // Save original unsigned document
        file_put_contents(self::$outputDir . '/00_original_unsigned.pdf', $content);

        return $content;
    }

    public function testOriginalDocumentIsCreated(): void
    {
        $this->assertNotEmpty(self::$originalContent);
        $this->assertStringStartsWith('%PDF-', self::$originalContent);

        $filePath = self::$outputDir . '/00_original_unsigned.pdf';
        $this->assertFileExists($filePath);
    }

    public function testUser1Signs(): string
    {
        $field1 = SignatureField::create('User1_Signature')
            ->setPosition(100, 420)
            ->setSize(200, 50)
            ->setSignerName('User 1 - Department Head')
            ->setReason('Department Head Approval')
            ->setLocation('New York Office');

        $signer1 = new Signer();
        $signedByUser1 = $signer1
            ->loadContent(self::$originalContent)
            ->setCertificateFromPem(self::$user1Cert['cert'], self::$user1Cert['key'])
            ->setSignatureField($field1)
            ->sign();

        $this->assertNotEmpty($signedByUser1);
        $this->assertStringStartsWith('%PDF-', $signedByUser1);

        // Verify it has 1 signature
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $signedByUser1);
        $this->assertSame(1, $sigCount, 'Document should have 1 signature');

        // Verify signer name is present
        $this->assertStringContainsString('User 1 - Department Head', $signedByUser1);

        // Save signed document
        $outputPath = self::$outputDir . '/01_signed_by_user1.pdf';
        file_put_contents($outputPath, $signedByUser1);
        $this->assertFileExists($outputPath);

        return $signedByUser1;
    }

    /**
     * @depends testUser1Signs
     */
    public function testUser2Signs(string $documentFromUser1): string
    {
        $field2 = SignatureField::create('User2_Signature')
            ->setPosition(100, 300)
            ->setSize(200, 50)
            ->setSignerName('User 2 - Legal Review')
            ->setReason('Legal Review Complete')
            ->setLocation('Chicago Office');

        $signer2 = new Signer();
        $signedByUser1And2 = $signer2
            ->loadContent($documentFromUser1)
            ->setCertificateFromPem(self::$user2Cert['cert'], self::$user2Cert['key'])
            ->setSignatureField($field2)
            ->sign();

        $this->assertNotEmpty($signedByUser1And2);
        $this->assertStringStartsWith('%PDF-', $signedByUser1And2);

        // Verify it has 2 signatures
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $signedByUser1And2);
        $this->assertSame(2, $sigCount, 'Document should have 2 signatures');

        // Verify both signer names are present
        $this->assertStringContainsString('User 1 - Department Head', $signedByUser1And2);
        $this->assertStringContainsString('User 2 - Legal Review', $signedByUser1And2);

        // Save signed document
        $outputPath = self::$outputDir . '/02_signed_by_user1_user2.pdf';
        file_put_contents($outputPath, $signedByUser1And2);
        $this->assertFileExists($outputPath);

        return $signedByUser1And2;
    }

    /**
     * @depends testUser2Signs
     */
    public function testUser3Signs(string $documentFromUser2): string
    {
        $field3 = SignatureField::create('User3_Signature')
            ->setPosition(100, 180)
            ->setSize(200, 50)
            ->setSignerName('User 3 - CEO')
            ->setReason('CEO Final Approval')
            ->setLocation('Los Angeles HQ');

        $signer3 = new Signer();
        $fullySignedPdf = $signer3
            ->loadContent($documentFromUser2)
            ->setCertificateFromPem(self::$user3Cert['cert'], self::$user3Cert['key'])
            ->setSignatureField($field3)
            ->sign();

        $this->assertNotEmpty($fullySignedPdf);
        $this->assertStringStartsWith('%PDF-', $fullySignedPdf);

        // Verify it has 3 signatures
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $fullySignedPdf);
        $this->assertSame(3, $sigCount, 'Document should have 3 signatures');

        // Verify all signer names are present
        $this->assertStringContainsString('User 1 - Department Head', $fullySignedPdf);
        $this->assertStringContainsString('User 2 - Legal Review', $fullySignedPdf);
        $this->assertStringContainsString('User 3 - CEO', $fullySignedPdf);

        // Save fully signed document
        $outputPath = self::$outputDir . '/03_fully_signed.pdf';
        file_put_contents($outputPath, $fullySignedPdf);
        $this->assertFileExists($outputPath);

        return $fullySignedPdf;
    }

    /**
     * @depends testUser3Signs
     */
    public function testFullySignedDocumentIsValid(string $fullySignedPdf): void
    {
        // Verify the document has valid PDF structure
        $this->assertStringStartsWith('%PDF-', $fullySignedPdf);
        $this->assertStringContainsString('%%EOF', $fullySignedPdf);

        // Verify signature dictionary entries
        $this->assertStringContainsString('/Type /Sig', $fullySignedPdf);
        $this->assertStringContainsString('/Filter /Adobe.PPKLite', $fullySignedPdf);
        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $fullySignedPdf);

        // Verify reasons are present
        $this->assertStringContainsString('Department Head Approval', $fullySignedPdf);
        $this->assertStringContainsString('Legal Review Complete', $fullySignedPdf);
        $this->assertStringContainsString('CEO Final Approval', $fullySignedPdf);

        // Verify locations are present
        $this->assertStringContainsString('New York Office', $fullySignedPdf);
        $this->assertStringContainsString('Chicago Office', $fullySignedPdf);
        $this->assertStringContainsString('Los Angeles HQ', $fullySignedPdf);
    }

    public function testMultiSignContent(): void
    {
        // Create temp files for certificates
        $tempDir = sys_get_temp_dir() . '/pdf-multi-sign-test';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        file_put_contents($tempDir . '/user1.pem', self::$user1Cert['cert']);
        file_put_contents($tempDir . '/user1_key.pem', self::$user1Cert['key']);
        file_put_contents($tempDir . '/user2.pem', self::$user2Cert['cert']);
        file_put_contents($tempDir . '/user2_key.pem', self::$user2Cert['key']);
        file_put_contents($tempDir . '/user3.pem', self::$user3Cert['cert']);
        file_put_contents($tempDir . '/user3_key.pem', self::$user3Cert['key']);

        // Apply all signatures in one call
        $multiSignedContent = Signer::multiSignContent(self::$originalContent, [
            [
                'cert' => $tempDir . '/user1.pem',
                'password' => '',
                'key' => $tempDir . '/user1_key.pem',
                'reason' => 'Department Head Approval',
                'location' => 'New York',
            ],
            [
                'cert' => $tempDir . '/user2.pem',
                'password' => '',
                'key' => $tempDir . '/user2_key.pem',
                'reason' => 'Legal Review Complete',
                'location' => 'Chicago',
            ],
            [
                'cert' => $tempDir . '/user3.pem',
                'password' => '',
                'key' => $tempDir . '/user3_key.pem',
                'reason' => 'CEO Final Approval',
                'location' => 'Los Angeles',
                'contact' => 'ceo@company.com',
            ],
        ]);

        $this->assertNotEmpty($multiSignedContent);
        $this->assertStringStartsWith('%PDF-', $multiSignedContent);

        // Verify it has 3 signatures
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $multiSignedContent);
        $this->assertSame(3, $sigCount, 'Multi-signed document should have 3 signatures');

        // Save multi-signed document
        $outputPath = self::$outputDir . '/04_multi_signed.pdf';
        file_put_contents($outputPath, $multiSignedContent);
        $this->assertFileExists($outputPath);

        // Clean up temp files
        array_map('unlink', glob($tempDir . '/*'));
        rmdir($tempDir);
    }

    public function testSignatureFieldsHaveUniqueNames(): void
    {
        // Sign document with first signature
        $field1 = SignatureField::create('Sig1')
            ->setPosition(100, 500)
            ->setSize(150, 40);

        $signer1 = new Signer();
        $signed1 = $signer1
            ->loadContent(self::$originalContent)
            ->setCertificateFromPem(self::$user1Cert['cert'], self::$user1Cert['key'])
            ->setSignatureField($field1)
            ->sign();

        // Sign with second signature - different field name
        $field2 = SignatureField::create('Sig2')
            ->setPosition(100, 400)
            ->setSize(150, 40);

        $signer2 = new Signer();
        $signed2 = $signer2
            ->loadContent($signed1)
            ->setCertificateFromPem(self::$user2Cert['cert'], self::$user2Cert['key'])
            ->setSignatureField($field2)
            ->sign();

        // Both signature field names should be present
        $this->assertStringContainsString('Sig1', $signed2);
        $this->assertStringContainsString('Sig2', $signed2);

        // Should have 2 signatures
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $signed2);
        $this->assertSame(2, $sigCount);
    }

    public function testIncrementalUpdatePreservesOriginalContent(): void
    {
        // Get size of original
        $originalSize = strlen(self::$originalContent);

        // Sign document
        $field = SignatureField::create('TestSig')
            ->setPosition(100, 500)
            ->setSize(150, 40);

        $signer = new Signer();
        $signed = $signer
            ->loadContent(self::$originalContent)
            ->setCertificateFromPem(self::$user1Cert['cert'], self::$user1Cert['key'])
            ->setSignatureField($field)
            ->sign();

        // Signed document should be larger (original + incremental update)
        $signedSize = strlen($signed);
        $this->assertGreaterThan($originalSize, $signedSize);

        // Original content should be preserved at the start
        $originalWithoutEof = rtrim(self::$originalContent);
        $originalWithoutEof = preg_replace('/%%EOF\s*$/', '', $originalWithoutEof);
        $this->assertStringStartsWith(trim($originalWithoutEof), $signed);
    }

    public static function tearDownAfterClass(): void
    {
        // Output summary of generated files
        $files = glob(self::$outputDir . '/*.pdf');
        if ($files) {
            echo "\n\nGenerated PDF files in " . self::$outputDir . ":\n";
            foreach ($files as $file) {
                echo "  - " . basename($file) . " (" . number_format(filesize($file)) . " bytes)\n";
            }
        }
    }
}
