<?php

declare(strict_types=1);

namespace PdfLib\Form\Action;

use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;

/**
 * Named action for executing standard PDF viewer operations.
 *
 * Named actions provide shortcuts to common PDF viewer operations
 * like navigation, printing, and searching.
 *
 * PDF Reference: Section 8.6.4.7 "Named Actions"
 *
 * @example Navigation:
 * ```php
 * $nextPage = NamedAction::nextPage();
 * $prevPage = NamedAction::prevPage();
 * $firstPage = NamedAction::firstPage();
 * $lastPage = NamedAction::lastPage();
 * ```
 *
 * @example Printing:
 * ```php
 * $print = NamedAction::print();
 * ```
 *
 * @example Using custom name:
 * ```php
 * $action = NamedAction::create('FullScreen');
 * ```
 */
final class NamedAction extends Action
{
    // Standard named actions (PDF Reference Table 8.77)
    public const NAME_NEXT_PAGE = 'NextPage';
    public const NAME_PREV_PAGE = 'PrevPage';
    public const NAME_FIRST_PAGE = 'FirstPage';
    public const NAME_LAST_PAGE = 'LastPage';
    public const NAME_GO_BACK = 'GoBack';
    public const NAME_GO_FORWARD = 'GoForward';
    public const NAME_GO_TO_PAGE = 'GoToPage';
    public const NAME_FIND = 'Find';
    public const NAME_PRINT = 'Print';
    public const NAME_SAVE_AS = 'SaveAs';
    public const NAME_ZOOM_VIEW_IN = 'ZoomViewIn';
    public const NAME_ZOOM_VIEW_OUT = 'ZoomViewOut';
    public const NAME_FULL_SCREEN = 'FullScreen';
    public const NAME_CLOSE = 'Close';
    public const NAME_QUIT = 'Quit';

