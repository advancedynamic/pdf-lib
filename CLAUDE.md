# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Build & Development Commands

```bash
composer install          # Install dependencies
composer test             # Run PHPUnit tests
composer test -- --filter=ClassName  # Run a single test class
composer test -- --filter=testMethodName  # Run a single test method
composer analyse          # Run PHPStan static analysis (max level)
composer cs-check         # Check code style (dry-run)
composer cs-fix           # Fix code style automatically
```

## Architecture

This is a comprehensive PHP PDF library supporting generation, parsing, and manipulation.

- **Namespace**: `PdfLib\` maps to `src/`
- **Test Namespace**: `PdfLib\Tests\` maps to `tests/`
- **PHP Version**: 8.1+
- **PDF Version Support**: 1.4 through 2.0

### Namespace Structure

```
PdfLib\
├── Document\           # Document facade and metadata
├── Page\               # Page management and sizes
├── Parser\             # PDF parsing (lexer, object parser, xref)
│   └── Object\         # PDF object types (10 classes)
├── Writer\             # PDF writing (objects, streams, xref)
├── Exception\          # Exception hierarchy
├── Content\            # (Planned) Text, images, graphics
├── Font\               # (Planned) Font handling
├── Color\              # (Planned) Color spaces
├── Manipulation\       # (Planned) Merge, split, stamp
└── Security\           # (Planned) Encryption, signatures
```

### Core Classes (Phase 1 - Complete)

**Document Layer:**
- `PdfDocument` - Main document facade with fluent API
- `Metadata` - Document info (title, author, subject, keywords, dates)
- `Page` - Single page with boxes, rotation, dimensions
- `PageCollection` - Page tree management (Countable, Iterator, ArrayAccess)
- `PageSize` - Standard sizes (A0-A6, B0-B6, Letter, Legal, Tabloid)
- `PageBox` - MediaBox, CropBox, BleedBox, TrimBox, ArtBox

**Parser Layer:**
- `PdfParser` - Main parser facade (load PDFs from file/string)
- `Lexer` - Tokenize PDF byte stream
- `ObjectParser` - Parse PDF objects from tokens
- `XrefParser` - Parse xref tables and xref streams (PDF 1.5+)
- `StreamParser` - Decode streams (FlateDecode, ASCII85, ASCIIHex, LZW, RunLength)

**Object Model (10 types):**
- `PdfObject` - Abstract base class
- `PdfNull`, `PdfBoolean`, `PdfNumber`, `PdfString`, `PdfName`
- `PdfArray`, `PdfDictionary`, `PdfReference`, `PdfStream`

**Writer Layer:**
- `PdfWriter` - Main writer facade
- `ObjectWriter` - Serialize PDF objects
- `StreamWriter` - Handle stream compression
- `XrefWriter` - Write xref tables

### API Style

**Fluent (Simple Use Cases):**
```php
$pdf = PdfDocument::create()
    ->setTitle('My Document')
    ->addPage(PageSize::a4())
    ->save('output.pdf');
```

**Explicit Objects (Complex Scenarios):**
```php
$page = new Page(PageSize::letter()->landscape());
$page->setRotation(90);
$page->setCropBox(PageBox::create(500, 700, 50, 50));
$document->addPageObject($page);
```

### Dependencies

- `phpseclib/phpseclib` (~3.0) - Cryptographic library for digital signatures

### Coding Standards

- Uses PER-CS coding style (PHP-FIG)
- Strict types enabled in all files (`declare(strict_types=1)`)
- PHPStan level max - all code must pass static analysis
- Fluent interfaces (methods return `$this` where appropriate)
- Implements ArrayAccess, Countable, Iterator where appropriate

### Implementation Status

**Phase 1 (Core Foundation):** Complete
- Exception classes
- PDF object model (10 types)
- Parser (lexer, objects, xref, streams)
- Writer (objects, streams, xref)
- Document structure (pages, metadata)

**Phase 2 (Content Generation):** Planned
- Text rendering, fonts, images, graphics, colors

**Phase 3 (Manipulation):** Planned
- Merge, split, stamp, rotate, crop

**Phase 4 (Security):** Planned
- Encryption (RC4, AES), digital signatures, timestamps

**Phase 5 (Advanced):** Planned
- Tables, PDF/A compliance, optimization
