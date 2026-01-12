<?php

declare(strict_types=1);

namespace PdfLib\Document;

use DateTimeImmutable;
use DateTimeInterface;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfString;

/**
 * PDF document metadata (Info dictionary).
 */
final class Metadata
{
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $keywords = null;
    private ?string $creator = null;
    private ?string $producer = null;
    private ?DateTimeInterface $creationDate = null;
    private ?DateTimeInterface $modDate = null;

    /** @var array<string, string> */
    private array $custom = [];

    public function __construct()
    {
        $this->producer = 'PdfLib';
        $this->creationDate = new DateTimeImmutable();
    }

    /**
     * Create from an array of values.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $metadata = new self();

        if (isset($data['title'])) {
            $metadata->title = (string) $data['title'];
        }
        if (isset($data['author'])) {
            $metadata->author = (string) $data['author'];
        }
        if (isset($data['subject'])) {
            $metadata->subject = (string) $data['subject'];
        }
        if (isset($data['keywords'])) {
            $metadata->keywords = (string) $data['keywords'];
        }
        if (isset($data['creator'])) {
            $metadata->creator = (string) $data['creator'];
        }
        if (isset($data['producer'])) {
            $metadata->producer = (string) $data['producer'];
        }

        return $metadata;
    }

    /**
     * Create from a PdfDictionary (parsed from PDF).
     */
    public static function fromDictionary(PdfDictionary $dict): self
    {
        $metadata = new self();

        $titleObj = $dict->get('Title');
        if ($titleObj instanceof PdfString) {
            $metadata->title = $titleObj->getValue();
        }

        $authorObj = $dict->get('Author');
        if ($authorObj instanceof PdfString) {
            $metadata->author = $authorObj->getValue();
        }

        $subjectObj = $dict->get('Subject');
        if ($subjectObj instanceof PdfString) {
            $metadata->subject = $subjectObj->getValue();
        }

        $keywordsObj = $dict->get('Keywords');
        if ($keywordsObj instanceof PdfString) {
            $metadata->keywords = $keywordsObj->getValue();
        }

        $creatorObj = $dict->get('Creator');
        if ($creatorObj instanceof PdfString) {
            $metadata->creator = $creatorObj->getValue();
        }

        $producerObj = $dict->get('Producer');
        if ($producerObj instanceof PdfString) {
            $metadata->producer = $producerObj->getValue();
        }

        $creationDateObj = $dict->get('CreationDate');
        if ($creationDateObj instanceof PdfString) {
            $metadata->creationDate = self::parsePdfDate($creationDateObj->getValue());
        }

        $modDateObj = $dict->get('ModDate');
        if ($modDateObj instanceof PdfString) {
            $metadata->modDate = self::parsePdfDate($modDateObj->getValue());
        }

        return $metadata;
    }

    // Getters and setters

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): self
    {
        $this->author = $author;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getKeywords(): ?string
    {
        return $this->keywords;
    }

    public function setKeywords(?string $keywords): self
    {
        $this->keywords = $keywords;
        return $this;
    }

    public function getCreator(): ?string
    {
        return $this->creator;
    }

    public function setCreator(?string $creator): self
    {
        $this->creator = $creator;
        return $this;
    }

    public function getProducer(): ?string
    {
        return $this->producer;
    }

    public function setProducer(?string $producer): self
    {
        $this->producer = $producer;
        return $this;
    }

    public function getCreationDate(): ?DateTimeInterface
    {
        return $this->creationDate;
    }

    public function setCreationDate(?DateTimeInterface $date): self
    {
        $this->creationDate = $date;
        return $this;
    }

    public function getModDate(): ?DateTimeInterface
    {
        return $this->modDate;
    }

    public function setModDate(?DateTimeInterface $date): self
    {
        $this->modDate = $date;
        return $this;
    }

    /**
     * Set a custom metadata field.
     */
    public function setCustom(string $key, string $value): self
    {
        $this->custom[$key] = $value;
        return $this;
    }

    /**
     * Get a custom metadata field.
     */
    public function getCustom(string $key): ?string
    {
        return $this->custom[$key] ?? null;
    }

    /**
     * Get all custom fields.
     *
     * @return array<string, string>
     */
    public function getAllCustom(): array
    {
        return $this->custom;
    }

    /**
     * Convert to PDF dictionary.
     */
    public function toDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();

        if ($this->title !== null) {
            $dict->set('Title', PdfString::literal($this->title));
        }

        if ($this->author !== null) {
            $dict->set('Author', PdfString::literal($this->author));
        }

        if ($this->subject !== null) {
            $dict->set('Subject', PdfString::literal($this->subject));
        }

        if ($this->keywords !== null) {
            $dict->set('Keywords', PdfString::literal($this->keywords));
        }

        if ($this->creator !== null) {
            $dict->set('Creator', PdfString::literal($this->creator));
        }

        if ($this->producer !== null) {
            $dict->set('Producer', PdfString::literal($this->producer));
        }

        if ($this->creationDate !== null) {
            $dict->set('CreationDate', PdfString::literal(self::formatPdfDate($this->creationDate)));
        }

        if ($this->modDate !== null) {
            $dict->set('ModDate', PdfString::literal(self::formatPdfDate($this->modDate)));
        }

        // Add custom fields
        foreach ($this->custom as $key => $value) {
            $dict->set($key, PdfString::literal($value));
        }

        return $dict;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'subject' => $this->subject,
            'keywords' => $this->keywords,
            'creator' => $this->creator,
            'producer' => $this->producer,
            'creationDate' => $this->creationDate?->format('c'),
            'modDate' => $this->modDate?->format('c'),
            'custom' => $this->custom,
        ];
    }

    /**
     * Format a date for PDF (D:YYYYMMDDHHmmss+HH'mm').
     */
    public static function formatPdfDate(DateTimeInterface $date): string
    {
        $offset = $date->format('O');
        $sign = $offset[0];
        $hours = substr($offset, 1, 2);
        $minutes = substr($offset, 3, 2);

        return 'D:' . $date->format('YmdHis') . $sign . $hours . "'" . $minutes . "'";
    }

    /**
     * Parse a PDF date string.
     */
    public static function parsePdfDate(string $pdfDate): ?DateTimeInterface
    {
        // Remove D: prefix
        if (str_starts_with($pdfDate, 'D:')) {
            $pdfDate = substr($pdfDate, 2);
        }

        // Basic format: YYYYMMDDHHmmss
        $pattern = '/^(\d{4})(\d{2})?(\d{2})?(\d{2})?(\d{2})?(\d{2})?([+\-Z])?(\d{2})?\'?(\d{2})?\'?$/';

        if (!preg_match($pattern, $pdfDate, $matches)) {
            return null;
        }

        $year = $matches[1];
        $month = $matches[2] ?? '01';
        $day = $matches[3] ?? '01';
        $hour = $matches[4] ?? '00';
        $minute = $matches[5] ?? '00';
        $second = $matches[6] ?? '00';
        $tzSign = $matches[7] ?? '+';
        $tzHour = $matches[8] ?? '00';
        $tzMinute = $matches[9] ?? '00';

        if ($tzSign === 'Z') {
            $timezone = '+00:00';
        } else {
            $timezone = $tzSign . $tzHour . ':' . $tzMinute;
        }

        $dateString = "$year-$month-$day $hour:$minute:$second $timezone";

        try {
            return new DateTimeImmutable($dateString);
        } catch (\Exception) {
            return null;
        }
    }
}
