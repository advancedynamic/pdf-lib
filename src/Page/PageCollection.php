<?php

declare(strict_types=1);

namespace PdfLib\Page;

use ArrayAccess;
use Countable;
use Iterator;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfReference;

/**
 * Manages a collection of PDF pages (page tree).
 *
 * @implements ArrayAccess<int, Page>
 * @implements Iterator<int, Page>
 */
final class PageCollection implements ArrayAccess, Countable, Iterator
{
    /** @var array<int, Page> */
    private array $pages = [];

    private int $position = 0;

    /**
     * Add a page to the collection.
     */
    public function add(Page $page): self
    {
        $this->pages[] = $page;
        return $this;
    }

    /**
     * Insert a page at a specific index.
     */
    public function insertAt(int $index, Page $page): self
    {
        array_splice($this->pages, $index, 0, [$page]);
        return $this;
    }

    /**
     * Remove a page at a specific index.
     */
    public function removeAt(int $index): self
    {
        if (isset($this->pages[$index])) {
            array_splice($this->pages, $index, 1);
        }
        return $this;
    }

    /**
     * Get a page by index.
     */
    public function get(int $index): ?Page
    {
        return $this->pages[$index] ?? null;
    }

    /**
     * Get the first page.
     */
    public function first(): ?Page
    {
        return $this->pages[0] ?? null;
    }

    /**
     * Get the last page.
     */
    public function last(): ?Page
    {
        $count = count($this->pages);
        return $count > 0 ? $this->pages[$count - 1] : null;
    }

    /**
     * Get all pages.
     *
     * @return array<int, Page>
     */
    public function all(): array
    {
        return $this->pages;
    }

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this->pages) === 0;
    }

    /**
     * Move a page from one position to another.
     */
    public function move(int $from, int $to): self
    {
        if (!isset($this->pages[$from])) {
            return $this;
        }

        $page = $this->pages[$from];
        array_splice($this->pages, $from, 1);
        array_splice($this->pages, $to, 0, [$page]);

        return $this;
    }

    /**
     * Reverse page order.
     */
    public function reverse(): self
    {
        $this->pages = array_reverse($this->pages);
        return $this;
    }

    /**
     * Extract a range of pages into a new collection.
     */
    public function slice(int $start, ?int $length = null): self
    {
        $collection = new self();
        $sliced = array_slice($this->pages, $start, $length);
        foreach ($sliced as $page) {
            $collection->add($page);
        }
        return $collection;
    }

    /**
     * Extract specific page numbers into a new collection.
     *
     * @param array<int, int> $pageNumbers 0-indexed page numbers
     */
    public function extract(array $pageNumbers): self
    {
        $collection = new self();
        foreach ($pageNumbers as $pageNum) {
            $page = $this->get($pageNum);
            if ($page !== null) {
                $collection->add($page);
            }
        }
        return $collection;
    }

    /**
     * Extract odd pages (1st, 3rd, 5th...) into a new collection.
     */
    public function oddPages(): self
    {
        $collection = new self();
        foreach ($this->pages as $index => $page) {
            if ($index % 2 === 0) { // 0-indexed, so 0 is 1st page
                $collection->add($page);
            }
        }
        return $collection;
    }

    /**
     * Extract even pages (2nd, 4th, 6th...) into a new collection.
     */
    public function evenPages(): self
    {
        $collection = new self();
        foreach ($this->pages as $index => $page) {
            if ($index % 2 === 1) { // 0-indexed, so 1 is 2nd page
                $collection->add($page);
            }
        }
        return $collection;
    }

    /**
     * Append another collection.
     */
    public function append(self $other): self
    {
        foreach ($other->pages as $page) {
            $this->pages[] = $page;
        }
        return $this;
    }

    /**
     * Prepend another collection.
     */
    public function prepend(self $other): self
    {
        $this->pages = array_merge($other->pages, $this->pages);
        return $this;
    }

    /**
     * Apply a rotation to all pages.
     */
    public function rotateAll(int $degrees): self
    {
        foreach ($this->pages as $page) {
            $page->setRotation($page->getRotation() + $degrees);
        }
        return $this;
    }

    /**
     * Create Pages dictionary for the page tree.
     *
     * @param array<int, PdfReference> $pageRefs References to page objects
     */
    public function toPagesDict(array $pageRefs): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', PdfName::create('Pages'));
        $dict->set('Kids', new PdfArray($pageRefs));
        $dict->set('Count', PdfNumber::int(count($pageRefs)));

        return $dict;
    }

    // ArrayAccess implementation

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->pages[$offset]);
    }

    public function offsetGet(mixed $offset): ?Page
    {
        return $this->pages[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->pages[] = $value;
        } else {
            $this->pages[$offset] = $value;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->pages[$offset]);
        $this->pages = array_values($this->pages);
    }

    // Countable implementation

    public function count(): int
    {
        return count($this->pages);
    }

    // Iterator implementation

    public function current(): Page
    {
        return $this->pages[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->pages[$this->position]);
    }
}
