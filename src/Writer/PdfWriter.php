<?php

declare(strict_types=1);

namespace PdfLib\Writer;

use PdfLib\Exception\WriteException;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;

/**
 * Main PDF writer - generates complete PDF documents.
 */
final class PdfWriter
{
    private ObjectWriter $objectWriter;
    private StreamWriter $streamWriter;
    private XrefWriter $xrefWriter;

    /**
     * PDF version to write.
     */
    private string $version = '1.7';

    /**
     * Objects to write.
     *
     * @var array<int, array{object: PdfObject, generation: int}>
     */
    private array $objects = [];

    /**
     * Next object number to assign.
     */
    private int $nextObjectNumber = 1;

    /**
     * Document catalog reference.
     */
    private ?PdfReference $catalogRef = null;

    /**
     * Document info reference.
     */
    private ?PdfReference $infoRef = null;

    /**
     * Encryption dictionary reference.
     */
    private ?PdfReference $encryptRef = null;

    /**
     * Encryption handler for encrypting streams/strings.
     */
    private ?\PdfLib\Security\Encryption\EncryptionHandler $encryptionHandler = null;

    /**
     * Encryption key.
     */
    private string $encryptionKey = '';

    /**
     * Cross-reference format.
     */
    private string $xrefFormat = XrefWriter::FORMAT_TABLE;

    /**
     * Enable compression.
     */
    private bool $compress = true;

    public function __construct()
    {
        $this->objectWriter = new ObjectWriter();
        $this->streamWriter = new StreamWriter();
        $this->xrefWriter = new XrefWriter();
    }

    /**
     * Set PDF version.
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;
        return $this;
    }

    /**
     * Get PDF version.
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Set cross-reference format.
     */
    public function setXrefFormat(string $format): self
    {
        $this->xrefFormat = $format;
        $this->xrefWriter->setFormat($format);
        return $this;
    }

    /**
     * Enable or disable compression.
     */
    public function setCompression(bool $enabled): self
    {
        $this->compress = $enabled;
        if ($enabled) {
            $this->streamWriter->setDefaultFilter(StreamWriter::FILTER_FLATE);
        } else {
            $this->streamWriter->setDefaultFilter(null);
        }
        return $this;
    }

    /**
     * Set compression level (0-9).
     */
    public function setCompressionLevel(int $level): self
    {
        $this->streamWriter->setCompressionLevel($level);
        return $this;
    }

    /**
     * Add an object and return its reference.
     */
    public function addObject(PdfObject $object, int $generation = 0): PdfReference
    {
        $objectNumber = $this->nextObjectNumber++;
        $object->setIndirect($objectNumber, $generation);

        $this->objects[$objectNumber] = [
            'object' => $object,
            'generation' => $generation,
        ];

        return PdfReference::create($objectNumber, $generation);
    }

    /**
     * Set the document catalog.
     */
    public function setCatalog(PdfDictionary $catalog): PdfReference
    {
        $catalog->set('Type', PdfName::create('Catalog'));
        $this->catalogRef = $this->addObject($catalog);
        return $this->catalogRef;
    }

    /**
     * Set the document info dictionary.
     */
    public function setInfo(PdfDictionary $info): PdfReference
    {
        $this->infoRef = $this->addObject($info);
        return $this->infoRef;
    }

    /**
     * Set the encryption dictionary.
     */
    public function setEncrypt(PdfDictionary $encrypt): PdfReference
    {
        $this->encryptRef = $this->addObject($encrypt);
        return $this->encryptRef;
    }

    /**
     * Set encryption handler and key for encrypting streams/strings.
     */
    public function setEncryption(
        \PdfLib\Security\Encryption\EncryptionHandler $handler,
        string $key
    ): self {
        $this->encryptionHandler = $handler;
        $this->encryptionKey = $key;
        return $this;
    }

    /**
     * Check if encryption is enabled.
     */
    public function isEncrypted(): bool
    {
        return $this->encryptionHandler !== null;
    }

    /**
     * Get the stream writer for creating compressed streams.
     */
    public function getStreamWriter(): StreamWriter
    {
        return $this->streamWriter;
    }

    /**
     * Get the object writer.
     */
    public function getObjectWriter(): ObjectWriter
    {
        return $this->objectWriter;
    }

    /**
     * Get next available object number.
     */
    public function getNextObjectNumber(): int
    {
        return $this->nextObjectNumber;
    }

