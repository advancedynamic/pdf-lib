<?php

declare(strict_types=1);

namespace PdfLib\Security\Encryption;

/**
 * PDF document permissions flags.
 *
 * Controls what operations are allowed on an encrypted PDF document.
 * Permissions are enforced by PDF readers that respect encryption.
 *
 * @see PDF Reference 1.7, Table 3.20 (User access permissions)
 */
final class Permissions
{
    // Permission bit positions (1-indexed in PDF spec, 0-indexed internally)
    public const BIT_PRINT = 2;           // Bit 3: Print document
    public const BIT_MODIFY = 3;          // Bit 4: Modify contents
    public const BIT_COPY = 4;            // Bit 5: Copy/extract content
    public const BIT_ANNOT_FORMS = 5;     // Bit 6: Add/modify annotations, fill forms
    public const BIT_FILL_FORMS = 8;      // Bit 9: Fill form fields (even if bit 6 is off)
    public const BIT_EXTRACT = 9;         // Bit 10: Extract content for accessibility
    public const BIT_ASSEMBLE = 10;       // Bit 11: Assemble document (insert, rotate, delete pages)
    public const BIT_PRINT_HIGH = 11;     // Bit 12: Print in high quality

    // Default permission value with all permissions denied
    // Bits 1-2 must be 0, bits 7-8 must be 1, bits 13-32 must be 1
    private const BASE_PERMISSIONS = 0xFFFFF0C0;

    private int $permissions;

    public function __construct()
    {
        // Start with all permissions denied
        $this->permissions = self::BASE_PERMISSIONS;
    }

    /**
     * Create with all permissions allowed.
     */
    public static function allowAll(): self
    {
        $instance = new self();
        return $instance->allowPrinting()
            ->allowHighQualityPrinting()
            ->allowModifying()
            ->allowCopying()
            ->allowAnnotations()
            ->allowFormFilling()
            ->allowExtraction()
            ->allowAssembly();
    }

    /**
     * Create with all permissions denied.
     */
    public static function denyAll(): self
    {
        return new self();
    }

    /**
     * Allow printing.
     */
    public function allowPrinting(bool $allow = true): self
    {
        return $this->setBit(self::BIT_PRINT, $allow);
    }

    /**
     * Allow high-quality printing.
     */
    public function allowHighQualityPrinting(bool $allow = true): self
    {
        return $this->setBit(self::BIT_PRINT_HIGH, $allow);
    }

    /**
     * Allow modifying document contents.
     */
    public function allowModifying(bool $allow = true): self
    {
        return $this->setBit(self::BIT_MODIFY, $allow);
    }

    /**
     * Allow copying/extracting content.
     */
    public function allowCopying(bool $allow = true): self
    {
        return $this->setBit(self::BIT_COPY, $allow);
    }

    /**
     * Allow adding/modifying annotations and form fields.
     */
    public function allowAnnotations(bool $allow = true): self
    {
        return $this->setBit(self::BIT_ANNOT_FORMS, $allow);
    }

    /**
     * Allow filling form fields.
     */
    public function allowFormFilling(bool $allow = true): self
    {
        return $this->setBit(self::BIT_FILL_FORMS, $allow);
    }

    /**
     * Allow extracting content for accessibility.
     */
    public function allowExtraction(bool $allow = true): self
    {
        return $this->setBit(self::BIT_EXTRACT, $allow);
    }

    /**
     * Allow document assembly (insert, rotate, delete pages).
     */
    public function allowAssembly(bool $allow = true): self
    {
        return $this->setBit(self::BIT_ASSEMBLE, $allow);
    }

    /**
     * Check if printing is allowed.
     */
    public function canPrint(): bool
    {
        return $this->getBit(self::BIT_PRINT);
    }

    /**
     * Check if high-quality printing is allowed.
     */
    public function canPrintHighQuality(): bool
    {
        return $this->getBit(self::BIT_PRINT_HIGH);
    }

    /**
     * Check if modifying is allowed.
     */
    public function canModify(): bool
    {
        return $this->getBit(self::BIT_MODIFY);
    }

    /**
     * Check if copying is allowed.
     */
    public function canCopy(): bool
    {
        return $this->getBit(self::BIT_COPY);
    }

    /**
     * Check if annotations are allowed.
     */
    public function canAnnotate(): bool
    {
        return $this->getBit(self::BIT_ANNOT_FORMS);
    }

    /**
     * Check if form filling is allowed.
     */
    public function canFillForms(): bool
    {
        return $this->getBit(self::BIT_FILL_FORMS);
    }

    /**
     * Check if extraction is allowed.
     */
    public function canExtract(): bool
    {
        return $this->getBit(self::BIT_EXTRACT);
    }

    /**
     * Check if assembly is allowed.
     */
    public function canAssemble(): bool
    {
        return $this->getBit(self::BIT_ASSEMBLE);
    }

    /**
     * Get the raw permissions value (for PDF encryption).
     */
    public function getValue(): int
    {
        // Return as signed 32-bit integer
        if ($this->permissions > 0x7FFFFFFF) {
            return $this->permissions - 0x100000000;
        }
        return $this->permissions;
    }

    /**
     * Set permissions from raw value.
     */
    public function setValue(int $value): self
    {
        // Convert negative to unsigned
        if ($value < 0) {
            $value = $value + 0x100000000;
        }
        $this->permissions = $value;
        return $this;
    }

    /**
     * Create from array of permission names.
     *
     * @param array<string> $permissions Array of permission names
     */
    public static function fromArray(array $permissions): self
    {
        $instance = new self();

        foreach ($permissions as $permission) {
            match (strtolower($permission)) {
                'print' => $instance->allowPrinting(),
                'print-high', 'print_high', 'printhigh' => $instance->allowHighQualityPrinting(),
                'modify' => $instance->allowModifying(),
                'copy' => $instance->allowCopying(),
                'annot', 'annot-forms', 'annot_forms', 'annotforms' => $instance->allowAnnotations(),
                'fill-forms', 'fill_forms', 'fillforms' => $instance->allowFormFilling(),
                'extract' => $instance->allowExtraction(),
                'assemble' => $instance->allowAssembly(),
                default => null,
            };
        }

        return $instance;
    }

    /**
     * Convert to array of permission names.
     *
     * @return array<string>
     */
    public function toArray(): array
    {
        $permissions = [];

        if ($this->canPrint()) {
            $permissions[] = 'print';
        }
        if ($this->canPrintHighQuality()) {
            $permissions[] = 'print-high';
        }
        if ($this->canModify()) {
            $permissions[] = 'modify';
        }
        if ($this->canCopy()) {
            $permissions[] = 'copy';
        }
        if ($this->canAnnotate()) {
            $permissions[] = 'annot-forms';
        }
        if ($this->canFillForms()) {
            $permissions[] = 'fill-forms';
        }
        if ($this->canExtract()) {
            $permissions[] = 'extract';
        }
        if ($this->canAssemble()) {
            $permissions[] = 'assemble';
        }

        return $permissions;
    }

    /**
     * Set a permission bit.
     */
    private function setBit(int $bit, bool $value): self
    {
        if ($value) {
            $this->permissions |= (1 << $bit);
        } else {
            $this->permissions &= ~(1 << $bit);
        }
        return $this;
    }

    /**
     * Get a permission bit.
     */
    private function getBit(int $bit): bool
    {
        return ($this->permissions & (1 << $bit)) !== 0;
    }
}
