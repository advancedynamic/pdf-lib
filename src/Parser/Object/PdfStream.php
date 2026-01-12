<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF Stream object.
 *
 * A stream consists of a dictionary followed by a sequence of bytes.
 * Streams are used for content that may be compressed or encoded.
 *
 * Common stream types:
 * - Content streams (page content)
 * - Image data
 * - Embedded fonts
 * - Metadata
 */
final class PdfStream extends PdfObject
{
    public function __construct(
        private PdfDictionary $dictionary,
        private string $data,
        private bool $isDecoded = false
    ) {
    }

    /**
     * Create a stream from raw data (will be encoded on output).
     */
    public static function fromData(string $data, ?PdfDictionary $dictionary = null): self
    {
        $dict = $dictionary ?? new PdfDictionary();
        return new self($dict, $data, true);
    }

    /**
     * Create a stream from encoded data (as read from PDF).
     */
    public static function fromEncoded(PdfDictionary $dictionary, string $encodedData): self
    {
        return new self($dictionary, $encodedData, false);
    }

    /**
     * @return array{dictionary: PdfDictionary, data: string}
     */
    public function getValue(): array
    {
        return [
            'dictionary' => $this->dictionary,
            'data' => $this->data,
        ];
    }

    /**
     * Get the stream dictionary.
     */
    public function getDictionary(): PdfDictionary
    {
        return $this->dictionary;
    }

    /**
     * Get the raw stream data.
     */
    public function getData(): string
    {
        return $this->data;
    }

    /**
     * Set the stream data.
     */
    public function setData(string $data): self
    {
        $this->data = $data;
        $this->isDecoded = true;
        return $this;
    }

    /**
     * Check if the data is already decoded.
     */
    public function isDecoded(): bool
    {
        return $this->isDecoded;
    }

    /**
     * Get the length of the raw data.
     */
    public function getLength(): int
    {
        return strlen($this->data);
    }

    /**
     * Get the filter(s) applied to this stream.
     *
     * @return array<int, string>
     */
    public function getFilters(): array
    {
        $filter = $this->dictionary->get(PdfName::FILTER);

        if ($filter === null) {
            return [];
        }

        if ($filter instanceof PdfName) {
            return [$filter->getValue()];
        }

        if ($filter instanceof PdfArray) {
            $filters = [];
            foreach ($filter as $item) {
                if ($item instanceof PdfName) {
                    $filters[] = $item->getValue();
                }
            }
            return $filters;
        }

        return [];
    }

    /**
     * Set the filter for this stream.
     *
     * @param string|array<int, string> $filters
     */
    public function setFilter(string|array $filters): self
    {
        if (is_string($filters)) {
            $this->dictionary->set(PdfName::FILTER, PdfName::create($filters));
        } else {
            $filterArray = new PdfArray(
                array_map(fn (string $f) => PdfName::create($f), $filters)
            );
            $this->dictionary->set(PdfName::FILTER, $filterArray);
        }
        return $this;
    }

    /**
     * Get a dictionary entry.
     */
    public function get(string $key): ?PdfObject
    {
        return $this->dictionary->get($key);
    }

    /**
     * Set a dictionary entry.
     */
    public function set(string $key, PdfObject $value): self
    {
        $this->dictionary->set($key, $value);
        return $this;
    }

    public function toPdfString(): string
    {
        // Update length in dictionary
        $this->dictionary->set(PdfName::LENGTH, PdfNumber::int(strlen($this->data)));

        return $this->dictionary->toPdfString() . "\nstream\n" . $this->data . "\nendstream";
    }
}
