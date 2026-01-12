<?php

declare(strict_types=1);

namespace PdfLib\Content\Text;

use PdfLib\Content\ContentStream;

/**
 * Multi-line text paragraph with word wrapping.
 */
final class Paragraph
{
    private string $text;
    private TextStyle $style;
    private float $x = 0;
    private float $y = 0;
    private float $maxWidth = 0;
    private string $align = self::ALIGN_LEFT;
    private float $firstLineIndent = 0;
    private float $paragraphSpacing = 0;

    public const ALIGN_LEFT = 'left';
    public const ALIGN_CENTER = 'center';
    public const ALIGN_RIGHT = 'right';
    public const ALIGN_JUSTIFY = 'justify';

    public function __construct(string $text, ?TextStyle $style = null)
    {
        $this->text = $text;
        $this->style = $style ?? new TextStyle();
    }

    /**
     * Create a new paragraph.
     */
    public static function create(string $text, ?TextStyle $style = null): self
    {
        return new self($text, $style);
    }

    // Getters

    public function getText(): string
    {
        return $this->text;
    }

    public function getStyle(): TextStyle
    {
        return $this->style;
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function getMaxWidth(): float
    {
        return $this->maxWidth;
    }

    public function getAlign(): string
    {
        return $this->align;
    }

    public function getFirstLineIndent(): float
    {
        return $this->firstLineIndent;
    }

    public function getParagraphSpacing(): float
    {
        return $this->paragraphSpacing;
    }

    // Fluent setters

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function setStyle(TextStyle $style): self
    {
        $this->style = $style;
        return $this;
    }

    public function setPosition(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;
        return $this;
    }

    public function setMaxWidth(float $width): self
    {
        $this->maxWidth = $width;
        return $this;
    }

    public function setAlign(string $align): self
    {
        $this->align = $align;
        return $this;
    }

    public function setFirstLineIndent(float $indent): self
    {
        $this->firstLineIndent = $indent;
        return $this;
    }

    public function setParagraphSpacing(float $spacing): self
    {
        $this->paragraphSpacing = $spacing;
        return $this;
    }

    /**
     * Get the wrapped lines of text.
     *
     * @return array<int, string>
     */
    public function getLines(): array
    {
        if ($this->maxWidth <= 0) {
            // No wrapping - split only on newlines
            return explode("\n", $this->text);
        }

        $lines = [];
        $paragraphs = explode("\n", $this->text);

        foreach ($paragraphs as $paragraphIndex => $paragraph) {
            $words = preg_split('/\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);
            if ($words === false || count($words) === 0) {
                $lines[] = '';
                continue;
            }

            $currentLine = '';
            $isFirstLine = ($paragraphIndex === 0 || count($lines) === 0);
            $availableWidth = $isFirstLine
                ? $this->maxWidth - $this->firstLineIndent
                : $this->maxWidth;

            foreach ($words as $word) {
                $testLine = $currentLine === '' ? $word : $currentLine . ' ' . $word;
                $testWidth = $this->style->getTextWidth($testLine);

                if ($testWidth <= $availableWidth) {
                    $currentLine = $testLine;
                } else {
                    if ($currentLine !== '') {
                        $lines[] = $currentLine;
                        $availableWidth = $this->maxWidth;
                    }

                    // Handle very long words
                    if ($this->style->getTextWidth($word) > $availableWidth) {
                        $lines = array_merge($lines, $this->breakWord($word, $availableWidth));
                        $currentLine = '';
                    } else {
                        $currentLine = $word;
                    }
                }
            }

            if ($currentLine !== '') {
                $lines[] = $currentLine;
            }
        }

        return $lines;
    }

    /**
     * Break a long word that doesn't fit on a line.
     *
     * @return array<int, string>
     */
    private function breakWord(string $word, float $maxWidth): array
    {
        $lines = [];
        $current = '';
        $len = mb_strlen($word);

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($word, $i, 1);
            $test = $current . $char;

            if ($this->style->getTextWidth($test) <= $maxWidth) {
                $current = $test;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = $char;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Calculate the total height of the paragraph.
     */
    public function getHeight(): float
    {
        $lines = $this->getLines();
        $lineCount = count($lines);

        if ($lineCount === 0) {
            return 0;
        }

        $leading = $this->style->getLeading();
        return $this->style->getFontSize() + ($lineCount - 1) * $leading;
    }

    /**
     * Render the paragraph to a content stream.
     */
    public function render(ContentStream $stream): void
    {
        $lines = $this->getLines();
        if (count($lines) === 0) {
            return;
        }

        $style = $this->style;
        $fontName = $stream->registerFont($style->getFont());
        $leading = $style->getLeading();
        $fontSize = $style->getFontSize();

        $stream->saveState();
        $stream->setFillColor($style->getColor());
        $stream->beginText();
        $stream->setFont($fontName, $fontSize);
        $stream->setTextLeading($leading);

        if ($style->getCharacterSpacing() !== 0.0) {
            $stream->setCharacterSpacing($style->getCharacterSpacing());
        }

        $y = $this->y;

        foreach ($lines as $lineIndex => $line) {
            $isFirstLine = $lineIndex === 0;
            $isLastLine = $lineIndex === count($lines) - 1;

            // Calculate line X position based on alignment
            $lineWidth = $style->getTextWidth($line);
            $indent = $isFirstLine ? $this->firstLineIndent : 0;

            $lineX = match ($this->align) {
                self::ALIGN_CENTER => $this->x + ($this->maxWidth - $lineWidth) / 2,
                self::ALIGN_RIGHT => $this->x + $this->maxWidth - $lineWidth,
                self::ALIGN_JUSTIFY => $this->x + $indent,
                default => $this->x + $indent,
            };

            // Position text
            $stream->setTextMatrix(1, 0, 0, 1, $lineX, $y);

            // Handle justified text
            if ($this->align === self::ALIGN_JUSTIFY && !$isLastLine && $this->maxWidth > 0) {
                $this->renderJustifiedLine($stream, $line, $this->maxWidth - $indent, $style);
            } else {
                $stream->showText($line);
            }

            $y -= $leading;
        }

        $stream->endText();
        $stream->restoreState();
    }

    /**
     * Render a justified line of text.
     */
    private function renderJustifiedLine(
        ContentStream $stream,
        string $line,
        float $targetWidth,
        TextStyle $style
    ): void {
        $words = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || count($words) <= 1) {
            $stream->showText($line);
            return;
        }

        // Calculate current width without extra spacing
        $totalWordWidth = 0;
        foreach ($words as $word) {
            $totalWordWidth += $style->getTextWidth($word);
        }

        // Calculate extra space needed
        $extraSpace = $targetWidth - $totalWordWidth;
        $gaps = count($words) - 1;
        $extraWordSpacing = $gaps > 0 ? $extraSpace / $gaps : 0;

        // Apply word spacing and render
        $stream->setWordSpacing($extraWordSpacing + $style->getWordSpacing());
        $stream->showText(implode(' ', $words));
        $stream->setWordSpacing($style->getWordSpacing());
    }
}
