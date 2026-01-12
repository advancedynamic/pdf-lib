<?php

declare(strict_types=1);

namespace PdfLib\Export\Docx;

/**
 * Represents a run of text in a DOCX document.
 *
 * A run is a contiguous piece of text with the same formatting.
 */
class DocxRun
{
    private string $text;
    private bool $bold = false;
    private bool $italic = false;
    private bool $underline = false;
    private bool $strike = false;
    private ?string $font = null;
    private ?int $fontSize = null;      // In half-points
    private ?string $color = null;      // Hex without #
    private ?string $highlight = null;  // Highlight color name
    private bool $preserveSpaces = false;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    /**
     * Set bold.
     */
    public function setBold(bool $bold): self
    {
        $this->bold = $bold;

        return $this;
    }

    /**
     * Set italic.
     */
    public function setItalic(bool $italic): self
    {
        $this->italic = $italic;

        return $this;
    }

    /**
     * Set underline.
     */
    public function setUnderline(bool $underline): self
    {
        $this->underline = $underline;

        return $this;
    }

    /**
     * Set strikethrough.
     */
    public function setStrike(bool $strike): self
    {
        $this->strike = $strike;

        return $this;
    }

    /**
     * Set font name.
     */
    public function setFont(string $font): self
    {
        $this->font = $font;

        return $this;
    }

    /**
     * Set font size in points.
     */
    public function setFontSize(int $sizePt): self
    {
        $this->fontSize = $sizePt * 2; // Convert to half-points

        return $this;
    }

    /**
     * Set font size in half-points.
     */
    public function setFontSizeHalfPoints(int $size): self
    {
        $this->fontSize = $size;

        return $this;
    }

    /**
     * Set text color.
     *
     * @param string $color Hex color (with or without #)
     */
    public function setColor(string $color): self
    {
        $this->color = ltrim($color, '#');

        return $this;
    }

    /**
     * Set highlight color.
     *
     * @param string $highlight Color name (yellow, green, cyan, magenta, blue, red, darkBlue, etc.)
     */
    public function setHighlight(string $highlight): self
    {
        $this->highlight = $highlight;

        return $this;
    }

    /**
     * Set whether to preserve spaces.
     */
    public function setPreserveSpaces(bool $preserve): self
    {
        $this->preserveSpaces = $preserve;

        return $this;
    }

    /**
     * Convert to OOXML.
     */
    public function toXml(): string
    {
        $xml = '<w:r>';

        // Run properties
        $rPr = $this->getRunPropertiesXml();
        if ($rPr !== '') {
            $xml .= '<w:rPr>' . $rPr . '</w:rPr>';
        }

        // Text content
        $escapedText = htmlspecialchars($this->text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        if ($this->preserveSpaces || str_contains($this->text, '  ') || str_starts_with($this->text, ' ') || str_ends_with($this->text, ' ')) {
            $xml .= '<w:t xml:space="preserve">' . $escapedText . '</w:t>';
        } else {
            $xml .= '<w:t>' . $escapedText . '</w:t>';
        }

        $xml .= '</w:r>';

        return $xml;
    }

    /**
     * Get run properties XML.
     */
    private function getRunPropertiesXml(): string
    {
        $props = '';

        if ($this->font !== null) {
            $props .= '<w:rFonts w:ascii="' . $this->font . '" w:hAnsi="' . $this->font . '"/>';
        }

        if ($this->bold) {
            $props .= '<w:b/>';
        }

        if ($this->italic) {
            $props .= '<w:i/>';
        }

        if ($this->underline) {
            $props .= '<w:u w:val="single"/>';
        }

        if ($this->strike) {
            $props .= '<w:strike/>';
        }

        if ($this->fontSize !== null) {
            $props .= '<w:sz w:val="' . $this->fontSize . '"/>';
            $props .= '<w:szCs w:val="' . $this->fontSize . '"/>';
        }

        if ($this->color !== null) {
            $props .= '<w:color w:val="' . $this->color . '"/>';
        }

        if ($this->highlight !== null) {
            $props .= '<w:highlight w:val="' . $this->highlight . '"/>';
        }

        return $props;
    }
}
