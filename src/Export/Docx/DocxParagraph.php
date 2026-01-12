<?php

declare(strict_types=1);

namespace PdfLib\Export\Docx;

/**
 * Represents a paragraph in a DOCX document.
 */
class DocxParagraph
{
    private ?string $style = null;
    private bool $pageBreak = false;

    // Paragraph properties
    private ?string $alignment = null;  // left, center, right, both (justified)
    private ?int $spacingBefore = null; // In twips
    private ?int $spacingAfter = null;  // In twips
    private ?int $lineSpacing = null;   // In twips
    private ?int $indentLeft = null;    // In twips
    private ?int $indentRight = null;   // In twips
    private ?int $indentFirst = null;   // In twips (first line indent)

    /** @var array<DocxRun> */
    private array $runs = [];

    /**
     * Add a text run.
     */
    public function addRun(
        string $text,
        bool $bold = false,
        bool $italic = false,
        bool $underline = false,
        ?string $fontName = null,
        ?int $fontSize = null,
        ?string $color = null
    ): self {
        $run = new DocxRun($text);
        $run->setBold($bold);
        $run->setItalic($italic);
        $run->setUnderline($underline);

        if ($fontName !== null) {
            $run->setFont($fontName);
        }
        if ($fontSize !== null) {
            $run->setFontSize($fontSize);
        }
        if ($color !== null) {
            $run->setColor($color);
        }

        $this->runs[] = $run;

        return $this;
    }

    /**
     * Add a run object.
     */
    public function addRunObject(DocxRun $run): self
    {
        $this->runs[] = $run;

        return $this;
    }

    /**
     * Set paragraph style.
     */
    public function setStyle(string $style): self
    {
        $this->style = $style;

        return $this;
    }

    /**
     * Set page break before this paragraph.
     */
    public function setPageBreak(bool $pageBreak): self
    {
        $this->pageBreak = $pageBreak;

        return $this;
    }

    /**
     * Set alignment.
     */
    public function setAlignment(string $alignment): self
    {
        $this->alignment = $alignment;

        return $this;
    }

    /**
     * Set spacing before paragraph in points.
     */
    public function setSpacingBefore(float $points): self
    {
        $this->spacingBefore = (int) ($points * 20); // Convert to twips

        return $this;
    }

    /**
     * Set spacing after paragraph in points.
     */
    public function setSpacingAfter(float $points): self
    {
        $this->spacingAfter = (int) ($points * 20);

        return $this;
    }

    /**
     * Set line spacing in points.
     */
    public function setLineSpacing(float $points): self
    {
        $this->lineSpacing = (int) ($points * 20);

        return $this;
    }

    /**
     * Set left indent in points.
     */
    public function setIndentLeft(float $points): self
    {
        $this->indentLeft = (int) ($points * 20);

        return $this;
    }

    /**
     * Set right indent in points.
     */
    public function setIndentRight(float $points): self
    {
        $this->indentRight = (int) ($points * 20);

        return $this;
    }

    /**
     * Set first line indent in points.
     */
    public function setIndentFirst(float $points): self
    {
        $this->indentFirst = (int) ($points * 20);

        return $this;
    }

    /**
     * Convert to OOXML.
     */
    public function toXml(): string
    {
        $xml = '        <w:p>';

        // Paragraph properties
        $pPr = $this->getParagraphPropertiesXml();
        if ($pPr !== '') {
            $xml .= "\n            <w:pPr>" . $pPr . "</w:pPr>";
        }

        // Runs
        foreach ($this->runs as $run) {
            $xml .= "\n            " . $run->toXml();
        }

        // Page break
        if ($this->pageBreak) {
            $xml .= "\n            <w:r><w:br w:type=\"page\"/></w:r>";
        }

        $xml .= "\n        </w:p>\n";

        return $xml;
    }

    /**
     * Get paragraph properties XML.
     */
    private function getParagraphPropertiesXml(): string
    {
        $props = '';

        if ($this->style !== null) {
            $props .= "<w:pStyle w:val=\"{$this->style}\"/>";
        }

        if ($this->alignment !== null) {
            $props .= "<w:jc w:val=\"{$this->alignment}\"/>";
        }

        // Spacing
        $spacing = '';
        if ($this->spacingBefore !== null) {
            $spacing .= " w:before=\"{$this->spacingBefore}\"";
        }
        if ($this->spacingAfter !== null) {
            $spacing .= " w:after=\"{$this->spacingAfter}\"";
        }
        if ($this->lineSpacing !== null) {
            $spacing .= " w:line=\"{$this->lineSpacing}\" w:lineRule=\"auto\"";
        }
        if ($spacing !== '') {
            $props .= "<w:spacing{$spacing}/>";
        }

        // Indentation
        $indent = '';
        if ($this->indentLeft !== null) {
            $indent .= " w:left=\"{$this->indentLeft}\"";
        }
        if ($this->indentRight !== null) {
            $indent .= " w:right=\"{$this->indentRight}\"";
        }
        if ($this->indentFirst !== null) {
            $indent .= " w:firstLine=\"{$this->indentFirst}\"";
        }
        if ($indent !== '') {
            $props .= "<w:ind{$indent}/>";
        }

        return $props;
    }
}
