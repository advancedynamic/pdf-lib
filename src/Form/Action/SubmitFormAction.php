<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfString;

/**
 * Submit form action for sending form data to a URL.
 *
 * Submits form field data to a specified URL, supporting various
 * formats and submission options.
 *
 * PDF Reference: Section 8.6.4.4 "Submit-Form Actions"
 *
 * @example Basic URL submission:
 * ```php
 * $action = SubmitFormAction::create('https://example.com/submit');
 * ```
 *
 * @example HTML form submission:
 * ```php
 * $action = SubmitFormAction::create('https://example.com/submit')
 *     ->setFormat(SubmitFormAction::FORMAT_HTML)
 *     ->setMethod('POST')
 *     ->includeFields(['name', 'email', 'message']);
 * ```
 *
 * @example FDF submission:
 * ```php
 * $action = SubmitFormAction::create('mailto:forms@example.com')
 *     ->setFormat(SubmitFormAction::FORMAT_FDF)
 *     ->includeAnnotations();
 * ```
 */
final class SubmitFormAction extends Action
{
    // Submit format flags (PDF Reference Table 8.75)
    public const FLAG_INCLUDE_EXCLUDE = 1;      // If set, fields in Fields array are excluded
    public const FLAG_INCLUDE_NO_VALUE = 2;     // Include fields with no value
    public const FLAG_EXPORT_FORMAT = 4;        // Export as HTML (not FDF)
    public const FLAG_GET_METHOD = 8;           // Use GET method (not POST)
    public const FLAG_SUBMIT_COORDS = 16;       // Include click coordinates
    public const FLAG_XFDF = 32;                // Submit as XFDF (not FDF)
    public const FLAG_INCLUDE_APPEND_SAVES = 64;     // Include append saves
    public const FLAG_INCLUDE_ANNOTATIONS = 128;     // Include annotations
    public const FLAG_SUBMIT_PDF = 256;              // Submit entire PDF
    public const FLAG_CANONICAL_FORMAT = 512;        // Use canonical date format
    public const FLAG_EXCL_NON_USER_ANNOTS = 1024;   // Exclude non-user annotations
    public const FLAG_EXCL_F_KEY = 2048;             // Exclude F key
    public const FLAG_EMBED_FORM = 8192;             // Embed form in response

    // Format constants for convenience
    public const FORMAT_FDF = 'fdf';
    public const FORMAT_XFDF = 'xfdf';
    public const FORMAT_HTML = 'html';
    public const FORMAT_PDF = 'pdf';

    private string $url;
    private int $flags = 0;

    /** @var array<int, string> Field names to include or exclude */
    private array $fields = [];

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * Create a submit form action.
     *
     * @param string $url URL to submit form data to
     */
    public static function create(string $url): self
    {
        return new self($url);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return self::TYPE_SUBMIT_FORM;
    }

    /**
     * Get the submission URL.
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Set the submission URL.
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set the submission format.
     *
     * @param string $format One of FORMAT_FDF, FORMAT_XFDF, FORMAT_HTML, FORMAT_PDF
     */
    public function setFormat(string $format): self
    {
        // Clear format-related flags
        $this->flags &= ~(self::FLAG_EXPORT_FORMAT | self::FLAG_XFDF | self::FLAG_SUBMIT_PDF);

        switch ($format) {
            case self::FORMAT_HTML:
                $this->flags |= self::FLAG_EXPORT_FORMAT;
                break;
            case self::FORMAT_XFDF:
                $this->flags |= self::FLAG_XFDF;
                break;
            case self::FORMAT_PDF:
                $this->flags |= self::FLAG_SUBMIT_PDF;
                break;
            case self::FORMAT_FDF:
            default:
                // FDF is the default (no flags set)
                break;
        }

        return $this;
    }

    /**
     * Set the HTTP method (GET or POST).
     *
     * @param string $method 'GET' or 'POST'
     */
    public function setMethod(string $method): self
    {
        if (strtoupper($method) === 'GET') {
            $this->flags |= self::FLAG_GET_METHOD;
        } else {
            $this->flags &= ~self::FLAG_GET_METHOD;
        }
        return $this;
    }

    /**
     * Use GET method.
     */
    public function useGet(): self
    {
        return $this->setMethod('GET');
    }

    /**
     * Use POST method (default).
     */
    public function usePost(): self
    {
        return $this->setMethod('POST');
    }

    /**
     * Specify which fields to include in submission.
     *
     * @param array<int, string> $fieldNames Field names to include
     */
    public function includeFields(array $fieldNames): self
    {
        $this->flags &= ~self::FLAG_INCLUDE_EXCLUDE; // Clear exclude flag
        $this->fields = $fieldNames;
        return $this;
    }

