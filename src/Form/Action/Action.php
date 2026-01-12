<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;

/**
 * Abstract base class for PDF actions.
 *
 * Actions are operations that can be triggered by events in a PDF document,
 * such as opening the document, clicking a link, or interacting with form fields.
 *
 * PDF Reference: Section 8.5 "Actions"
 *
 * @example
 * ```php
 * // Create a JavaScript action
 * $action = JavaScriptAction::create('app.alert("Hello!");');
 *
 * // Chain actions
 * $action->setNext(JavaScriptAction::create('app.beep(0);'));
 * ```
 */
abstract class Action
{
    // Action types (PDF Reference Table 8.48)
    public const TYPE_GO_TO = 'GoTo';
    public const TYPE_GO_TO_R = 'GoToR';
    public const TYPE_GO_TO_E = 'GoToE';
    public const TYPE_LAUNCH = 'Launch';
    public const TYPE_THREAD = 'Thread';
    public const TYPE_URI = 'URI';
    public const TYPE_SOUND = 'Sound';
    public const TYPE_MOVIE = 'Movie';
    public const TYPE_HIDE = 'Hide';
    public const TYPE_NAMED = 'Named';
    public const TYPE_SUBMIT_FORM = 'SubmitForm';
    public const TYPE_RESET_FORM = 'ResetForm';
    public const TYPE_IMPORT_DATA = 'ImportData';
    public const TYPE_JAVASCRIPT = 'JavaScript';
    public const TYPE_SET_OCG_STATE = 'SetOCGState';
    public const TYPE_RENDITION = 'Rendition';
    public const TYPE_TRANS = 'Trans';
    public const TYPE_GO_TO_3D_VIEW = 'GoTo3DView';

    /** @var Action|null Next action in the chain */
    protected ?Action $next = null;

    /**
     * Get the action type name (S entry).
     */
    abstract public function getType(): string;

    /**
     * Build the action-specific dictionary entries.
     *
     * Subclasses implement this to add their specific entries.
     */
    abstract protected function buildActionEntries(PdfDictionary $dict): void;

    /**
     * Set the next action to be executed after this one.
     *
     * @param Action|null $action The next action
     */
    public function setNext(?Action $action): self
    {
        $this->next = $action;
        return $this;
    }

    /**
     * Get the next action in the chain.
     */
    public function getNext(): ?Action
    {
        return $this->next;
    }

    /**
     * Check if this action has a next action.
     */
    public function hasNext(): bool
    {
        return $this->next !== null;
    }

    /**
     * Build the complete action dictionary.
     */
    public function toDictionary(): PdfDictionary
    {
        $dict = new PdfDictionary();
        $dict->set('Type', PdfName::create('Action'));
        $dict->set('S', PdfName::create($this->getType()));

        // Add action-specific entries
        $this->buildActionEntries($dict);

        // Add next action if present
        if ($this->next !== null) {
            $dict->set('Next', $this->next->toDictionary());
        }

        return $dict;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'type' => $this->getType(),
        ];

        if ($this->next !== null) {
            $data['next'] = $this->next->toArray();
        }

        return $data;
    }
}
