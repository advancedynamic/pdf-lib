<?php

declare(strict_types=1);

namespace PdfLib\Security\Encryption;

use PdfLib\Document\PdfDocument;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfString;
use PdfLib\Parser\PdfParser;

/**
 * PDF Encryptor - Encrypt PDF documents with passwords and permissions.
 *
 * @example
 * ```php
 * $encryptor = new Encryptor('document.pdf');
 * $encryptor->setUserPassword('user123')
 *           ->setOwnerPassword('owner456')
 *           ->setEncryptionMode(Encryptor::AES_256)
 *           ->allowPrinting()
 *           ->denyModifying()
 *           ->save('encrypted.pdf');
 * ```
 */
final class Encryptor
{
    // Encryption modes
    public const RC4_40 = 0;
    public const RC4_128 = 1;
    public const AES_128 = 2;
    public const AES_256 = 3;

    private ?PdfParser $parser = null;
    private string $content = '';
    private string $version = '1.7';

    private string $userPassword = '';
    private string $ownerPassword = '';
    private int $encryptionMode = self::AES_128;
    private Permissions $permissions;

    private string $documentId = '';
    private string $encryptionKey = '';

    public function __construct(?string $filePath = null)
    {
        $this->permissions = Permissions::allowAll();

        if ($filePath !== null) {
            $this->loadFile($filePath);
        }
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
        $this->version = $this->parser->getVersion();

        return $this;
    }

    /**
     * Set user password (required to open document).
     */
    public function setUserPassword(string $password): self
    {
        $this->userPassword = $password;
        return $this;
    }

    /**
     * Set owner password (required for full access).
     */
    public function setOwnerPassword(string $password): self
    {
        $this->ownerPassword = $password;
        return $this;
    }

    /**
     * Set both passwords at once.
     */
    public function setPasswords(string $userPassword, string $ownerPassword): self
    {
        $this->userPassword = $userPassword;
        $this->ownerPassword = $ownerPassword;
        return $this;
    }

    /**
     * Set encryption mode.
     */
    public function setEncryptionMode(int $mode): self
    {
        if (!in_array($mode, [self::RC4_40, self::RC4_128, self::AES_128, self::AES_256], true)) {
            throw new \InvalidArgumentException('Invalid encryption mode');
        }

        $this->encryptionMode = $mode;
        return $this;
    }

