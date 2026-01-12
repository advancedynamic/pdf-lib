<?php

declare(strict_types=1);

namespace PdfLib\Security\Signature;

/**
 * PDF Signature Field - Widget annotation for digital signatures.
 *
 * @example
 * ```php
 * $field = SignatureField::create('signature1')
 *     ->setPosition(100, 100)
 *     ->setSize(200, 50)
 *     ->setPage(1)
 *     ->setReason('Document approval')
 *     ->setLocation('New York');
 * ```
 */
final class SignatureField
{
    // Signature types (certification levels)
    public const TYPE_APPROVAL = 0;          // Approval signature (no restrictions)
    public const TYPE_CERTIFY_NO_CHANGES = 1; // Certifying, no changes allowed
    public const TYPE_CERTIFY_FORM_FILL = 2;  // Certifying, form filling allowed
    public const TYPE_CERTIFY_ANNOTATE = 3;   // Certifying, form filling + annotation allowed

    private string $name;
    private int $page = 1;
    private float $x = 0;
    private float $y = 0;
    private float $width = 0;
    private float $height = 0;

    // Signature metadata
    private ?string $signerName = null;
    private ?string $reason = null;
    private ?string $location = null;
    private ?string $contactInfo = null;

    // Appearance
    private bool $visible = true;
    private ?string $backgroundImage = null;
    private ?string $customAppearance = null;

    // Signature type
    private int $signatureType = self::TYPE_APPROVAL;

    // State
    private bool $isSigned = false;

    public function __construct(string $name)
    {
        $this->name = $this->sanitizeFieldName($name);
    }

    /**
     * Create a new signature field.
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * Get field name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set field name.
     */
    public function setName(string $name): self
    {
        $this->name = $this->sanitizeFieldName($name);
        return $this;
    }

