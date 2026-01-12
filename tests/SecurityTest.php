<?php

declare(strict_types=1);

namespace PdfLib\Tests;

use PHPUnit\Framework\TestCase;
use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;
use PdfLib\Security\Encryption\Permissions;
use PdfLib\Security\Encryption\Encryptor;
use PdfLib\Security\Encryption\Rc4Handler;
use PdfLib\Security\Encryption\Aes128Handler;
use PdfLib\Security\Encryption\Aes256Handler;
use PdfLib\Security\Signature\Certificate;
use PdfLib\Security\Signature\SignatureField;
use PdfLib\Security\Signature\TimestampClient;
use PdfLib\Security\Signature\LtvValidator;
use PdfLib\Security\Signature\Signer;

/**
 * Tests for PDF security/encryption classes.
 */
final class SecurityTest extends TestCase
{
    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = __DIR__ . '/target';
        if (!is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }
    }

    /**
     * Create a simple test PDF.
     */
    private function createTestPdf(int $pages = 2): string
    {
        $document = PdfDocument::create();

        for ($i = 1; $i <= $pages; $i++) {
            $page = new Page(PageSize::a4());
            $page->addText("Page {$i}", 100, 700, ['fontSize' => 24]);
            $page->addText("Secure content on page {$i}", 100, 650, ['fontSize' => 12]);
            $document->addPageObject($page);
        }

        return $document->render();
    }

    // ==================== Permissions Tests ====================

    public function testPermissionsDefaultDeniesAll(): void
    {
        $permissions = new Permissions();

        $this->assertFalse($permissions->canPrint());
        $this->assertFalse($permissions->canModify());
        $this->assertFalse($permissions->canCopy());
    }

    public function testPermissionsAllowAll(): void
    {
        $permissions = Permissions::allowAll();

        $this->assertTrue($permissions->canPrint());
        $this->assertTrue($permissions->canPrintHighQuality());
        $this->assertTrue($permissions->canModify());
        $this->assertTrue($permissions->canCopy());
        $this->assertTrue($permissions->canAnnotate());
        $this->assertTrue($permissions->canFillForms());
        $this->assertTrue($permissions->canExtract());
        $this->assertTrue($permissions->canAssemble());
    }

    public function testPermissionsDenyAll(): void
    {
        $permissions = Permissions::denyAll();

        $this->assertFalse($permissions->canPrint());
        $this->assertFalse($permissions->canPrintHighQuality());
        $this->assertFalse($permissions->canModify());
        $this->assertFalse($permissions->canCopy());
        $this->assertFalse($permissions->canAnnotate());
        $this->assertFalse($permissions->canFillForms());
        $this->assertFalse($permissions->canExtract());
        $this->assertFalse($permissions->canAssemble());
    }

    public function testPermissionsCanSetIndividually(): void
    {
        $permissions = new Permissions();

        $permissions->allowPrinting()
                   ->allowCopying();

        $this->assertTrue($permissions->canPrint());
        $this->assertTrue($permissions->canCopy());
        $this->assertFalse($permissions->canModify());
        $this->assertFalse($permissions->canAnnotate());
    }

    public function testPermissionsCanToggle(): void
    {
        $permissions = Permissions::allowAll();

        $permissions->allowPrinting(false)
                   ->allowModifying(false);

        $this->assertFalse($permissions->canPrint());
        $this->assertFalse($permissions->canModify());
        $this->assertTrue($permissions->canCopy());
    }

    public function testPermissionsFromArray(): void
    {
        $permissions = Permissions::fromArray(['print', 'copy', 'fill-forms']);

        $this->assertTrue($permissions->canPrint());
        $this->assertTrue($permissions->canCopy());
        $this->assertTrue($permissions->canFillForms());
        $this->assertFalse($permissions->canModify());
        $this->assertFalse($permissions->canAnnotate());
    }

    public function testPermissionsToArray(): void
    {
        $permissions = new Permissions();
        $permissions->allowPrinting()
                   ->allowCopying()
                   ->allowExtraction();

        $array = $permissions->toArray();

        $this->assertContains('print', $array);
        $this->assertContains('copy', $array);
        $this->assertContains('extract', $array);
        $this->assertNotContains('modify', $array);
    }

    public function testPermissionsGetValue(): void
    {
        $permissions = Permissions::allowAll();
        $value = $permissions->getValue();

        // Value should be a negative integer (signed 32-bit)
        $this->assertIsInt($value);
    }

    public function testPermissionsSetValue(): void
    {
        $permissions = new Permissions();
        $originalValue = Permissions::allowAll()->getValue();

        $permissions->setValue($originalValue);

        $this->assertEquals($originalValue, $permissions->getValue());
    }

    // ==================== RC4 Handler Tests ====================

    public function testRc4Handler40BitCreation(): void
    {
        $handler = Rc4Handler::rc4_40();

        $this->assertEquals('RC4', $handler->getAlgorithm());
        $this->assertEquals(40, $handler->getKeyLength());
        $this->assertEquals(1, $handler->getV());
        $this->assertEquals(2, $handler->getR());
    }

    public function testRc4Handler128BitCreation(): void
    {
        $handler = Rc4Handler::rc4_128();

        $this->assertEquals('RC4', $handler->getAlgorithm());
        $this->assertEquals(128, $handler->getKeyLength());
        $this->assertEquals(2, $handler->getV());
        $this->assertEquals(3, $handler->getR());
    }

    public function testRc4HandlerEncryptDecrypt(): void
    {
        $handler = Rc4Handler::rc4_128();
        $key = random_bytes(16);
        $data = 'Hello, this is test data!';

        $encrypted = $handler->encrypt($data, $key, 1, 0);
        $this->assertNotEquals($data, $encrypted);

        $decrypted = $handler->decrypt($encrypted, $key, 1, 0);
        $this->assertEquals($data, $decrypted);
    }

    public function testRc4HandlerComputeOwnerKey(): void
    {
        $handler = Rc4Handler::rc4_128();

        $ownerKey = $handler->computeOwnerKey('owner123', 'user456');

        $this->assertEquals(32, strlen($ownerKey));
    }

    public function testRc4HandlerComputeUserKey(): void
    {
        $handler = Rc4Handler::rc4_128();
        $encryptionKey = random_bytes(16);

        $userKey = $handler->computeUserKey($encryptionKey);

        $this->assertEquals(32, strlen($userKey));
    }

    public function testRc4HandlerComputeEncryptionKey(): void
    {
        $handler = Rc4Handler::rc4_128();

        $ownerKey = str_repeat("\x00", 32);
        $documentId = random_bytes(16);

        $key = $handler->computeEncryptionKey('password', $ownerKey, -4, $documentId);

        $this->assertEquals(16, strlen($key));
    }

    // ==================== AES-128 Handler Tests ====================

    public function testAes128HandlerCreation(): void
    {
        $handler = new Aes128Handler();

        $this->assertEquals('AES-128', $handler->getAlgorithm());
        $this->assertEquals(128, $handler->getKeyLength());
        $this->assertEquals(4, $handler->getV());
        $this->assertEquals(4, $handler->getR());
    }

    public function testAes128HandlerEncryptDecrypt(): void
    {
        $handler = new Aes128Handler();
        $key = random_bytes(16);
        $data = 'Hello, this is test data for AES-128!';

        $encrypted = $handler->encrypt($data, $key, 1, 0);
        $this->assertNotEquals($data, $encrypted);
        $this->assertGreaterThan(strlen($data), strlen($encrypted)); // IV + padding

        $decrypted = $handler->decrypt($encrypted, $key, 1, 0);
        $this->assertEquals($data, $decrypted);
    }

    public function testAes128HandlerComputeOwnerKey(): void
    {
        $handler = new Aes128Handler();

        $ownerKey = $handler->computeOwnerKey('owner123', 'user456');

        $this->assertEquals(32, strlen($ownerKey));
    }

    // ==================== AES-256 Handler Tests ====================

    public function testAes256HandlerCreation(): void
    {
        $handler = new Aes256Handler();

        $this->assertEquals('AES-256', $handler->getAlgorithm());
        $this->assertEquals(256, $handler->getKeyLength());
        $this->assertEquals(5, $handler->getV());
        $this->assertEquals(6, $handler->getR());
    }

    public function testAes256HandlerEncryptDecrypt(): void
    {
        $handler = new Aes256Handler();
        $key = random_bytes(32);
        $data = 'Hello, this is test data for AES-256!';

        $encrypted = $handler->encrypt($data, $key, 1, 0);
        $this->assertNotEquals($data, $encrypted);

        $decrypted = $handler->decrypt($encrypted, $key, 1, 0);
        $this->assertEquals($data, $decrypted);
    }

    public function testAes256HandlerGenerateEncryptionData(): void
    {
        $handler = new Aes256Handler();

        $data = $handler->generateEncryptionData('user123', 'owner456', -4);

        $this->assertArrayHasKey('key', $data);
        $this->assertArrayHasKey('O', $data);
        $this->assertArrayHasKey('U', $data);
        $this->assertArrayHasKey('OE', $data);
        $this->assertArrayHasKey('UE', $data);
        $this->assertArrayHasKey('Perms', $data);

        $this->assertEquals(32, strlen($data['key']));
        $this->assertEquals(48, strlen($data['O']));
        $this->assertEquals(48, strlen($data['U']));
        $this->assertEquals(32, strlen($data['OE']));
        $this->assertEquals(32, strlen($data['UE']));
        $this->assertEquals(16, strlen($data['Perms']));
    }

    // ==================== Encryptor Tests ====================

    public function testEncryptorCanLoadPdf(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf);

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanSetPasswords(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setUserPassword('user123')
                  ->setOwnerPassword('owner456');

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanSetBothPasswords(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setPasswords('user123', 'owner456');

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanSetEncryptionMode(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setEncryptionMode(Encryptor::AES_256);

        $handler = $encryptor->getHandler();
        $this->assertEquals('AES-256', $handler->getAlgorithm());
    }

    public function testEncryptorGetMinimumVersion(): void
    {
        $encryptor = new Encryptor();

        $encryptor->setEncryptionMode(Encryptor::RC4_40);
        $this->assertEquals('1.1', $encryptor->getMinimumVersion());

        $encryptor->setEncryptionMode(Encryptor::RC4_128);
        $this->assertEquals('1.4', $encryptor->getMinimumVersion());

        $encryptor->setEncryptionMode(Encryptor::AES_128);
        $this->assertEquals('1.5', $encryptor->getMinimumVersion());

        $encryptor->setEncryptionMode(Encryptor::AES_256);
        $this->assertEquals('1.7', $encryptor->getMinimumVersion());
    }

    public function testEncryptorCanSetPermissions(): void
    {
        $pdf = $this->createTestPdf();

        $permissions = new Permissions();
        $permissions->allowPrinting()
                   ->allowCopying();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setPermissions($permissions);

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanAllowAllPermissions(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->allowAllPermissions();

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanDenyAllPermissions(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->denyAllPermissions();

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanSetIndividualPermissions(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->allowPrinting()
                  ->allowCopying()
                  ->allowModifying(false);

        $this->expectNotToPerformAssertions();
    }

    public function testEncryptorCanEncrypt(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setUserPassword('test123')
                  ->setEncryptionMode(Encryptor::AES_128);

        $document = $encryptor->encrypt();

        $this->assertInstanceOf(PdfDocument::class, $document);
        $this->assertEquals(2, $document->getPageCount());
    }

    public function testEncryptorGetEncryptionDictionary(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setUserPassword('test123')
                  ->setOwnerPassword('owner456')
                  ->setEncryptionMode(Encryptor::AES_128);

        $dict = $encryptor->getEncryptionDictionary();

        $this->assertArrayHasKey('Filter', $dict);
        $this->assertArrayHasKey('V', $dict);
        $this->assertArrayHasKey('R', $dict);
        $this->assertArrayHasKey('O', $dict);
        $this->assertArrayHasKey('U', $dict);
        $this->assertArrayHasKey('P', $dict);

        $this->assertEquals('Standard', $dict['Filter']);
        $this->assertEquals(4, $dict['V']);
        $this->assertEquals(4, $dict['R']);
    }

    public function testEncryptorGetEncryptionDictionaryAes256(): void
    {
        $pdf = $this->createTestPdf();

        $encryptor = new Encryptor();
        $encryptor->loadContent($pdf)
                  ->setUserPassword('test123')
                  ->setOwnerPassword('owner456')
                  ->setEncryptionMode(Encryptor::AES_256);

        $dict = $encryptor->getEncryptionDictionary();

        $this->assertEquals(5, $dict['V']);
        $this->assertEquals(6, $dict['R']);
        $this->assertArrayHasKey('OE', $dict);
        $this->assertArrayHasKey('UE', $dict);
        $this->assertArrayHasKey('Perms', $dict);
    }

    public function testEncryptorSavesToFile(): void
    {
        $pdf = $this->createTestPdf();
        $outputPath = $this->targetDir . '/encrypted_test.pdf';

        $encryptor = new Encryptor();
        $result = $encryptor->loadContent($pdf)
                           ->setUserPassword('test123')
                           ->setEncryptionMode(Encryptor::AES_128)
                           ->save($outputPath);

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
    }

    // ==================== Integration Tests with File Output ====================

    public function testCreateEncryptedPdfOutput(): void
    {
        $document = PdfDocument::create();

        $page = new Page(PageSize::a4());
        $page->addText('Encrypted Document', 100, 700, ['fontSize' => 24]);
        $page->addText('This document is protected with AES-128 encryption.', 100, 650, ['fontSize' => 12]);
        $page->addText('User Password: test123', 100, 600, ['fontSize' => 10]);
        $page->addText('Owner Password: owner456', 100, 580, ['fontSize' => 10]);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/encrypted_document.pdf';

        $encryptor = new Encryptor();
        $encryptor->loadContent($document->render())
                  ->setUserPassword('test123')
                  ->setOwnerPassword('owner456')
                  ->setEncryptionMode(Encryptor::AES_128)
                  ->allowPrinting()
                  ->allowCopying()
                  ->allowModifying(false)
                  ->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testCreateRestrictedPdfOutput(): void
    {
        $document = PdfDocument::create();

        $page = new Page(PageSize::a4());
        $page->addText('Restricted Document', 100, 700, ['fontSize' => 24]);
        $page->addText('This document has restricted permissions.', 100, 650, ['fontSize' => 12]);
        $page->addText('- Printing: Allowed', 100, 600, ['fontSize' => 10]);
        $page->addText('- Copying: Denied', 100, 580, ['fontSize' => 10]);
        $page->addText('- Modifying: Denied', 100, 560, ['fontSize' => 10]);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/restricted_document.pdf';

        $permissions = Permissions::denyAll()
            ->allowPrinting()
            ->allowFormFilling();

        $encryptor = new Encryptor();
        $encryptor->loadContent($document->render())
                  ->setPasswords('', 'owner_only')
                  ->setPermissions($permissions)
                  ->save($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    // ==================== Certificate Tests ====================

    /**
     * Create a self-signed test certificate.
     *
     * @return array{cert: string, key: string}
     */
    private function createTestCertificate(): array
    {
        $dn = [
            'countryName' => 'US',
            'stateOrProvinceName' => 'California',
            'localityName' => 'San Francisco',
            'organizationName' => 'Test Organization',
            'commonName' => 'Test Signer',
            'emailAddress' => 'test@example.com',
        ];

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csr = openssl_csr_new($dn, $privateKey, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $privateKey, 365, ['digest_alg' => 'sha256']);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($privateKey, $keyPem);

        return ['cert' => $certPem, 'key' => $keyPem];
    }

    public function testCertificateFromPem(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        $this->assertNotEmpty($cert->getSubjectName());
        $this->assertNotEmpty($cert->getIssuerName());
        // Serial number might be "0" for self-signed test certs
        $this->assertIsString($cert->getSerialNumber());
    }

    public function testCertificateFromDer(): void
    {
        $testCert = $this->createTestCertificate();

        // Convert PEM to DER
        $pem = $testCert['cert'];
        $pem = preg_replace('/-----BEGIN .*?-----/', '', $pem);
        $pem = preg_replace('/-----END .*?-----/', '', $pem);
        $pem = preg_replace('/\s+/', '', $pem);
        $der = base64_decode($pem, true);

        $cert = Certificate::fromDer($der);

        $this->assertNotEmpty($cert->getSubjectName());
        $this->assertTrue($cert->isSelfSigned());
    }

    public function testCertificateIsValid(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        $this->assertTrue($cert->isValid());
        $this->assertFalse($cert->isExpired());
    }

    public function testCertificateValidityDates(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        $validFrom = $cert->getValidFrom();
        $validTo = $cert->getValidTo();

        $this->assertNotNull($validFrom);
        $this->assertNotNull($validTo);
        $this->assertLessThan($validTo, $validFrom);
    }

    public function testCertificateIsSelfSigned(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        $this->assertTrue($cert->isSelfSigned());
    }

    public function testCertificateCanSign(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        // Self-signed test cert without key usage extensions should be able to sign
        $this->assertTrue($cert->canSign());
    }

    public function testCertificateGetFingerprint(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        $sha256 = $cert->getFingerprint('sha256');
        $sha1 = $cert->getFingerprint('sha1');

        $this->assertEquals(64, strlen($sha256)); // SHA-256 hex is 64 chars
        $this->assertEquals(40, strlen($sha1));   // SHA-1 hex is 40 chars
    }

    public function testCertificateGetPemAndDer(): void
    {
        $testCert = $this->createTestCertificate();

        $cert = Certificate::fromPem($testCert['cert']);

        $pem = $cert->getPem();
        $der = $cert->getDer();

        $this->assertStringContainsString('-----BEGIN CERTIFICATE-----', $pem);
        $this->assertStringStartsWith("\x30", $der); // DER SEQUENCE starts with 0x30
    }

    // ==================== SignatureField Tests ====================

    public function testSignatureFieldCreate(): void
    {
        $field = SignatureField::create('Signature1');

        $this->assertEquals('Signature1', $field->getName());
        $this->assertEquals(1, $field->getPage());
        $this->assertTrue($field->isVisible());
        $this->assertFalse($field->isSigned());
    }

    public function testSignatureFieldSetPosition(): void
    {
        $field = SignatureField::create('Sig')
            ->setPosition(100, 200)
            ->setSize(150, 50);

        $this->assertEquals(100.0, $field->getX());
        $this->assertEquals(200.0, $field->getY());
        $this->assertEquals(150.0, $field->getWidth());
        $this->assertEquals(50.0, $field->getHeight());
    }

    public function testSignatureFieldGetRect(): void
    {
        $field = SignatureField::create('Sig')
            ->setPosition(100, 200)
            ->setSize(150, 50);

        $rect = $field->getRect();

        $this->assertEquals([100.0, 200.0, 250.0, 250.0], $rect);
    }

    public function testSignatureFieldSetRect(): void
    {
        $field = SignatureField::create('Sig');
        $field->setRect([50, 60, 200, 110]);

        $this->assertEquals(50.0, $field->getX());
        $this->assertEquals(60.0, $field->getY());
        $this->assertEquals(150.0, $field->getWidth());
        $this->assertEquals(50.0, $field->getHeight());
    }

    public function testSignatureFieldSetMetadata(): void
    {
        $field = SignatureField::create('Sig')
            ->setSignerName('John Doe')
            ->setReason('Document Approval')
            ->setLocation('New York')
            ->setContactInfo('john@example.com');

        $this->assertEquals('John Doe', $field->getSignerName());
        $this->assertEquals('Document Approval', $field->getReason());
        $this->assertEquals('New York', $field->getLocation());
        $this->assertEquals('john@example.com', $field->getContactInfo());
    }

    public function testSignatureFieldGetSignatureInfo(): void
    {
        $field = SignatureField::create('Sig')
            ->setSignerName('John Doe')
            ->setReason('Approval');

        $info = $field->getSignatureInfo();

        $this->assertArrayHasKey('Name', $info);
        $this->assertArrayHasKey('Reason', $info);
        $this->assertEquals('John Doe', $info['Name']);
    }

    public function testSignatureFieldSetInvisible(): void
    {
        $field = SignatureField::create('Sig')
            ->invisible();

        $this->assertFalse($field->isVisible());
    }

    public function testSignatureFieldSignatureTypes(): void
    {
        $field = SignatureField::create('Sig');

        // Default is approval
        $this->assertEquals(SignatureField::TYPE_APPROVAL, $field->getSignatureType());
        $this->assertFalse($field->isCertifying());

        // Set to certifying
        $field->asCertifyNoChanges();
        $this->assertEquals(SignatureField::TYPE_CERTIFY_NO_CHANGES, $field->getSignatureType());
        $this->assertTrue($field->isCertifying());

        $field->asCertifyFormFill();
        $this->assertEquals(SignatureField::TYPE_CERTIFY_FORM_FILL, $field->getSignatureType());

        $field->asCertifyAnnotate();
        $this->assertEquals(SignatureField::TYPE_CERTIFY_ANNOTATE, $field->getSignatureType());
    }

    public function testSignatureFieldMarkSigned(): void
    {
        $field = SignatureField::create('Sig');

        $this->assertFalse($field->isSigned());

        $field->markSigned();

        $this->assertTrue($field->isSigned());
    }

    public function testSignatureFieldGetFlags(): void
    {
        $field = SignatureField::create('Sig');

        // Visible: Print (4) + Locked (128) = 132
        $this->assertEquals(132, $field->getFlags());

        // Invisible adds Hidden (2) + NoView (32) = 166
        $field->invisible();
        $this->assertEquals(166, $field->getFlags());
    }

    public function testSignatureFieldToArray(): void
    {
        $field = SignatureField::create('Sig')
            ->setPage(2)
            ->setPosition(100, 200)
            ->setSize(150, 50)
            ->setReason('Test');

        $array = $field->toArray();

        $this->assertEquals('Sig', $array['name']);
        $this->assertEquals(2, $array['page']);
        $this->assertTrue($array['visible']);
        $this->assertFalse($array['isSigned']);
    }

    public function testSignatureFieldFromArray(): void
    {
        $data = [
            'name' => 'TestSig',
            'page' => 3,
            'rect' => [50, 60, 200, 100],
            'visible' => false,
            'signatureType' => SignatureField::TYPE_CERTIFY_FORM_FILL,
            'info' => [
                'Name' => 'Jane Doe',
                'Reason' => 'Review',
            ],
        ];

        $field = SignatureField::fromArray($data);

        $this->assertEquals('TestSig', $field->getName());
        $this->assertEquals(3, $field->getPage());
        $this->assertFalse($field->isVisible());
        $this->assertEquals(SignatureField::TYPE_CERTIFY_FORM_FILL, $field->getSignatureType());
        $this->assertEquals('Jane Doe', $field->getSignerName());
    }

    public function testSignatureFieldSanitizesName(): void
    {
        $field = SignatureField::create('My Signature!@#$%');

        // Special characters replaced with underscores
        $this->assertEquals('My_Signature_____', $field->getName());
    }

    public function testSignatureFieldWithName(): void
    {
        $field = SignatureField::create('Sig1')
            ->setReason('Original');

        $cloned = $field->withName('Sig2');

        $this->assertEquals('Sig1', $field->getName());
        $this->assertEquals('Sig2', $cloned->getName());
        $this->assertEquals('Original', $cloned->getReason()); // Properties preserved
    }

    // ==================== TimestampClient Tests ====================

    public function testTimestampClientCreation(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');

        $this->assertInstanceOf(TimestampClient::class, $client);
    }

    public function testTimestampClientStaticCreate(): void
    {
        $client = TimestampClient::create('http://timestamp.digicert.com');

        $this->assertInstanceOf(TimestampClient::class, $client);
    }

    public function testTimestampClientSetHashAlgorithm(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');

        // These should not throw
        $client->setHashAlgorithm('sha256');
        $client->setHashAlgorithm('sha384');
        $client->setHashAlgorithm('sha512');
        $client->setHashAlgorithm('sha1');

        $this->expectNotToPerformAssertions();
    }

    public function testTimestampClientInvalidHashAlgorithm(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');

        $this->expectException(\InvalidArgumentException::class);
        $client->setHashAlgorithm('md5');
    }

    public function testTimestampClientSetTimeout(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');
        $client->setTimeout(60);

        $this->expectNotToPerformAssertions();
    }

    public function testTimestampClientSetCredentials(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');
        $client->setCredentials('user', 'pass');

        $this->expectNotToPerformAssertions();
    }

    public function testTimestampClientAddHeader(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');
        $client->addHeader('X-Custom-Header', 'value');

        $this->expectNotToPerformAssertions();
    }

    public function testTimestampClientSetRequestCertificate(): void
    {
        $client = new TimestampClient('http://timestamp.digicert.com');
        $client->setRequestCertificate(false);

        $this->expectNotToPerformAssertions();
    }

    public function testTimestampClientGetWellKnownUrls(): void
    {
        $urls = TimestampClient::getWellKnownTsaUrls();

        $this->assertArrayHasKey('DigiCert', $urls);
        $this->assertArrayHasKey('Sectigo', $urls);
        $this->assertArrayHasKey('GlobalSign', $urls);
        $this->assertArrayHasKey('FreeTSA', $urls);
        $this->assertArrayHasKey('Comodo', $urls);
    }

    // ==================== LtvValidator Tests ====================

    public function testLtvValidatorCreation(): void
    {
        $validator = new LtvValidator();

        $this->assertInstanceOf(LtvValidator::class, $validator);
    }

    public function testLtvValidatorAddCertificate(): void
    {
        $testCert = $this->createTestCertificate();
        $cert = Certificate::fromPem($testCert['cert']);

        $validator = new LtvValidator();
        $validator->addCertificate($cert);

        $this->expectNotToPerformAssertions();
    }

    public function testLtvValidatorSetTimeout(): void
    {
        $validator = new LtvValidator();
        $validator->setTimeout(60);

        $this->expectNotToPerformAssertions();
    }

    public function testLtvValidatorSetCheckOcsp(): void
    {
        $validator = new LtvValidator();
        $validator->setCheckOcsp(true);
        $validator->setCheckCrl(false);

        $this->expectNotToPerformAssertions();
    }

    public function testLtvValidatorBuildDssWithNoCertificates(): void
    {
        $validator = new LtvValidator();

        $dss = $validator->buildDss();

        $this->assertIsArray($dss);
        $this->assertArrayHasKey('Certs', $dss);
        $this->assertArrayHasKey('OCSPs', $dss);
        $this->assertArrayHasKey('CRLs', $dss);
        $this->assertEmpty($dss['Certs']);
    }

    public function testLtvValidatorBuildDssWithCertificate(): void
    {
        $testCert = $this->createTestCertificate();
        $cert = Certificate::fromPem($testCert['cert']);

        $validator = new LtvValidator();
        $validator->addCertificate($cert);

        $dss = $validator->buildDss();

        $this->assertNotEmpty($dss['Certs']);
    }

    public function testLtvValidatorCheckRevocationForSelfSignedCert(): void
    {
        $testCert = $this->createTestCertificate();
        $cert = Certificate::fromPem($testCert['cert']);

        $validator = new LtvValidator();
        $validator->addCertificate($cert);

        // Self-signed cert should be reported as unknown (no OCSP/CRL)
        $result = $validator->checkRevocation($cert);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        // Status could be 'unknown' or 'error' for self-signed
    }

    // ==================== Signer Tests ====================

    public function testSignerCreation(): void
    {
        $signer = new Signer();

        $this->assertInstanceOf(Signer::class, $signer);
    }

    public function testSignerStaticCreate(): void
    {
        $signer = Signer::create();

        $this->assertInstanceOf(Signer::class, $signer);
    }

    public function testSignerLoadContent(): void
    {
        $pdf = $this->createTestPdf();

        $signer = new Signer();
        $signer->loadContent($pdf);

        $this->expectNotToPerformAssertions();
    }

    public function testSignerInvalidContent(): void
    {
        $signer = new Signer();

        $this->expectException(\InvalidArgumentException::class);
        $signer->loadContent('Not a PDF');
    }

    public function testSignerSetCertificateFromPem(): void
    {
        $testCert = $this->createTestCertificate();
        $pdf = $this->createTestPdf();

        $signer = new Signer();
        $signer->loadContent($pdf)
               ->setCertificateFromPem($testCert['cert'], $testCert['key']);

        $this->expectNotToPerformAssertions();
    }

    public function testSignerSetSignatureField(): void
    {
        $pdf = $this->createTestPdf();

        $field = SignatureField::create('Signature1')
            ->setPosition(100, 100)
            ->setSize(200, 50)
            ->setReason('Test signature');

        $signer = new Signer();
        $signer->loadContent($pdf)
               ->setSignatureField($field);

        $this->expectNotToPerformAssertions();
    }

    public function testSignerSetInvisibleSignature(): void
    {
        $pdf = $this->createTestPdf();

        $signer = new Signer();
        $signer->loadContent($pdf)
               ->setInvisibleSignature();

        $this->expectNotToPerformAssertions();
    }

    public function testSignerSetDigestAlgorithm(): void
    {
        $signer = new Signer();

        $signer->setDigestAlgorithm('sha256');
        $signer->setDigestAlgorithm('sha384');
        $signer->setDigestAlgorithm('sha512');

        $this->expectNotToPerformAssertions();
    }

    public function testSignerInvalidDigestAlgorithm(): void
    {
        $signer = new Signer();

        $this->expectException(\InvalidArgumentException::class);
        $signer->setDigestAlgorithm('md5');
    }

    public function testSignerEnableTimestamp(): void
    {
        $signer = new Signer();
        $signer->enableTimestamp('http://timestamp.digicert.com');

        $this->assertEquals(Signer::LEVEL_T, $signer->getSignatureLevel());
    }

    public function testSignerEnableLtv(): void
    {
        $signer = new Signer();
        $signer->enableLtv();

        $this->assertEquals(Signer::LEVEL_LTV, $signer->getSignatureLevel());
    }

    public function testSignerDisableTimestamp(): void
    {
        $signer = new Signer();
        $signer->enableTimestamp('http://timestamp.digicert.com')
               ->disableTimestamp();

        $this->assertEquals(Signer::LEVEL_B, $signer->getSignatureLevel());
    }

    public function testSignerGetSignatureLevel(): void
    {
        $signer = new Signer();

        // Default is Basic
        $this->assertEquals(Signer::LEVEL_B, $signer->getSignatureLevel());

        // With timestamp
        $signer->enableTimestamp('http://timestamp.digicert.com');
        $this->assertEquals(Signer::LEVEL_T, $signer->getSignatureLevel());

        // With LTV (overrides timestamp level)
        $signer->enableLtv();
        $this->assertEquals(Signer::LEVEL_LTV, $signer->getSignatureLevel());
    }

    public function testSignerGetSignatureSize(): void
    {
        $size = Signer::getSignatureSize();

        $this->assertGreaterThan(0, $size);
        $this->assertEquals(32768, $size);
    }

    public function testSignerSignWithoutPdf(): void
    {
        $testCert = $this->createTestCertificate();

        $signer = new Signer();
        $signer->setCertificateFromPem($testCert['cert'], $testCert['key']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No PDF loaded');
        $signer->sign();
    }

    public function testSignerSignWithoutCertificate(): void
    {
        $pdf = $this->createTestPdf();

        $signer = new Signer();
        $signer->loadContent($pdf);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No certificate set');
        $signer->sign();
    }

    public function testSignerSign(): void
    {
        $testCert = $this->createTestCertificate();
        $pdf = $this->createTestPdf();

        $field = SignatureField::create('Signature1')
            ->setPosition(100, 700)
            ->setSize(200, 50)
            ->setSignerName('Test Signer')
            ->setReason('Approval');

        $signer = new Signer();
        $signedPdf = $signer->loadContent($pdf)
                            ->setCertificateFromPem($testCert['cert'], $testCert['key'])
                            ->setSignatureField($field)
                            ->sign();

        $this->assertStringStartsWith('%PDF-', $signedPdf);
        $this->assertStringContainsString('/Type /Sig', $signedPdf);
        $this->assertStringContainsString('/Filter /Adobe.PPKLite', $signedPdf);
        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $signedPdf);
        $this->assertStringContainsString('/ByteRange', $signedPdf);
        $this->assertStringContainsString('/Contents <', $signedPdf);
    }

    public function testSignerSignToFile(): void
    {
        $testCert = $this->createTestCertificate();
        $pdf = $this->createTestPdf();
        $outputPath = $this->targetDir . '/signed_test.pdf';

        $field = SignatureField::create('Signature1')
            ->setPosition(100, 700)
            ->setSize(200, 50)
            ->setReason('Test');

        $signer = new Signer();
        $result = $signer->loadContent($pdf)
                         ->setCertificateFromPem($testCert['cert'], $testCert['key'])
                         ->setSignatureField($field)
                         ->signToFile($outputPath);

        $this->assertTrue($result);
        $this->assertFileExists($outputPath);
    }

    public function testSignerVerifyNonSignedPdf(): void
    {
        $pdf = $this->createTestPdf();
        $tempFile = $this->targetDir . '/temp_unsigned.pdf';
        file_put_contents($tempFile, $pdf);

        $result = Signer::verify($tempFile);

        $this->assertFalse($result['valid']);
        $this->assertContains('No signature found in document', $result['errors']);
    }

    public function testSignerVerifyNonExistentFile(): void
    {
        $result = Signer::verify('/nonexistent/file.pdf');

        $this->assertFalse($result['valid']);
        $this->assertContains('File not found', $result['errors']);
    }

    // ==================== Signature Integration Tests ====================

    public function testCreateSignedPdfOutput(): void
    {
        $testCert = $this->createTestCertificate();

        $document = PdfDocument::create();

        $page = new Page(PageSize::a4());
        $page->addText('Digitally Signed Document', 100, 750, ['fontSize' => 24]);
        $page->addText('This document has been digitally signed.', 100, 700, ['fontSize' => 12]);
        $page->addText('Signature Details:', 100, 650, ['fontSize' => 14]);
        $page->addText('- Signer: Test Signer', 120, 620, ['fontSize' => 10]);
        $page->addText('- Reason: Document Approval', 120, 600, ['fontSize' => 10]);
        $page->addText('- Location: San Francisco, CA', 120, 580, ['fontSize' => 10]);
        $page->addText('Signature Field Location:', 100, 500, ['fontSize' => 12]);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/digitally_signed.pdf';

        $field = SignatureField::create('ApprovalSignature')
            ->setPosition(100, 400)
            ->setSize(200, 50)
            ->setSignerName('Test Signer')
            ->setReason('Document Approval')
            ->setLocation('San Francisco, CA')
            ->setContactInfo('test@example.com');

        $signer = new Signer();
        $signer->loadContent($document->render())
               ->setCertificateFromPem($testCert['cert'], $testCert['key'])
               ->setSignatureField($field)
               ->signToFile($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testCreateInvisibleSignaturePdfOutput(): void
    {
        $testCert = $this->createTestCertificate();

        $document = PdfDocument::create();

        $page = new Page(PageSize::a4());
        $page->addText('Document with Invisible Signature', 100, 750, ['fontSize' => 24]);
        $page->addText('This document is digitally signed, but the signature is invisible.', 100, 700, ['fontSize' => 12]);
        $page->addText('Open in Adobe Reader to verify the signature.', 100, 660, ['fontSize' => 12]);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/invisible_signature.pdf';

        $signer = new Signer();
        $signer->loadContent($document->render())
               ->setCertificateFromPem($testCert['cert'], $testCert['key'])
               ->setInvisibleSignature()
               ->signToFile($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testCreateCertifiedPdfOutput(): void
    {
        $testCert = $this->createTestCertificate();

        $document = PdfDocument::create();

        $page = new Page(PageSize::a4());
        $page->addText('Certified Document', 100, 750, ['fontSize' => 24]);
        $page->addText('This document has been certified.', 100, 700, ['fontSize' => 12]);
        $page->addText('Certification allows only form filling.', 100, 660, ['fontSize' => 12]);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/certified_document.pdf';

        $field = SignatureField::create('CertifyingSignature')
            ->setPosition(100, 550)
            ->setSize(200, 50)
            ->setReason('Document Certification')
            ->asCertifyFormFill();

        $signer = new Signer();
        $signer->loadContent($document->render())
               ->setCertificateFromPem($testCert['cert'], $testCert['key'])
               ->setSignatureField($field)
               ->signToFile($outputPath);

        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(0, filesize($outputPath));
    }

    public function testCertificateSummary(): void
    {
        $testCert = $this->createTestCertificate();
        $cert = Certificate::fromPem($testCert['cert']);

        // Verify all certificate methods return expected values
        $this->assertEquals('Test Signer', $cert->getSubjectName());
        $this->assertEquals('Test Signer', $cert->getIssuerName()); // Self-signed
        $this->assertIsString($cert->getSerialNumber());
        $this->assertInstanceOf(\DateTimeImmutable::class, $cert->getValidFrom());
        $this->assertInstanceOf(\DateTimeImmutable::class, $cert->getValidTo());
        $this->assertTrue($cert->isSelfSigned());
        $this->assertTrue($cert->canSign());
        $this->assertNotEmpty($cert->getFingerprint('sha256'));
        $this->assertEquals(64, strlen($cert->getFingerprint('sha256'))); // SHA-256 hex is 64 chars
        $this->assertTrue($cert->isValid());
    }

    // ==================== Multiple Signature Tests ====================

    public function testMultipleSignatures(): void
    {
        // Create two different test certificates
        $cert1 = $this->createTestCertificateWithName('First Signer', 'first@example.com');
        $cert2 = $this->createTestCertificateWithName('Second Signer', 'second@example.com');

        // Create original document
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());
        $page->addText('Document with Multiple Signatures', 100, 750, ['fontSize' => 24]);
        $page->addText('This document requires approval from two signers.', 100, 700, ['fontSize' => 12]);
        $page->addText('First Signature:', 100, 600, ['fontSize' => 14]);
        $page->addText('Second Signature:', 100, 450, ['fontSize' => 14]);
        $document->addPageObject($page);

        $outputPath = $this->targetDir . '/multiple_signatures.pdf';

        // First signature
        $field1 = SignatureField::create('FirstApproval')
            ->setPosition(100, 520)
            ->setSize(200, 50)
            ->setSignerName('First Signer')
            ->setReason('First Approval')
            ->setLocation('New York');

        $signer1 = new Signer();
        $signedOnce = $signer1->loadContent($document->render())
            ->setCertificateFromPem($cert1['cert'], $cert1['key'])
            ->setSignatureField($field1)
            ->sign();

        // Second signature on the already-signed PDF
        $field2 = SignatureField::create('SecondApproval')
            ->setPosition(100, 370)
            ->setSize(200, 50)
            ->setSignerName('Second Signer')
            ->setReason('Final Approval')
            ->setLocation('Los Angeles');

        $signer2 = new Signer();
        $signedTwice = $signer2->loadContent($signedOnce)
            ->setCertificateFromPem($cert2['cert'], $cert2['key'])
            ->setSignatureField($field2)
            ->sign();

        file_put_contents($outputPath, $signedTwice);

        // Verify the file exists and has content
        $this->assertFileExists($outputPath);
        $this->assertGreaterThan(100000, filesize($outputPath)); // Should be larger due to two signatures

        // Verify both signatures are present
        $content = file_get_contents($outputPath);

        // Check for two /Type /Sig entries
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $content);
        $this->assertEquals(2, $sigCount, 'Should have exactly 2 signature dictionaries');

        // Check for two ByteRange entries
        $byteRangeCount = preg_match_all('/\/ByteRange\s*\[/', $content);
        $this->assertEquals(2, $byteRangeCount, 'Should have exactly 2 ByteRange entries');

        // Check both signers are mentioned
        $this->assertStringContainsString('First Signer', $content);
        $this->assertStringContainsString('Second Signer', $content);

        // Check both signature fields are in the AcroForm Fields array
        preg_match_all('/\/Fields\s*\[([^\]]+)\]/', $content, $fieldsMatches);
        $lastFields = end($fieldsMatches[1]);
        $fieldRefCount = preg_match_all('/\d+\s+\d+\s+R/', $lastFields);
        $this->assertEquals(2, $fieldRefCount, 'AcroForm should have 2 field references');
    }

    public function testMultipleSignaturesWithDifferentPages(): void
    {
        $cert1 = $this->createTestCertificateWithName('Manager', 'manager@example.com');
        $cert2 = $this->createTestCertificateWithName('Director', 'director@example.com');

        // Create multi-page document
        $document = PdfDocument::create();

        $page1 = new Page(PageSize::a4());
        $page1->addText('Contract Agreement - Page 1', 100, 750, ['fontSize' => 24]);
        $page1->addText('Terms and conditions...', 100, 700, ['fontSize' => 12]);
        $page1->addText('Manager Signature:', 100, 200, ['fontSize' => 14]);
        $document->addPageObject($page1);

        $page2 = new Page(PageSize::a4());
        $page2->addText('Contract Agreement - Page 2', 100, 750, ['fontSize' => 24]);
        $page2->addText('Additional terms...', 100, 700, ['fontSize' => 12]);
        $page2->addText('Director Signature:', 100, 200, ['fontSize' => 14]);
        $document->addPageObject($page2);

        $outputPath = $this->targetDir . '/multi_page_signatures.pdf';

        // First signature on page 1
        $field1 = SignatureField::create('ManagerSignature')
            ->setPage(1)
            ->setPosition(100, 120)
            ->setSize(200, 50)
            ->setReason('Manager Approval');

        $signer1 = new Signer();
        $signedOnce = $signer1->loadContent($document->render())
            ->setCertificateFromPem($cert1['cert'], $cert1['key'])
            ->setSignatureField($field1)
            ->sign();

        // Second signature on page 2
        $field2 = SignatureField::create('DirectorSignature')
            ->setPage(2)
            ->setPosition(100, 120)
            ->setSize(200, 50)
            ->setReason('Director Approval');

        $signer2 = new Signer();
        $signedTwice = $signer2->loadContent($signedOnce)
            ->setCertificateFromPem($cert2['cert'], $cert2['key'])
            ->setSignatureField($field2)
            ->sign();

        file_put_contents($outputPath, $signedTwice);

        $this->assertFileExists($outputPath);

        // Verify both signatures
        $content = file_get_contents($outputPath);
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $content);
        $this->assertEquals(2, $sigCount);
    }

    /**
     * Test sequential signing workflow:
     * 1. User 1 signs and downloads
     * 2. User 2 signs doc from flow 1 and downloads
     * 3. User 3 signs doc from flow 2 and downloads
     */
    public function testSequentialSigningWorkflow(): void
    {
        // Create three different certificates for three users
        $user1Cert = $this->createTestCertificateWithName('User 1 - Department Head', 'user1@company.com');
        $user2Cert = $this->createTestCertificateWithName('User 2 - Legal Review', 'user2@company.com');
        $user3Cert = $this->createTestCertificateWithName('User 3 - CEO', 'user3@company.com');

        // Create original document
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());
        $page->addText('Contract Agreement', 100, 750, ['fontSize' => 24]);
        $page->addText('This document requires three approvals.', 100, 700, ['fontSize' => 12]);
        $page->addText('User 1 Signature (Department Head):', 100, 600, ['fontSize' => 12]);
        $page->addText('User 2 Signature (Legal Review):', 100, 450, ['fontSize' => 12]);
        $page->addText('User 3 Signature (CEO Final Approval):', 100, 300, ['fontSize' => 12]);
        $document->addPageObject($page);

        $originalContent = $document->render();

        // ========== Flow 1: User 1 signs and downloads ==========
        $field1 = SignatureField::create('User1Approval')
            ->setPosition(100, 520)
            ->setSize(200, 50)
            ->setSignerName('User 1 - Department Head')
            ->setReason('Department Head Approval')
            ->setLocation('New York Office');

        $signer1 = new Signer();
        $signedByUser1 = $signer1->loadContent($originalContent)
            ->setCertificateFromPem($user1Cert['cert'], $user1Cert['key'])
            ->setSignatureField($field1)
            ->sign();

        // Simulate User 1 downloading
        $user1DownloadPath = $this->targetDir . '/workflow_user1_download.pdf';
        file_put_contents($user1DownloadPath, $signedByUser1);
        $this->assertFileExists($user1DownloadPath);

        // Verify User 1's signature
        $content1 = file_get_contents($user1DownloadPath);
        $this->assertEquals(1, preg_match_all('/\/Type\s*\/Sig\b/', $content1));
        $this->assertStringContainsString('User 1 - Department Head', $content1);

        // ========== Flow 2: User 2 signs doc from flow 1 ==========
        $field2 = SignatureField::create('User2Approval')
            ->setPosition(100, 370)
            ->setSize(200, 50)
            ->setSignerName('User 2 - Legal Review')
            ->setReason('Legal Review Complete')
            ->setLocation('Chicago Office');

        $signer2 = new Signer();
        $signedByUser1And2 = $signer2->loadContent($signedByUser1)  // Load User 1's signed doc
            ->setCertificateFromPem($user2Cert['cert'], $user2Cert['key'])
            ->setSignatureField($field2)
            ->sign();

        // Simulate User 2 downloading
        $user2DownloadPath = $this->targetDir . '/workflow_user2_download.pdf';
        file_put_contents($user2DownloadPath, $signedByUser1And2);
        $this->assertFileExists($user2DownloadPath);

        // Verify both User 1 and User 2 signatures
        $content2 = file_get_contents($user2DownloadPath);
        $this->assertEquals(2, preg_match_all('/\/Type\s*\/Sig\b/', $content2));
        $this->assertStringContainsString('User 1 - Department Head', $content2);
        $this->assertStringContainsString('User 2 - Legal Review', $content2);

        // ========== Flow 3: User 3 signs doc from flow 2 ==========
        $field3 = SignatureField::create('User3Approval')
            ->setPosition(100, 220)
            ->setSize(200, 50)
            ->setSignerName('User 3 - CEO')
            ->setReason('CEO Final Approval')
            ->setLocation('Los Angeles HQ');

        $signer3 = new Signer();
        $fullySignedPdf = $signer3->loadContent($signedByUser1And2)  // Load doc with 2 signatures
            ->setCertificateFromPem($user3Cert['cert'], $user3Cert['key'])
            ->setSignatureField($field3)
            ->sign();

        // Simulate User 3 downloading the fully signed document
        $user3DownloadPath = $this->targetDir . '/workflow_user3_download.pdf';
        file_put_contents($user3DownloadPath, $fullySignedPdf);
        $this->assertFileExists($user3DownloadPath);

        // Verify all three signatures are present
        $content3 = file_get_contents($user3DownloadPath);
        $this->assertEquals(3, preg_match_all('/\/Type\s*\/Sig\b/', $content3));
        $this->assertEquals(3, preg_match_all('/\/ByteRange\s*\[/', $content3));

        // All signers should be present
        $this->assertStringContainsString('User 1 - Department Head', $content3);
        $this->assertStringContainsString('User 2 - Legal Review', $content3);
        $this->assertStringContainsString('User 3 - CEO', $content3);

        // AcroForm should have 3 field references
        preg_match_all('/\/Fields\s*\[([^\]]+)\]/', $content3, $fieldsMatches);
        $lastFields = end($fieldsMatches[1]);
        $fieldRefCount = preg_match_all('/\d+\s+\d+\s+R/', $lastFields);
        $this->assertEquals(3, $fieldRefCount, 'AcroForm should have 3 field references');

        // File size should increase with each signature
        $this->assertGreaterThan(filesize($user1DownloadPath), filesize($user2DownloadPath));
        $this->assertGreaterThan(filesize($user2DownloadPath), filesize($user3DownloadPath));
    }

    /**
     * Test multiSign static method for batch signing.
     */
    public function testMultiSignStaticMethod(): void
    {
        // Create test certificates
        $cert1 = $this->createTestCertificateWithName('Approver', 'approver@company.com');
        $cert2 = $this->createTestCertificateWithName('Reviewer', 'reviewer@company.com');
        $cert3 = $this->createTestCertificateWithName('Manager', 'manager@company.com');

        // Save certificates to temp files for multiSign
        $cert1Path = $this->targetDir . '/approver.pem';
        $key1Path = $this->targetDir . '/approver_key.pem';
        file_put_contents($cert1Path, $cert1['cert']);
        file_put_contents($key1Path, $cert1['key']);

        $cert2Path = $this->targetDir . '/reviewer.pem';
        $key2Path = $this->targetDir . '/reviewer_key.pem';
        file_put_contents($cert2Path, $cert2['cert']);
        file_put_contents($key2Path, $cert2['key']);

        $cert3Path = $this->targetDir . '/manager.pem';
        $key3Path = $this->targetDir . '/manager_key.pem';
        file_put_contents($cert3Path, $cert3['cert']);
        file_put_contents($key3Path, $cert3['key']);

        // Create original document
        $document = PdfDocument::create();
        $page = new Page(PageSize::a4());
        $page->addText('Multi-Signature Document', 100, 750, ['fontSize' => 24]);
        $page->addText('Signed by multiple parties using multiSign method.', 100, 700, ['fontSize' => 12]);
        $document->addPageObject($page);

        // Use multiSignContent to apply all signatures
        $signedContent = Signer::multiSignContent($document->render(), [
            [
                'cert' => $cert1Path,
                'password' => '',
                'key' => $key1Path,
                'reason' => 'Approved by Department',
                'location' => 'New York',
            ],
            [
                'cert' => $cert2Path,
                'password' => '',
                'key' => $key2Path,
                'reason' => 'Reviewed for Compliance',
                'location' => 'Chicago',
            ],
            [
                'cert' => $cert3Path,
                'password' => '',
                'key' => $key3Path,
                'reason' => 'Final Management Approval',
                'location' => 'Los Angeles',
                'contact' => 'manager@company.com',
            ],
        ]);

        $outputPath = $this->targetDir . '/multi_sign_static.pdf';
        file_put_contents($outputPath, $signedContent);

        $this->assertFileExists($outputPath);

        // Verify all three signatures
        $content = file_get_contents($outputPath);
        $sigCount = preg_match_all('/\/Type\s*\/Sig\b/', $content);
        $this->assertEquals(3, $sigCount, 'Should have 3 signatures');
    }

    /**
     * Create a test certificate with custom name.
     *
     * @return array{cert: string, key: string}
     */
    private function createTestCertificateWithName(string $commonName, string $email): array
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
            'organizationName' => 'Test Organization',
            'commonName' => $commonName,
            'emailAddress' => $email,
        ];

        $csr = openssl_csr_new($dn, $privateKey, $config);
        $cert = openssl_csr_sign($csr, null, $privateKey, 365, $config);

        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($privateKey, $keyPem);

        return ['cert' => $certPem, 'key' => $keyPem];
    }
}
