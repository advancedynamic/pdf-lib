<?php

declare(strict_types=1);

namespace PdfLib\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Parses HTML into a structured element tree.
 */
class HtmlParser
{
    private string $html;
    private string $encoding;

    /** @var array<string, string> Extracted CSS styles */
    private array $styles = [];

    public function __construct(string $html, string $encoding = 'UTF-8')
    {
        $this->html = $html;
        $this->encoding = $encoding;
    }

    /**
     * Parse HTML and return element tree.
     *
     * @return array<HtmlElement>
     */
    public function parse(): array
    {
        $dom = new DOMDocument();

        // Suppress warnings for malformed HTML
        libxml_use_internal_errors(true);

        // Wrap in proper HTML structure if needed
        $html = $this->prepareHtml($this->html);

        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();

        // Extract styles from <style> tags
        $this->extractStyles($dom);

        // Find body or root element
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body === null) {
            $body = $dom->documentElement;
        }

        if ($body === null) {
            return [];
        }

        return $this->parseNode($body);
    }

    /**
     * Prepare HTML for parsing.
     */
    private function prepareHtml(string $html): string
    {
        // Add encoding meta tag if not present
        if (stripos($html, '<meta') === false) {
            $html = '<?xml encoding="' . $this->encoding . '">' . $html;
        }

        // Wrap in basic structure if no html/body tags
        if (stripos($html, '<body') === false && stripos($html, '<html') === false) {
            $html = '<html><body>' . $html . '</body></html>';
        }

        return $html;
    }

    /**
     * Extract CSS from style tags.
     */
    private function extractStyles(DOMDocument $dom): void
    {
        $styleTags = $dom->getElementsByTagName('style');

        foreach ($styleTags as $styleTag) {
            $css = $styleTag->textContent;
            $this->parseCss($css);
        }
    }

    /**
     * Parse CSS string into styles array.
     */
    private function parseCss(string $css): void
    {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);

        // Parse rules
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $selectors = array_map('trim', explode(',', $match[1]));
            $rules = $match[2];

            foreach ($selectors as $selector) {
                $this->styles[$selector] = ($this->styles[$selector] ?? '') . $rules;
            }
        }
    }

    /**
     * Parse a DOM node and its children.
     *
     * @return array<HtmlElement>
     */
    private function parseNode(DOMNode $node): array
    {
        $elements = [];

        foreach ($node->childNodes as $child) {
            $element = $this->parseChildNode($child);
            if ($element !== null) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * Parse a child node.
     */
    private function parseChildNode(DOMNode $node): ?HtmlElement
    {
        if ($node instanceof DOMText) {
            $text = trim($node->textContent);
            if ($text !== '') {
                return new HtmlElement('text', $text);
            }
            return null;
        }

        if (!$node instanceof DOMElement) {
            return null;
        }

        $tagName = strtolower($node->tagName);

        // Skip certain elements
        if (in_array($tagName, ['script', 'style', 'head', 'meta', 'link', 'title'], true)) {
            return null;
        }

        $element = new HtmlElement($tagName);

        // Parse attributes
        $this->parseAttributes($element, $node);

        // Parse inline styles
        $this->parseInlineStyle($element, $node);

        // Apply CSS from style tags
        $this->applyCssStyles($element, $tagName, $node);

        // Parse children
        $children = $this->parseNode($node);
        foreach ($children as $child) {
            $element->addChild($child);
        }

        // For self-closing or text-containing elements
        if ($tagName === 'br') {
            $element->setContent("\n");
        } elseif ($tagName === 'img') {
            $element->setContent($node->getAttribute('src'));
        } elseif (in_array($tagName, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li', 'td', 'th', 'span', 'strong', 'em', 'b', 'i', 'u', 'a'], true)) {
            // Collect text content
            $textContent = $this->getTextContent($node);
            if ($textContent !== '' && empty($children)) {
                $element->setContent($textContent);
            }
        }

        return $element;
    }

    /**
     * Get text content of a node.
     */
    private function getTextContent(DOMNode $node): string
    {
        $text = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= $child->textContent;
            }
        }
        return trim($text);
    }

    /**
     * Parse element attributes.
     */
    private function parseAttributes(HtmlElement $element, DOMElement $node): void
    {
        // Common attributes
        if ($node->hasAttribute('id')) {
            $element->setId($node->getAttribute('id'));
        }

        if ($node->hasAttribute('class')) {
            $element->setClasses(explode(' ', $node->getAttribute('class')));
        }

        if ($node->hasAttribute('href')) {
            $element->setAttribute('href', $node->getAttribute('href'));
        }

        if ($node->hasAttribute('src')) {
            $element->setAttribute('src', $node->getAttribute('src'));
        }

        if ($node->hasAttribute('width')) {
            $element->setAttribute('width', $node->getAttribute('width'));
        }

        if ($node->hasAttribute('height')) {
            $element->setAttribute('height', $node->getAttribute('height'));
        }

        if ($node->hasAttribute('alt')) {
            $element->setAttribute('alt', $node->getAttribute('alt'));
        }

        if ($node->hasAttribute('align')) {
            $element->setStyle('text-align', $node->getAttribute('align'));
        }

        if ($node->hasAttribute('valign')) {
            $element->setStyle('vertical-align', $node->getAttribute('valign'));
        }

        if ($node->hasAttribute('bgcolor')) {
            $element->setStyle('background-color', $node->getAttribute('bgcolor'));
        }

        if ($node->hasAttribute('border')) {
            $border = $node->getAttribute('border');
            if ($border !== '0') {
                $element->setStyle('border', $border . 'px solid #000000');
            }
        }

        if ($node->hasAttribute('cellpadding')) {
            $element->setStyle('padding', $node->getAttribute('cellpadding') . 'px');
        }

        if ($node->hasAttribute('colspan')) {
            $element->setAttribute('colspan', $node->getAttribute('colspan'));
        }

        if ($node->hasAttribute('rowspan')) {
            $element->setAttribute('rowspan', $node->getAttribute('rowspan'));
        }
    }

    /**
     * Parse inline style attribute.
     */
    private function parseInlineStyle(HtmlElement $element, DOMElement $node): void
    {
        if (!$node->hasAttribute('style')) {
            return;
        }

        $styleStr = $node->getAttribute('style');
        $styles = $this->parseStyleString($styleStr);

        foreach ($styles as $property => $value) {
            $element->setStyle($property, $value);
        }
    }

    /**
     * Apply CSS styles from style tags.
     */
    private function applyCssStyles(HtmlElement $element, string $tagName, DOMElement $node): void
    {
        // Tag selector
        if (isset($this->styles[$tagName])) {
            $styles = $this->parseStyleString($this->styles[$tagName]);
            foreach ($styles as $property => $value) {
                if (!$element->hasStyle($property)) {
                    $element->setStyle($property, $value);
                }
            }
        }

        // Class selectors
        if ($node->hasAttribute('class')) {
            $classes = explode(' ', $node->getAttribute('class'));
            foreach ($classes as $class) {
                $selector = '.' . trim($class);
                if (isset($this->styles[$selector])) {
                    $styles = $this->parseStyleString($this->styles[$selector]);
                    foreach ($styles as $property => $value) {
                        if (!$element->hasStyle($property)) {
                            $element->setStyle($property, $value);
                        }
                    }
                }
            }
        }

        // ID selector
        if ($node->hasAttribute('id')) {
            $selector = '#' . $node->getAttribute('id');
            if (isset($this->styles[$selector])) {
                $styles = $this->parseStyleString($this->styles[$selector]);
                foreach ($styles as $property => $value) {
                    if (!$element->hasStyle($property)) {
                        $element->setStyle($property, $value);
                    }
                }
            }
        }
    }

    /**
     * Parse a CSS style string into property => value array.
     *
     * @return array<string, string>
     */
    private function parseStyleString(string $styleStr): array
    {
        $styles = [];
        $declarations = array_filter(array_map('trim', explode(';', $styleStr)));

        foreach ($declarations as $declaration) {
            $parts = explode(':', $declaration, 2);
            if (count($parts) === 2) {
                $property = trim($parts[0]);
                $value = trim($parts[1]);
                $styles[$property] = $value;
            }
        }

        return $styles;
    }
}