    /**
     * Get page number (1-based).
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set page number (1-based).
     */
    public function setPage(int $page): self
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page number must be >= 1');
        }
        $this->page = $page;
        return $this;
    }

    /**
     * Get X position.
     */
    public function getX(): float
    {
        return $this->x;
    }

    /**
     * Get Y position.
     */
    public function getY(): float
    {
        return $this->y;
    }

    /**
     * Set position.
     */
    public function setPosition(float $x, float $y): self
    {
        $this->x = $x;
        $this->y = $y;
        return $this;
    }

    /**
     * Get width.
     */
    public function getWidth(): float
    {
        return $this->width;
    }

    /**
     * Get height.
     */
    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * Set size.
     */
    public function setSize(float $width, float $height): self
    {
        $this->width = max(0, $width);
        $this->height = max(0, $height);
        return $this;
    }

    /**
     * Get rectangle [x1, y1, x2, y2].
     *
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    public function getRect(): array
    {
        return [
            $this->x,
            $this->y,
            $this->x + $this->width,
            $this->y + $this->height,
        ];
    }

    /**
     * Set rectangle [x1, y1, x2, y2].
     *
     * @param array{0: float, 1: float, 2: float, 3: float} $rect
     */
    public function setRect(array $rect): self
    {
        $this->x = $rect[0];
        $this->y = $rect[1];
        $this->width = $rect[2] - $rect[0];
        $this->height = $rect[3] - $rect[1];
        return $this;
    }

    /**
     * Get signer name.
     */
    public function getSignerName(): ?string
    {
        return $this->signerName;
    }

    /**
     * Set signer name.
     */
    public function setSignerName(string $name): self
    {
        $this->signerName = $name;
        return $this;
    }

    /**
     * Get signing reason.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Set signing reason.
     */
    public function setReason(string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    /**
     * Get signing location.
     */
    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * Set signing location.
     */
    public function setLocation(string $location): self
    {
        $this->location = $location;
        return $this;
    }

    /**
     * Get contact info.
     */
    public function getContactInfo(): ?string
    {
        return $this->contactInfo;
    }

    /**
     * Set contact info.
     */
    public function setContactInfo(string $contactInfo): self
    {
        $this->contactInfo = $contactInfo;
        return $this;
    }

    /**
     * Check if field is visible.
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * Set visibility.
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;
        return $this;
    }

    /**
     * Make field invisible.
     */
    public function invisible(): self
    {
        $this->visible = false;
        return $this;
    }

    /**
     * Get signature type.
     */
    public function getSignatureType(): int
    {
        return $this->signatureType;
    }

    /**
     * Set signature type.
     */
    public function setSignatureType(int $type): self
    {
        if (!in_array($type, [
            self::TYPE_APPROVAL,
            self::TYPE_CERTIFY_NO_CHANGES,
            self::TYPE_CERTIFY_FORM_FILL,
            self::TYPE_CERTIFY_ANNOTATE,
        ], true)) {
            throw new \InvalidArgumentException('Invalid signature type');
        }
        $this->signatureType = $type;
        return $this;
    }

    /**
     * Set as approval signature (default).
     */
    public function asApproval(): self
    {
        $this->signatureType = self::TYPE_APPROVAL;
        return $this;
    }

    /**
     * Set as certifying signature - no changes allowed.
     */
    public function asCertifyNoChanges(): self
    {
        $this->signatureType = self::TYPE_CERTIFY_NO_CHANGES;
        return $this;
    }

    /**
     * Set as certifying signature - form fill allowed.
     */
    public function asCertifyFormFill(): self
    {
        $this->signatureType = self::TYPE_CERTIFY_FORM_FILL;
        return $this;
    }

    /**
     * Set as certifying signature - annotations allowed.
     */
    public function asCertifyAnnotate(): self
    {
        $this->signatureType = self::TYPE_CERTIFY_ANNOTATE;
        return $this;
    }

    /**
     * Check if this is a certifying signature.
     */
    public function isCertifying(): bool
    {
        return $this->signatureType !== self::TYPE_APPROVAL;
    }

    /**
     * Check if field is signed.
     */
    public function isSigned(): bool
    {
        return $this->isSigned;
    }

    /**
     * Mark field as signed.
     */
    public function markSigned(): self
    {
        $this->isSigned = true;
        return $this;
    }

    /**
     * Get background image path.
     */
    public function getBackgroundImage(): ?string
    {
        return $this->backgroundImage;
    }

    /**
     * Set background image for visible signature.
     */
    public function setBackgroundImage(string $imagePath): self
    {
        $this->backgroundImage = $imagePath;
        return $this;
    }

    /**
     * Get custom appearance stream.
     */
    public function getCustomAppearance(): ?string
    {
        return $this->customAppearance;
    }

    /**
     * Set custom appearance stream content.
     */
    public function setCustomAppearance(string $appearance): self
    {
        $this->customAppearance = $appearance;
        return $this;
    }

    /**
     * Get annotation flags.
     */
    public function getFlags(): int
    {
        // Flags: Print (4) + Locked (128) = 132
        // If invisible, add Hidden (2) and NoView (32)
        $flags = 132;
        if (!$this->visible) {
            $flags |= 2 | 32;
        }
        return $flags;
    }

    /**
     * Build signature info dictionary entries.
     *
     * @return array<string, string>
     */
    public function getSignatureInfo(): array
    {
        $info = [];

        if ($this->signerName !== null) {
            $info['Name'] = $this->signerName;
        }

        if ($this->reason !== null) {
            $info['Reason'] = $this->reason;
        }

        if ($this->location !== null) {
            $info['Location'] = $this->location;
        }

        if ($this->contactInfo !== null) {
            $info['ContactInfo'] = $this->contactInfo;
        }

        return $info;
    }

    /**
     * Sanitize field name (remove special characters).
     */
    private function sanitizeFieldName(string $name): string
    {
        // Remove characters that are problematic in PDF names
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
        return $name ?: 'Signature';
    }

    /**
     * Clone the field with a new name.
     */
    public function withName(string $name): self
    {
        $clone = clone $this;
        $clone->name = $this->sanitizeFieldName($name);
        return $clone;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'page' => $this->page,
            'rect' => $this->getRect(),
            'visible' => $this->visible,
            'signatureType' => $this->signatureType,
            'info' => $this->getSignatureInfo(),
            'isSigned' => $this->isSigned,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $field = new self($data['name'] ?? 'Signature');

        if (isset($data['page'])) {
            $field->setPage((int) $data['page']);
        }

        if (isset($data['rect'])) {
            $field->setRect($data['rect']);
        }

        if (isset($data['visible'])) {
            $field->setVisible((bool) $data['visible']);
        }

        if (isset($data['signatureType'])) {
            $field->setSignatureType((int) $data['signatureType']);
        }

        if (isset($data['info'])) {
            $info = $data['info'];
            if (isset($info['Name'])) {
                $field->setSignerName($info['Name']);
            }
            if (isset($info['Reason'])) {
                $field->setReason($info['Reason']);
            }
            if (isset($info['Location'])) {
                $field->setLocation($info['Location']);
            }
            if (isset($info['ContactInfo'])) {
                $field->setContactInfo($info['ContactInfo']);
            }
        }

        return $field;
    }
}