    // Acrobat-specific named actions
    public const NAME_GENERAL_INFO = 'GeneralInfo';
    public const NAME_PAGE_SETUP = 'PageSetup';
    public const NAME_SINGLE_PAGE = 'SinglePage';
    public const NAME_ONE_COLUMN = 'OneColumn';
    public const NAME_TWO_COLUMN_LEFT = 'TwoColumnLeft';
    public const NAME_TWO_COLUMN_RIGHT = 'TwoColumnRight';
    public const NAME_TWO_PAGE_LEFT = 'TwoPageLeft';
    public const NAME_TWO_PAGE_RIGHT = 'TwoPageRight';
    public const NAME_FIT_PAGE = 'FitPage';
    public const NAME_FIT_WIDTH = 'FitWidth';
    public const NAME_FIT_HEIGHT = 'FitHeight';
    public const NAME_FIT_VISIBLE = 'FitVisible';
    public const NAME_ACTUAL_SIZE = 'ActualSize';
    public const NAME_SHOW_HIDE_BOOKMARKS = 'ShowHideBookmarks';
    public const NAME_SHOW_HIDE_THUMBNAILS = 'ShowHideThumbnails';
    public const NAME_SHOW_HIDE_TOOLBAR = 'ShowHideToolbar';
    public const NAME_SHOW_HIDE_MENUBAR = 'ShowHideMenubar';

    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Create a named action.
     *
     * @param string $name Action name (one of the NAME_* constants or custom)
     */
    public static function create(string $name): self
    {
        return new self($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return self::TYPE_NAMED;
    }

    /**
     * Get the action name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the action name.
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildActionEntries(PdfDictionary $dict): void
    {
        $dict->set('N', PdfName::create($this->name));
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'name' => $this->name,
        ]);
    }

    // =========================================================================
    // NAVIGATION FACTORY METHODS
    // =========================================================================

    /**
     * Go to next page.
     */
    public static function nextPage(): self
    {
        return new self(self::NAME_NEXT_PAGE);
    }

    /**
     * Go to previous page.
     */
    public static function prevPage(): self
    {
        return new self(self::NAME_PREV_PAGE);
    }

    /**
     * Go to first page.
     */
    public static function firstPage(): self
    {
        return new self(self::NAME_FIRST_PAGE);
    }

    /**
     * Go to last page.
     */
    public static function lastPage(): self
    {
        return new self(self::NAME_LAST_PAGE);
    }

    /**
     * Go back in view history.
     */
    public static function goBack(): self
    {
        return new self(self::NAME_GO_BACK);
    }

    /**
     * Go forward in view history.
     */
    public static function goForward(): self
    {
        return new self(self::NAME_GO_FORWARD);
    }

    /**
     * Open page navigation dialog.
     */
    public static function goToPage(): self
    {
        return new self(self::NAME_GO_TO_PAGE);
    }

    // =========================================================================
    // COMMON OPERATIONS FACTORY METHODS
    // =========================================================================

    /**
     * Open print dialog.
     */
    public static function print(): self
    {
        return new self(self::NAME_PRINT);
    }

    /**
     * Open find/search dialog.
     */
    public static function find(): self
    {
        return new self(self::NAME_FIND);
    }

    /**
     * Open save as dialog.
     */
    public static function saveAs(): self
    {
        return new self(self::NAME_SAVE_AS);
    }

    /**
     * Toggle full screen mode.
     */
    public static function fullScreen(): self
    {
        return new self(self::NAME_FULL_SCREEN);
    }

    /**
     * Close the document.
     */
    public static function close(): self
    {
        return new self(self::NAME_CLOSE);
    }

    /**
     * Quit the application.
     */
    public static function quit(): self
    {
        return new self(self::NAME_QUIT);
    }

    // =========================================================================
    // ZOOM FACTORY METHODS
    // =========================================================================

    /**
     * Zoom in.
     */
    public static function zoomIn(): self
    {
        return new self(self::NAME_ZOOM_VIEW_IN);
    }

    /**
     * Zoom out.
     */
    public static function zoomOut(): self
    {
        return new self(self::NAME_ZOOM_VIEW_OUT);
    }

    /**
     * Fit page in window.
     */
    public static function fitPage(): self
    {
        return new self(self::NAME_FIT_PAGE);
    }

    /**
     * Fit width in window.
     */
    public static function fitWidth(): self
    {
        return new self(self::NAME_FIT_WIDTH);
    }

    /**
     * Fit height in window.
     */
    public static function fitHeight(): self
    {
        return new self(self::NAME_FIT_HEIGHT);
    }

    /**
     * Fit visible content in window.
     */
    public static function fitVisible(): self
    {
        return new self(self::NAME_FIT_VISIBLE);
    }

    /**
     * Actual size (100% zoom).
     */
    public static function actualSize(): self
    {
        return new self(self::NAME_ACTUAL_SIZE);
    }

    // =========================================================================
    // PAGE LAYOUT FACTORY METHODS
    // =========================================================================

    /**
     * Single page view.
     */
    public static function singlePage(): self
    {
        return new self(self::NAME_SINGLE_PAGE);
    }

    /**
     * Single column continuous view.
     */
    public static function oneColumn(): self
    {
        return new self(self::NAME_ONE_COLUMN);
    }

    /**
     * Two column continuous view (left binding).
     */
    public static function twoColumnLeft(): self
    {
        return new self(self::NAME_TWO_COLUMN_LEFT);
    }

    /**
     * Two column continuous view (right binding).
     */
    public static function twoColumnRight(): self
    {
        return new self(self::NAME_TWO_COLUMN_RIGHT);
    }

    /**
     * Two page view (left binding).
     */
    public static function twoPageLeft(): self
    {
        return new self(self::NAME_TWO_PAGE_LEFT);
    }

    /**
     * Two page view (right binding).
     */
    public static function twoPageRight(): self
    {
        return new self(self::NAME_TWO_PAGE_RIGHT);
    }

    // =========================================================================
    // UI TOGGLE FACTORY METHODS
    // =========================================================================

    /**
     * Show/hide bookmarks panel.
     */
    public static function toggleBookmarks(): self
    {
        return new self(self::NAME_SHOW_HIDE_BOOKMARKS);
    }

    /**
     * Show/hide thumbnails panel.
     */
    public static function toggleThumbnails(): self
    {
        return new self(self::NAME_SHOW_HIDE_THUMBNAILS);
    }

    /**
     * Show/hide toolbar.
     */
    public static function toggleToolbar(): self
    {
        return new self(self::NAME_SHOW_HIDE_TOOLBAR);
    }

    /**
     * Show/hide menu bar.
     */
    public static function toggleMenubar(): self
    {
        return new self(self::NAME_SHOW_HIDE_MENUBAR);
    }

    /**
     * Show general document info.
     */
    public static function generalInfo(): self
    {
        return new self(self::NAME_GENERAL_INFO);
    }

    /**
     * Open page setup dialog.
     */
    public static function pageSetup(): self
    {
        return new self(self::NAME_PAGE_SETUP);
    }
}