    /**
     * Specify which fields to exclude from submission.
     *
     * @param array<int, string> $fieldNames Field names to exclude
     */
    public function excludeFields(array $fieldNames): self
    {
        $this->flags |= self::FLAG_INCLUDE_EXCLUDE; // Set exclude flag
        $this->fields = $fieldNames;
        return $this;
    }

    /**
     * Include all fields (default).
     */
    public function includeAllFields(): self
    {
        $this->fields = [];
        $this->flags &= ~self::FLAG_INCLUDE_EXCLUDE;
        return $this;
    }

    /**
     * Include fields that have no value.
     */
    public function includeEmptyFields(bool $include = true): self
    {
        if ($include) {
            $this->flags |= self::FLAG_INCLUDE_NO_VALUE;
        } else {
            $this->flags &= ~self::FLAG_INCLUDE_NO_VALUE;
        }
        return $this;
    }

    /**
     * Include click coordinates in submission.
     */
    public function includeCoordinates(bool $include = true): self
    {
        if ($include) {
            $this->flags |= self::FLAG_SUBMIT_COORDS;
        } else {
            $this->flags &= ~self::FLAG_SUBMIT_COORDS;
        }
        return $this;
    }

    /**
     * Include annotation data in submission.
     */
    public function includeAnnotations(bool $include = true): self
    {
        if ($include) {
            $this->flags |= self::FLAG_INCLUDE_ANNOTATIONS;
        } else {
            $this->flags &= ~self::FLAG_INCLUDE_ANNOTATIONS;
        }
        return $this;
    }

    /**
     * Use canonical date format (D:YYYYMMDDHHmmSS).
     */
    public function useCanonicalDates(bool $use = true): self
    {
        if ($use) {
            $this->flags |= self::FLAG_CANONICAL_FORMAT;
        } else {
            $this->flags &= ~self::FLAG_CANONICAL_FORMAT;
        }
        return $this;
    }

    /**
     * Get the flags value.
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Set flags directly.
     */
    public function setFlags(int $flags): self
    {
        $this->flags = $flags;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildActionEntries(PdfDictionary $dict): void
    {
        // F - File specification (URL)
        $fileSpec = new PdfDictionary();
        $fileSpec->set('FS', \PdfLib\Parser\Object\PdfName::create('URL'));
        $fileSpec->set('F', PdfString::literal($this->url));
        $dict->set('F', $fileSpec);

        // Flags
        if ($this->flags !== 0) {
            $dict->set('Flags', PdfNumber::int($this->flags));
        }

        // Fields
        if (!empty($this->fields)) {
            $fieldsArray = new PdfArray();
            foreach ($this->fields as $fieldName) {
                $fieldsArray->push(PdfString::literal($fieldName));
            }
            $dict->set('Fields', $fieldsArray);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'url' => $this->url,
            'flags' => $this->flags,
            'fields' => $this->fields,
        ]);
    }

    // =========================================================================
    // CONVENIENCE FACTORY METHODS
    // =========================================================================

    /**
     * Create an HTML form submission action.
     *
     * @param string $url Target URL
     * @param string $method HTTP method ('GET' or 'POST')
     */
    public static function html(string $url, string $method = 'POST'): self
    {
        return self::create($url)
            ->setFormat(self::FORMAT_HTML)
            ->setMethod($method);
    }

    /**
     * Create an FDF submission action.
     *
     * @param string $url Target URL or mailto: address
     */
    public static function fdf(string $url): self
    {
        return self::create($url)
            ->setFormat(self::FORMAT_FDF);
    }

    /**
     * Create an XFDF submission action.
     *
     * @param string $url Target URL or mailto: address
     */
    public static function xfdf(string $url): self
    {
        return self::create($url)
            ->setFormat(self::FORMAT_XFDF);
    }

    /**
     * Create a PDF submission action (submit entire PDF).
     *
     * @param string $url Target URL
     */
    public static function pdf(string $url): self
    {
        return self::create($url)
            ->setFormat(self::FORMAT_PDF);
    }

    /**
     * Create an email submission action.
     *
     * @param string $email Email address
     * @param string $subject Email subject
     */
    public static function email(string $email, string $subject = 'Form Submission'): self
    {
        $mailto = 'mailto:' . $email . '?subject=' . rawurlencode($subject);
        return self::create($mailto)
            ->setFormat(self::FORMAT_FDF);
    }
}