    /**
     * Set permissions object.
     */
    public function setPermissions(Permissions $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Allow all permissions.
     */
    public function allowAllPermissions(): self
    {
        $this->permissions = Permissions::allowAll();
        return $this;
    }

    /**
     * Deny all permissions.
     */
    public function denyAllPermissions(): self
    {
        $this->permissions = Permissions::denyAll();
        return $this;
    }

    /**
     * Allow printing.
     */
    public function allowPrinting(bool $allow = true): self
    {
        $this->permissions->allowPrinting($allow);
        return $this;
    }

    /**
     * Allow high-quality printing.
     */
    public function allowHighQualityPrinting(bool $allow = true): self
    {
        $this->permissions->allowHighQualityPrinting($allow);
        return $this;
    }

    /**
     * Allow modifying.
     */
    public function allowModifying(bool $allow = true): self
    {
        $this->permissions->allowModifying($allow);
        return $this;
    }

    /**
     * Allow copying.
     */
    public function allowCopying(bool $allow = true): self
    {
        $this->permissions->allowCopying($allow);
        return $this;
    }

    /**
     * Allow annotations.
     */
    public function allowAnnotations(bool $allow = true): self
    {
        $this->permissions->allowAnnotations($allow);
        return $this;
    }

    /**
     * Allow form filling.
     */
    public function allowFormFilling(bool $allow = true): self
    {
        $this->permissions->allowFormFilling($allow);
        return $this;
    }

    /**
     * Allow extraction.
     */
    public function allowExtraction(bool $allow = true): self
    {
        $this->permissions->allowExtraction($allow);
        return $this;
    }

    /**
     * Allow assembly.
     */
    public function allowAssembly(bool $allow = true): self
    {
        $this->permissions->allowAssembly($allow);
        return $this;
    }

    /**
     * Get the encryption handler for the current mode.
     */
    public function getHandler(): EncryptionHandler
    {
        return match ($this->encryptionMode) {
            self::RC4_40 => Rc4Handler::rc4_40(),
            self::RC4_128 => Rc4Handler::rc4_128(),
            self::AES_128 => new Aes128Handler(),
            self::AES_256 => new Aes256Handler(),
        };
    }

    /**
     * Get minimum PDF version for current encryption mode.
     */
    public function getMinimumVersion(): string
    {
        return match ($this->encryptionMode) {
            self::RC4_40 => '1.1',
            self::RC4_128 => '1.4',
            self::AES_128 => '1.5',
            self::AES_256 => '1.7',
        };
    }

    /**
     * Encrypt and return the PDF content as a string.
     */
    public function encryptToString(): string
    {
        $this->ensureLoaded();

        // Generate document ID if not already set
        if ($this->documentId === '') {
            $this->documentId = random_bytes(16);
        }

        // Get handler and generate encryption data
        $handler = $this->getHandler();
        $encryptData = $this->generateEncryptionData($handler);
        $this->encryptionKey = $encryptData['key'];

        // Ensure version is high enough for encryption mode
        $minVersion = $this->getMinimumVersion();
        if (version_compare($this->version, $minVersion, '<')) {
            $this->version = $minVersion;
        }

        // Rebuild PDF with encryption
        return $this->rebuildEncryptedPdf($handler, $encryptData);
    }

    /**
     * Encrypt and return the PDF document object.
     */
    public function encrypt(): PdfDocument
    {
        $content = $this->encryptToString();
        $doc = PdfDocument::create();
        // The content is already encrypted, just return it wrapped in a document
        // For now, we save to temp and reload - not ideal but functional
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_enc_');
        file_put_contents($tempFile, $content);
        $doc = PdfDocument::load($tempFile);
        unlink($tempFile);
        return $doc;
    }

    /**
     * Generate encryption data based on mode.
     *
     * @return array{key: string, O: string, U: string, OE?: string, UE?: string, Perms?: string}
     */
    private function generateEncryptionData(EncryptionHandler $handler): array
    {
        $ownerPassword = $this->ownerPassword ?: $this->userPassword;

        if ($this->encryptionMode === self::AES_256) {
            $aes256 = new Aes256Handler();
            return $aes256->generateEncryptionData(
                $this->userPassword,
                $ownerPassword,
                $this->permissions->getValue()
            );
        }

        // RC4 and AES-128
        $ownerKey = $handler->computeOwnerKey($ownerPassword, $this->userPassword);
        $encryptionKey = $handler->computeEncryptionKey(
            $this->userPassword,
            $ownerKey,
            $this->permissions->getValue(),
            $this->documentId
        );
        // Pass documentId to computeUserKey (needed for Algorithm 5)
        if ($handler instanceof Aes128Handler || $handler instanceof Rc4Handler) {
            $userKey = $handler->computeUserKey($encryptionKey, $this->documentId);
        } else {
            $userKey = $handler->computeUserKey($encryptionKey);
        }

        return [
            'key' => $encryptionKey,
            'O' => $ownerKey,
            'U' => $userKey,
        ];
    }

    /**
     * Rebuild PDF with encryption.
     */
    private function rebuildEncryptedPdf(EncryptionHandler $handler, array $encryptData): string
    {
        $output = "%PDF-{$this->version}\n";
        $output .= "%\xE2\xE3\xCF\xD3\n";

        $offsets = [];
        $nextObjNum = 1;

        // Collect all objects from the parser
        $objects = $this->collectObjects();
        $encryptObjNum = count($objects) + 1;

        // Write all objects with encryption
        foreach ($objects as $objNum => $objData) {
            $offsets[$objNum] = strlen($output);
            $object = $objData['object'];
            $generation = $objData['generation'];

            // Encrypt streams and strings (but not the encryption dict itself)
            $encryptedObject = $this->encryptObject($object, $handler, $objNum, $generation);

            $output .= "{$objNum} {$generation} obj\n";
            $output .= $this->writeObject($encryptedObject);
            $output .= "\nendobj\n";
        }

        // Write encryption dictionary
        $offsets[$encryptObjNum] = strlen($output);
        $output .= "{$encryptObjNum} 0 obj\n";
        $output .= $this->buildEncryptionDictionary($encryptData);
        $output .= "\nendobj\n";

        // Write xref
        $xrefOffset = strlen($output);
        $output .= $this->writeXref($offsets, $encryptObjNum + 1);

        // Write trailer
        $output .= $this->writeTrailer($encryptObjNum + 1, $encryptObjNum);
        $output .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $output;
    }

    /**
     * Collect all objects from the parsed PDF.
     *
     * @return array<int, array{object: PdfObject, generation: int}>
     */
    private function collectObjects(): array
    {
        $objects = [];
        $xref = $this->parser->getXref();

        foreach ($xref as $objNum => $entry) {
            if ($objNum === 0 || !($entry['inUse'] ?? true)) {
                continue;
            }

            try {
                $object = $this->parser->getObject($objNum);
                if ($object !== null) {
                    $objects[$objNum] = [
                        'object' => $object,
                        'generation' => $entry['generation'] ?? 0,
                    ];
                }
            } catch (\Exception $e) {
                // Skip objects that can't be parsed
            }
        }

        return $objects;
    }

    /**
     * Encrypt a PDF object (streams and strings).
     */
    private function encryptObject(PdfObject $object, EncryptionHandler $handler, int $objNum, int $generation): PdfObject
    {
        if ($object instanceof PdfStream) {
            // Encrypt stream data
            $data = $object->getData();
            $encryptedData = $handler->encrypt($data, $this->encryptionKey, $objNum, $generation);

            $dict = clone $object->getDictionary();
            $dict->set('Length', PdfNumber::int(strlen($encryptedData)));

            return PdfStream::fromData($encryptedData, $dict);
        }

        if ($object instanceof PdfString) {
            // Encrypt string content
            $value = $object->getValue();
            $encrypted = $handler->encrypt($value, $this->encryptionKey, $objNum, $generation);
            return PdfString::hex(bin2hex($encrypted));
        }

        if ($object instanceof PdfArray) {
            // Recursively encrypt array elements
            $newArray = [];
            foreach ($object as $item) {
                $newArray[] = $this->encryptObject($item, $handler, $objNum, $generation);
            }
            return PdfArray::fromValues($newArray);
        }

        if ($object instanceof PdfDictionary) {
            // Recursively encrypt dictionary values
            $newDict = new PdfDictionary();
            foreach ($object->getValue() as $key => $value) {
                $newDict->set($key, $this->encryptObject($value, $handler, $objNum, $generation));
            }
            return $newDict;
        }

        return $object;
    }

    /**
     * Write a PDF object to string.
     */
    private function writeObject(PdfObject $object): string
    {
        if ($object instanceof PdfStream) {
            $dict = $object->getDictionary();
            $data = $object->getData();
            return $this->writeDictionary($dict) . "\nstream\n" . $data . "\nendstream";
        }

        if ($object instanceof PdfString) {
            return $object->toPdfString();
        }

        if ($object instanceof PdfName) {
            return $object->toPdfString();
        }

        if ($object instanceof PdfNumber) {
            $value = $object->getValue();
            if (is_int($value)) {
                return (string) $value;
            }
            $formatted = sprintf('%.10f', $value);
            $formatted = rtrim($formatted, '0');
            $formatted = rtrim($formatted, '.');
            return $formatted;
        }

        if ($object instanceof \PdfLib\Parser\Object\PdfBoolean) {
            return $object->getValue() ? 'true' : 'false';
        }

        if ($object instanceof \PdfLib\Parser\Object\PdfNull) {
            return 'null';
        }

        if ($object instanceof PdfReference) {
            return $object->toPdfString();
        }

        if ($object instanceof PdfArray) {
            $parts = [];
            foreach ($object as $item) {
                $parts[] = $this->writeObject($item);
            }
            return '[' . implode(' ', $parts) . ']';
        }

        if ($object instanceof PdfDictionary) {
            return $this->writeDictionary($object);
        }

        return 'null';
    }

    /**
     * Write a dictionary to string.
     */
    private function writeDictionary(PdfDictionary $dict): string
    {
        if ($dict->isEmpty()) {
            return '<< >>';
        }

        $parts = [];
        foreach ($dict->getValue() as $key => $value) {
            $parts[] = '/' . $key . ' ' . $this->writeObject($value);
        }

        return "<<\n" . implode("\n", $parts) . "\n>>";
    }

    /**
     * Build encryption dictionary string.
     */
    private function buildEncryptionDictionary(array $encryptData): string
    {
        $handler = $this->getHandler();
        $v = $handler->getV();
        $r = $handler->getR();
        $p = $this->permissions->getValue();

        $dict = "<<\n";
        $dict .= "/Filter /Standard\n";
        $dict .= "/V {$v}\n";
        $dict .= "/R {$r}\n";

        if ($this->encryptionMode === self::AES_256) {
            // AES-256 specific
            $dict .= "/Length 256\n";
            $dict .= "/O <" . bin2hex($encryptData['O']) . ">\n";
            $dict .= "/U <" . bin2hex($encryptData['U']) . ">\n";
            $dict .= "/OE <" . bin2hex($encryptData['OE']) . ">\n";
            $dict .= "/UE <" . bin2hex($encryptData['UE']) . ">\n";
            $dict .= "/P {$p}\n";
            $dict .= "/Perms <" . bin2hex($encryptData['Perms']) . ">\n";
            $dict .= "/CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV3 /Length 32 >> >>\n";
            $dict .= "/StmF /StdCF\n";
            $dict .= "/StrF /StdCF\n";
        } elseif ($this->encryptionMode === self::AES_128) {
            // AES-128 specific
            $dict .= "/Length 128\n";
            $dict .= "/O <" . bin2hex($encryptData['O']) . ">\n";
            $dict .= "/U <" . bin2hex($encryptData['U']) . ">\n";
            $dict .= "/P {$p}\n";
            $dict .= "/EncryptMetadata true\n";
            $dict .= "/CF << /StdCF << /AuthEvent /DocOpen /CFM /AESV2 /Length 16 >> >>\n";
            $dict .= "/StmF /StdCF\n";
            $dict .= "/StrF /StdCF\n";
        } else {
            // RC4
            $dict .= "/Length " . $handler->getKeyLength() . "\n";
            $dict .= "/O <" . bin2hex($encryptData['O']) . ">\n";
            $dict .= "/U <" . bin2hex($encryptData['U']) . ">\n";
            $dict .= "/P {$p}\n";
        }

        $dict .= ">>";

        return $dict;
    }

    /**
     * Write xref table.
     */
    private function writeXref(array $offsets, int $size): string
    {
        $output = "xref\n";
        $output .= "0 {$size}\n";

        // Object 0 is always free
        $output .= "0000000000 65535 f \n";

        for ($i = 1; $i < $size; $i++) {
            $offset = $offsets[$i] ?? 0;
            $output .= sprintf("%010d %05d n \n", $offset, 0);
        }

        return $output;
    }

    /**
     * Write trailer.
     */
    private function writeTrailer(int $size, int $encryptObjNum): string
    {
        // Get Root reference from parser
        $trailer = $this->parser->getTrailer();
        $rootRef = $trailer->get('Root');
        $infoRef = $trailer->get('Info');

        $output = "trailer\n<<\n";
        $output .= "/Size {$size}\n";

        if ($rootRef !== null) {
            $output .= "/Root " . $rootRef->toPdfString() . "\n";
        }

        if ($infoRef !== null) {
            $output .= "/Info " . $infoRef->toPdfString() . "\n";
        }

        $output .= "/Encrypt {$encryptObjNum} 0 R\n";
        $output .= "/ID [<" . bin2hex($this->documentId) . "> <" . bin2hex($this->documentId) . ">]\n";
        $output .= ">>\n";

        return $output;
    }

    /**
     * Get encryption dictionary values.
     *
     * @return array{
     *     Filter: string,
     *     V: int,
     *     R: int,
     *     Length?: int,
     *     O: string,
     *     U: string,
     *     P: int,
     *     EncryptMetadata?: bool,
     *     CF?: array,
     *     StmF?: string,
     *     StrF?: string,
     *     OE?: string,
     *     UE?: string,
     *     Perms?: string
     * }
     */
    public function getEncryptionDictionary(): array
    {
        $handler = $this->getHandler();
        $documentId = random_bytes(16);

        $dict = [
            'Filter' => 'Standard',
            'V' => $handler->getV(),
            'R' => $handler->getR(),
            'P' => $this->permissions->getValue(),
            'EncryptMetadata' => true,
        ];

        if ($this->encryptionMode === self::AES_256) {
            $aes256 = new Aes256Handler();
            $encData = $aes256->generateEncryptionData(
                $this->userPassword,
                $this->ownerPassword ?: $this->userPassword,
                $this->permissions->getValue()
            );

            $dict['O'] = $encData['O'];
            $dict['U'] = $encData['U'];
            $dict['OE'] = $encData['OE'];
            $dict['UE'] = $encData['UE'];
            $dict['Perms'] = $encData['Perms'];
        } else {
            $dict['Length'] = $handler->getKeyLength();

            $ownerKey = $handler->computeOwnerKey(
                $this->ownerPassword ?: $this->userPassword,
                $this->userPassword
            );

            $encryptionKey = $handler->computeEncryptionKey(
                $this->userPassword,
                $ownerKey,
                $this->permissions->getValue(),
                $documentId
            );

            $dict['O'] = $ownerKey;
            $dict['U'] = $handler->computeUserKey($encryptionKey);

            if ($this->encryptionMode === self::AES_128) {
                $dict['CF'] = [
                    'StdCF' => [
                        'CFM' => 'AESV2',
                        'Length' => 16,
                    ],
                ];
                $dict['StmF'] = 'StdCF';
                $dict['StrF'] = 'StdCF';
            }
        }

        return $dict;
    }

    /**
     * Encrypt and save to file.
     */
    public function save(string $outputPath): bool
    {
        $content = $this->encryptToString();
        return file_put_contents($outputPath, $content) !== false;
    }

    /**
     * Encrypt and save to file (alias for save).
     */
    public function encryptToFile(string $outputPath): bool
    {
        return $this->save($outputPath);
    }

    /**
     * Ensure PDF is loaded.
     */
    private function ensureLoaded(): void
    {
        if ($this->parser === null) {
            throw new \RuntimeException('No PDF loaded. Call loadFile() or loadContent() first.');
        }
    }
}