    /**
     * Write the complete PDF document to a string.
     */
    public function write(): string
    {
        if ($this->catalogRef === null) {
            throw WriteException::invalidObject('Document catalog not set');
        }

        $output = '';

        // Write header
        $output .= $this->writeHeader();

        // Track object offsets
        $offsets = [];

        // Write all objects
        foreach ($this->objects as $objNum => $entry) {
            $offsets[$objNum] = strlen($output);
            $output .= $this->objectWriter->writeIndirect(
                $entry['object'],
                $objNum,
                $entry['generation']
            );
        }

        // Record xref offset
        $xrefOffset = strlen($output);

        // Build xref entries
        $xrefEntries = [];

        // Object 0 is always free
        $xrefEntries[0] = [
            'offset' => 0,
            'generation' => 65535,
            'inUse' => false,
        ];

        foreach ($offsets as $objNum => $offset) {
            $xrefEntries[$objNum] = [
                'offset' => $offset,
                'generation' => $this->objects[$objNum]['generation'],
                'inUse' => true,
            ];
        }

        // Create trailer
        $trailer = $this->xrefWriter->createTrailer(
            $this->nextObjectNumber,
            $this->catalogRef,
            $this->infoRef,
            $this->xrefWriter->generateId(),
            $this->encryptRef
        );

        // Write xref and trailer
        $output .= $this->xrefWriter->write($xrefEntries, $trailer, $xrefOffset);

        // Write startxref
        $output .= "startxref\n$xrefOffset\n%%EOF\n";

        return $output;
    }

    /**
     * Write PDF to a file.
     */
    public function writeToFile(string $path): void
    {
        $content = $this->write();

        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw WriteException::fileError($path, 'Could not write to file');
        }
    }

    /**
     * Write PDF header.
     */
    private function writeHeader(): string
    {
        $header = "%PDF-{$this->version}\n";

        // Add binary marker (4 bytes with high bit set)
        // This helps identify the file as binary
        $header .= "%\xE2\xE3\xCF\xD3\n";

        return $header;
    }

    /**
     * Reset the writer for a new document.
     */
    public function reset(): self
    {
        $this->objects = [];
        $this->nextObjectNumber = 1;
        $this->catalogRef = null;
        $this->infoRef = null;
        $this->encryptRef = null;
        $this->encryptionHandler = null;
        $this->encryptionKey = '';
        return $this;
    }

    /**
     * Get encryption handler.
     */
    public function getEncryptionHandler(): ?\PdfLib\Security\Encryption\EncryptionHandler
    {
        return $this->encryptionHandler;
    }

    /**
     * Get encryption key.
     */
    public function getEncryptionKey(): string
    {
        return $this->encryptionKey;
    }

    /**
     * Create a simple PDF document with minimal content.
     */
    public static function createMinimalDocument(): self
    {
        $writer = new self();

        // Create empty page
        $mediaBox = \PdfLib\Parser\Object\PdfArray::fromValues([0, 0, 612, 792]); // Letter size

        $page = new PdfDictionary();
        $page->set('Type', PdfName::create('Page'));
        $page->set('MediaBox', $mediaBox);
        $pageRef = $writer->addObject($page);

        // Create pages dictionary
        $pages = new PdfDictionary();
        $pages->set('Type', PdfName::create('Pages'));
        $pages->set('Kids', \PdfLib\Parser\Object\PdfArray::fromValues([$pageRef]));
        $pages->set('Count', PdfNumber::int(1));
        $pagesRef = $writer->addObject($pages);

        // Update page parent
        $page->set('Parent', $pagesRef);

        // Create catalog
        $catalog = new PdfDictionary();
        $catalog->set('Pages', $pagesRef);
        $writer->setCatalog($catalog);

        // Create info
        $info = new PdfDictionary();
        $info->set('Producer', \PdfLib\Parser\Object\PdfString::literal('PdfLib'));
        $info->set('CreationDate', \PdfLib\Parser\Object\PdfString::literal(
            'D:' . date('YmdHis') . 'Z'
        ));
        $writer->setInfo($info);

        return $writer;
    }

    /**
     * Get all registered objects.
     *
     * @return array<int, array{object: PdfObject, generation: int}>
     */
    public function getObjects(): array
    {
        return $this->objects;
    }

    /**
     * Get an object by its number.
     */
    public function getObject(int $objectNumber): ?PdfObject
    {
        return $this->objects[$objectNumber]['object'] ?? null;
    }
}
