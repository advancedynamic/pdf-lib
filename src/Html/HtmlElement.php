<?php

declare(strict_types=1);

namespace PdfLib\Html;

/**
 * Represents a parsed HTML element.
 */
class HtmlElement
{
    private string $tagName;
    private string $content = '';
    private ?string $id = null;

    /** @var array<string> */
    private array $classes = [];

    /** @var array<string, string> */
    private array $attributes = [];

    /** @var array<string, string> */
    private array $styles = [];

    /** @var array<HtmlElement> */
    private array $children = [];

    private ?HtmlElement $parent = null;

    public function __construct(string $tagName, string $content = '')
    {
        $this->tagName = $tagName;
        $this->content = $content;
    }

    /**
     * Get tag name.
     */
    public function getTagName(): string
    {
        return $this->tagName;
    }

    /**
     * Get text content.
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Set text content.
     */
    public function setContent(string $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get element ID.
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set element ID.
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get CSS classes.
     *
     * @return array<string>
     */
    public function getClasses(): array
    {
        return $this->classes;
    }

    /**
     * Set CSS classes.
     *
     * @param array<string> $classes
     */
    public function setClasses(array $classes): self
    {
        $this->classes = array_filter(array_map('trim', $classes));

        return $this;
    }

    /**
     * Check if element has a class.
     */
    public function hasClass(string $class): bool
    {
        return in_array($class, $this->classes, true);
    }

    /**
     * Get an attribute value.
     */
    public function getAttribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * Set an attribute.
     */
    public function setAttribute(string $name, string $value): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * Check if element has an attribute.
     */
    public function hasAttribute(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * Get all attributes.
     *
     * @return array<string, string>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a style property value.
     */
    public function getStyle(string $property): ?string
    {
        return $this->styles[$property] ?? null;
    }

    /**
     * Set a style property.
     */
    public function setStyle(string $property, string $value): self
    {
        $this->styles[$property] = $value;

        return $this;
    }

    /**
     * Check if element has a style property.
     */
    public function hasStyle(string $property): bool
    {
        return isset($this->styles[$property]);
    }

    /**
     * Get all styles.
     *
     * @return array<string, string>
     */
    public function getStyles(): array
    {
        return $this->styles;
    }

    /**
     * Add a child element.
     */
    public function addChild(HtmlElement $child): self
    {
        $child->parent = $this;
        $this->children[] = $child;

        return $this;
    }

    /**
     * Get all children.
     *
     * @return array<HtmlElement>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Check if element has children.
     */
    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    /**
     * Get parent element.
     */
    public function getParent(): ?HtmlElement
    {
        return $this->parent;
    }

    /**
     * Check if this is a text element.
     */
    public function isText(): bool
    {
        return $this->tagName === 'text';
    }

    /**
     * Check if this is a block element.
     */
    public function isBlock(): bool
    {
        return in_array($this->tagName, [
            'div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'ul', 'ol', 'li', 'table', 'tr', 'blockquote',
            'pre', 'hr', 'header', 'footer', 'section', 'article',
            'nav', 'aside', 'figure', 'figcaption',
        ], true);
    }

    /**
     * Check if this is an inline element.
     */
    public function isInline(): bool
    {
        return in_array($this->tagName, [
            'span', 'a', 'strong', 'b', 'em', 'i', 'u',
            'code', 'sub', 'sup', 'small', 'mark', 'text',
        ], true);
    }

    /**
     * Check if this is a table element.
     */
    public function isTable(): bool
    {
        return $this->tagName === 'table';
    }

    /**
     * Check if this is a table row.
     */
    public function isTableRow(): bool
    {
        return $this->tagName === 'tr';
    }

    /**
     * Check if this is a table cell.
     */
    public function isTableCell(): bool
    {
        return in_array($this->tagName, ['td', 'th'], true);
    }

    /**
     * Check if this is a list element.
     */
    public function isList(): bool
    {
        return in_array($this->tagName, ['ul', 'ol'], true);
    }

    /**
     * Check if this is a list item.
     */
    public function isListItem(): bool
    {
        return $this->tagName === 'li';
    }

    /**
     * Check if this is an image.
     */
    public function isImage(): bool
    {
        return $this->tagName === 'img';
    }

    /**
     * Check if this is a line break.
     */
    public function isLineBreak(): bool
    {
        return $this->tagName === 'br';
    }

    /**
     * Check if this is a horizontal rule.
     */
    public function isHorizontalRule(): bool
    {
        return $this->tagName === 'hr';
    }

    /**
     * Check if this is a heading.
     */
    public function isHeading(): bool
    {
        return preg_match('/^h[1-6]$/', $this->tagName) === 1;
    }

    /**
     * Get heading level (1-6) or 0 if not a heading.
     */
    public function getHeadingLevel(): int
    {
        if (preg_match('/^h([1-6])$/', $this->tagName, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Get computed text content including children.
     */
    public function getTextContent(): string
    {
        if ($this->isText()) {
            return $this->content;
        }

        $text = '';
        foreach ($this->children as $child) {
            $text .= $child->getTextContent();
        }

        return $text;
    }

    /**
     * Get href for links.
     */
    public function getHref(): ?string
    {
        if ($this->tagName === 'a') {
            return $this->getAttribute('href');
        }

        return null;
    }

    /**
     * Get src for images.
     */
    public function getSrc(): ?string
    {
        if ($this->tagName === 'img') {
            return $this->getAttribute('src');
        }

        return null;
    }

    /**
     * Get colspan for table cells.
     */
    public function getColspan(): int
    {
        $colspan = $this->getAttribute('colspan');

        return $colspan !== null ? (int) $colspan : 1;
    }

    /**
     * Get rowspan for table cells.
     */
    public function getRowspan(): int
    {
        $rowspan = $this->getAttribute('rowspan');

        return $rowspan !== null ? (int) $rowspan : 1;
    }
}
