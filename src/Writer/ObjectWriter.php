<?php

declare(strict_types=1);

namespace PdfLib\Writer;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfBoolean;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNull;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfString;

/**
 * Writes PDF objects to a byte stream.
 */
final class ObjectWriter
{
    /**
     * Write a PDF object to string.
     */
    public function write(PdfObject $object): string
    {
        return match (true) {
            $object instanceof PdfNull => $this->writeNull(),
            $object instanceof PdfBoolean => $this->writeBoolean($object),
            $object instanceof PdfNumber => $this->writeNumber($object),
            $object instanceof PdfString => $this->writeString($object),
            $object instanceof PdfName => $this->writeName($object),
            $object instanceof PdfArray => $this->writeArray($object),
            $object instanceof PdfDictionary => $this->writeDictionary($object),
            $object instanceof PdfReference => $this->writeReference($object),
            $object instanceof PdfStream => $this->writeStream($object),
            default => throw new \InvalidArgumentException('Unknown PDF object type'),
        };
    }

    /**
     * Write an indirect object definition.
     */
    public function writeIndirect(PdfObject $object, int $objectNumber, int $generationNumber = 0): string
    {
        $content = $this->write($object);
        return sprintf("%d %d obj\n%s\nendobj\n", $objectNumber, $generationNumber, $content);
    }

    /**
     * Write null object.
     */
    private function writeNull(): string
    {
        return 'null';
    }

    /**
     * Write boolean object.
     */
    private function writeBoolean(PdfBoolean $object): string
    {
        return $object->getValue() ? 'true' : 'false';
    }

    /**
     * Write number object.
     */
    private function writeNumber(PdfNumber $object): string
    {
        $value = $object->getValue();

        if (is_int($value)) {
            return (string) $value;
        }

        // Format float without trailing zeros
        $formatted = sprintf('%.10f', $value);
        $formatted = rtrim($formatted, '0');
        $formatted = rtrim($formatted, '.');

        return $formatted;
    }

    /**
     * Write string object.
     */
    private function writeString(PdfString $object): string
    {
        return $object->toPdfString();
    }

    /**
     * Write name object.
     */
    private function writeName(PdfName $object): string
    {
        return $object->toPdfString();
    }

    /**
     * Write array object.
     */
    private function writeArray(PdfArray $object): string
    {
        $parts = [];
        foreach ($object as $item) {
            $parts[] = $this->write($item);
        }
        return '[' . implode(' ', $parts) . ']';
    }

    /**
     * Write dictionary object.
     */
    private function writeDictionary(PdfDictionary $object): string
    {
        if ($object->isEmpty()) {
            return '<< >>';
        }

        $parts = [];
        foreach ($object->getValue() as $key => $value) {
            $nameObj = PdfName::create($key);
            $parts[] = $this->writeName($nameObj) . ' ' . $this->write($value);
        }

        return "<<\n" . implode("\n", $parts) . "\n>>";
    }

    /**
     * Write indirect reference.
     */
    private function writeReference(PdfReference $object): string
    {
        return $object->toPdfString();
    }

    /**
     * Write stream object.
     */
    private function writeStream(PdfStream $object): string
    {
        $dict = $object->getDictionary();
        $data = $object->getData();

        // Update length in dictionary
        $dict->set(PdfName::LENGTH, PdfNumber::int(strlen($data)));

        return $this->writeDictionary($dict) . "\nstream\n" . $data . "\nendstream";
    }
}
