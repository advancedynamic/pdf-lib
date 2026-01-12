<?php

declare(strict_types=1);

namespace PdfLib\Form;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfObject;

/**
 * Individual radio button option within a group.
 *
 * Radio button options are widget annotations that belong to a parent
 * radio button group field.
 */
final class RadioButtonOption extends FormField
{
    // Radio button styles
    public const STYLE_CIRCLE = 'circle';
    public const STYLE_CHECK = 'check';
    public const STYLE_CROSS = 'cross';
    public const STYLE_DIAMOND = 'diamond';
    public const STYLE_SQUARE = 'square';
    public const STYLE_STAR = 'star';

    private string $groupName;
    private string $optionValue;
    private bool $selected = false;
    private string $radioStyle = self::STYLE_CIRCLE;

    public function __construct(string $groupName, string $value)
    {
        parent::__construct($groupName);
        $this->groupName = $groupName;
        $this->optionValue = $value;
        // Default radio button size
        $this->width = 16;
        $this->height = 16;
    }

    /**
     * Create a new radio button option.
     */
    public static function create(string $groupName, string $value): self
    {
        return new self($groupName, $value);
    }

    public function getFieldType(): string
    {
        return 'Btn';
    }

    protected function getTypeSpecificFlags(): int
    {
        return RadioButtonGroup::FLAG_RADIO;
    }

    protected function buildFieldSpecificEntries(PdfDictionary $dict): void
    {
        // Set appearance state
        $dict->set(
            'AS',
            $this->selected
                ? PdfName::create($this->optionValue)
                : PdfName::create('Off')
        );
    }

    public function getValue(): ?string
    {
        return $this->selected ? $this->optionValue : null;
    }

    public function setValue(mixed $value): static
    {
        $this->selected = ($value === $this->optionValue || $value === true);
        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return null;
    }

    /**
     * Override to return name for radio value.
     */
    protected function getValueForPdf(): ?PdfObject
    {
        return $this->selected
            ? PdfName::create($this->optionValue)
            : PdfName::create('Off');
    }

    /**
     * Get group name.
     */
    public function getGroupName(): string
    {
        return $this->groupName;
    }

    /**
     * Set group name.
     */
    public function setGroupName(string $groupName): self
    {
        $this->groupName = $groupName;
        $this->name = $groupName;
        return $this;
    }

    /**
     * Get option value.
     */
    public function getOptionValue(): string
    {
        return $this->optionValue;
    }

    /**
     * Check if selected.
     */
    public function isSelected(): bool
    {
        return $this->selected;
    }

    /**
     * Set selected state.
     */
    public function setSelected(bool $selected): self
    {
        $this->selected = $selected;
        return $this;
    }

    /**
     * Get radio style.
     */
    public function getRadioStyle(): string
    {
        return $this->radioStyle;
    }

    /**
     * Set radio style.
     */
    public function setRadioStyle(string $style): self
    {
        $validStyles = [
            self::STYLE_CIRCLE,
            self::STYLE_CHECK,
            self::STYLE_CROSS,
            self::STYLE_DIAMOND,
            self::STYLE_SQUARE,
            self::STYLE_STAR,
        ];
        if (in_array($style, $validStyles, true)) {
            $this->radioStyle = $style;
        }
        return $this;
    }

    public function generateAppearance(): string
    {
        return $this->generateAppearanceForState($this->selected);
    }

    /**
     * Generate appearance for selected state.
     */
    public function generateSelectedAppearance(): string
    {
        return $this->generateAppearanceForState(true);
    }

    /**
     * Generate appearance for unselected state.
     */
    public function generateUnselectedAppearance(): string
    {
        return $this->generateAppearanceForState(false);
    }

    /**
     * Generate appearance for a given state.
     */
    private function generateAppearanceForState(bool $selected): string
    {
        $content = '';
        $width = $this->width;
        $height = $this->height;
        $cx = $width / 2;
        $cy = $height / 2;
        $radius = min($width, $height) / 2 - 1;

        // Draw circle border
        if ($this->borderColor !== null) {
            $content .= sprintf(
                '%.3f %.3f %.3f RG ',
                $this->borderColor[0],
                $this->borderColor[1],
                $this->borderColor[2]
            );
        } else {
            $content .= '0 G ';  // Black border
        }
        $content .= sprintf('%.4f w ', $this->borderWidth);

        // Draw circle using bezier curves (approximation)
        $kappa = 0.5522847498;
        $ox = $radius * $kappa;
        $oy = $radius * $kappa;

        $content .= sprintf('%.4f %.4f m ', $cx + $radius, $cy);
        $content .= sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f c ',
            $cx + $radius, $cy + $oy,
            $cx + $ox, $cy + $radius,
            $cx, $cy + $radius
        );
        $content .= sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f c ',
            $cx - $ox, $cy + $radius,
            $cx - $radius, $cy + $oy,
            $cx - $radius, $cy
        );
        $content .= sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f c ',
            $cx - $radius, $cy - $oy,
            $cx - $ox, $cy - $radius,
            $cx, $cy - $radius
        );
        $content .= sprintf(
            '%.4f %.4f %.4f %.4f %.4f %.4f c ',
            $cx + $ox, $cy - $radius,
            $cx + $radius, $cy - $oy,
            $cx + $radius, $cy
        );
        $content .= 'S ';

        // Draw filled inner circle if selected
        if ($selected) {
            $innerRadius = $radius * 0.5;
            $iox = $innerRadius * $kappa;
            $ioy = $innerRadius * $kappa;

            $content .= '0 g ';  // Black fill
            $content .= sprintf('%.4f %.4f m ', $cx + $innerRadius, $cy);
            $content .= sprintf(
                '%.4f %.4f %.4f %.4f %.4f %.4f c ',
                $cx + $innerRadius, $cy + $ioy,
                $cx + $iox, $cy + $innerRadius,
                $cx, $cy + $innerRadius
            );
            $content .= sprintf(
                '%.4f %.4f %.4f %.4f %.4f %.4f c ',
                $cx - $iox, $cy + $innerRadius,
                $cx - $innerRadius, $cy + $ioy,
                $cx - $innerRadius, $cy
            );
            $content .= sprintf(
                '%.4f %.4f %.4f %.4f %.4f %.4f c ',
                $cx - $innerRadius, $cy - $ioy,
                $cx - $iox, $cy - $innerRadius,
                $cx, $cy - $innerRadius
            );
            $content .= sprintf(
                '%.4f %.4f %.4f %.4f %.4f %.4f c ',
                $cx + $iox, $cy - $innerRadius,
                $cx + $innerRadius, $cy - $ioy,
                $cx + $innerRadius, $cy
            );
            $content .= 'f ';
        }

        return trim($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'groupName' => $this->groupName,
            'optionValue' => $this->optionValue,
            'selected' => $this->selected,
            'radioStyle' => $this->radioStyle,
        ]);
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $option = new self(
            $data['groupName'] ?? 'RadioGroup',
            $data['optionValue'] ?? 'option'
        );
        $option->fromArrayBase($data);

        if (isset($data['selected'])) {
            $option->selected = (bool) $data['selected'];
        }
        if (isset($data['radioStyle'])) {
            $option->radioStyle = (string) $data['radioStyle'];
        }

        return $option;
    }
}
