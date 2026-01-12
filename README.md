# PDF-Lib

A comprehensive PHP library for creating, parsing, and manipulating PDF documents. Built with modern PHP 8.1+ features, zero external dependencies for core functionality.

## Support

Please consider supporting this project by making a donation via PayPal: https://paypal.me/abahido

## Features

- **Document Creation** - Create PDFs from scratch with text, images, tables, and graphics
- **PDF Parsing** - Read and extract content from existing PDFs
- **Manipulation** - Merge, split, rotate, crop, stamp, and optimize PDFs
- **Digital Signatures** - Sign PDFs with X.509 certificates (PAdES compatible)
- **Encryption** - Password protection with RC4, AES-128, and AES-256
- **Form Filling** - Fill and flatten PDF forms (AcroForms)
- **HTML to PDF** - Convert HTML/CSS to PDF (pure PHP, no external tools)
- **PDF to Office** - Export PDFs to DOCX and XLSX formats
- **Laravel Integration** - First-class Laravel support via separate package

## Requirements

- PHP 8.1 or higher
- OpenSSL extension (for encryption and signatures)
- GD or Imagick extension (for image handling)

## Installation

```bash
composer require pdf-lib/pdf-lib
```

For Laravel integration:

```bash
composer require pdf-lib/laravel
```

## Quick Start

### Creating a PDF

```php
use PdfLib\Document\PdfDocument;

$pdf = PdfDocument::create()
    ->setTitle('My Document')
    ->setAuthor('John Doe')
    ->addPage()
        ->addText('Hello World!', 100, 750, ['fontSize' => 24])
        ->addText('Welcome to PDF-Lib', 100, 700)
    ->save('document.pdf');
```

### Reading a PDF

```php
use PdfLib\Parser\PdfParser;

$parser = PdfParser::parseFile('document.pdf');

echo "Pages: " . $parser->getPageCount() . "\n";
echo "Title: " . $parser->getInfo()?->get('Title')?->getValue() . "\n";
```

### Merging PDFs

```php
use PdfLib\Manipulation\Merger;

$merger = new Merger();
$merger->addFile('document1.pdf')
       ->addFile('document2.pdf')
       ->addFile('document3.pdf', pages: [1, 3, 5])  // Specific pages
       ->save('merged.pdf');
```

### Digital Signatures

```php
use PdfLib\Security\Signature\Signer;

$signer = new Signer();
$signer->loadFile('document.pdf')
       ->loadCertificate('certificate.p12', 'password')
       ->setReason('Document Approval')
       ->setLocation('New York')
       ->save('signed.pdf');
```

---

## Documentation

### Table of Contents

- [Coordinate System](#coordinate-system)
- [Document Creation](#document-creation)
- [Text & Fonts](#text--fonts)
- [Images](#images)
- [Tables](#tables)
- [Graphics](#graphics)
- [PDF Parsing](#pdf-parsing)
- [Manipulation](#manipulation)
  - [Merging](#merging)
  - [Splitting](#splitting)
  - [Rotating](#rotating)
  - [Cropping](#cropping)
  - [Stamping & Watermarks](#stamping--watermarks)
  - [Optimization](#optimization)
- [Digital Signatures](#digital-signatures)
  - [Single Signature](#single-signature)
  - [Multiple Signatures](#multiple-signatures)
  - [Sequential Signing Workflow](#sequential-signing-workflow)
- [Encryption](#encryption)
- [Form Filling](#form-filling)
- [Converters](#converters)
  - [HTML to PDF](#html-to-pdf)
  - [PDF to DOCX](#pdf-to-docx)
  - [PDF to XLSX](#pdf-to-xlsx)
- [Laravel Integration](#laravel-integration)

---

## Coordinate System

PDF uses a coordinate system where:
- **Origin (0, 0)** is at the **bottom-left** corner of the page
- **X axis** increases to the **right**
- **Y axis** increases **upward**
- Units are in **points** (1 point = 1/72 inch)

```
    ┌─────────────────────────────────┐  ← Top of page (y = 842 for A4)
    │                                 │
    │     (100, 700)                  │
    │         ●  ← Text/Image here    │
    │                                 │
    │                                 │
    │     (100, 400)                  │
    │         ●  ← Another element    │
    │                                 │
    │                                 │
    │                                 │
    └─────────────────────────────────┘
  (0,0)                              (595, 0) for A4
  Origin (bottom-left)
```

### Common Page Sizes (in points)

| Size | Width | Height | Notes |
|------|-------|--------|-------|
| A4 | 595 | 842 | Standard international |
| Letter | 612 | 792 | US standard |
| Legal | 612 | 1008 | US legal |
| A3 | 842 | 1191 | Double A4 |

### Positioning Elements

```php
use PdfLib\Page\PageSize;

// A4 page dimensions
$width = 595;   // points
$height = 842;  // points

// Position at top-left area
$page->addText('Top Left', 50, 800);

// Position at top-right area
$page->addText('Top Right', 450, 800);

// Position at center
$page->addText('Center', $width / 2 - 30, $height / 2);

// Position at bottom
$page->addText('Bottom', 50, 50);
```

### Positioning Images (QR Code, Logo, etc.)

```php
// addImage(path, x, y, width, height)

// QR Code at top-right corner
$page->addImage('qrcode.png', 450, 750, 100, 100);

// Logo at top-left
$page->addImage('logo.png', 50, 780, 120, 50);

// Signature image at bottom-right
$page->addImage('signature.png', 400, 50, 150, 60);

// Center an image horizontally
$imageWidth = 200;
$pageWidth = 595;
$x = ($pageWidth - $imageWidth) / 2;  // = 197.5
$page->addImage('photo.jpg', $x, 400, 200, 150);
```

### Visual Position Reference (A4 Page)

```php
// Common positions on A4 (595 x 842 points)

// Header area (top of page)
$headerY = 800;  // Near top

// Content area
$contentStartY = 750;  // Below header
$contentEndY = 100;    // Above footer

// Footer area
$footerY = 50;   // Near bottom

// Margins
$leftMargin = 50;
$rightMargin = 545;  // 595 - 50

// Example: Invoice layout
$page->addImage('logo.png', 50, 780, 100, 40);           // Logo top-left
$page->addText('INVOICE', 450, 800, ['fontSize' => 24]); // Title top-right
$page->addImage('qrcode.png', 480, 50, 80, 80);          // QR code bottom-right
$page->addText('Page 1', 280, 30);                        // Page number bottom-center
```

### Calculating Positions

```php
// Helper function to position from top (more intuitive)
function fromTop(float $distanceFromTop, float $pageHeight = 842): float
{
    return $pageHeight - $distanceFromTop;
}

// Usage: place element 100 points from top
$y = fromTop(100);  // Returns 742
$page->addText('100 points from top', 50, $y);

// Position image with specific margins
$pageWidth = 595;
$pageHeight = 842;
$margin = 50;

// Top-right corner with margin
$imageWidth = 100;
$x = $pageWidth - $margin - $imageWidth;  // 445
$y = $pageHeight - $margin;                // 792
$page->addImage('qrcode.png', $x, $y - 100, 100, 100);
```

---

## Document Creation

### Basic Document

```php
use PdfLib\Document\PdfDocument;
use PdfLib\Page\Page;
use PdfLib\Page\PageSize;

// Method 1: Fluent API
$pdf = PdfDocument::create()
    ->setTitle('My Document')
    ->setAuthor('Author Name')
    ->setSubject('Document Subject')
    ->setCreator('PDF-Lib')
    ->addPage()
        ->addText('Hello World', 100, 750)
    ->save('output.pdf');

// Method 2: Object-oriented
$document = new PdfDocument();
$document->setTitle('My Document');

$page = new Page(PageSize::a4());
$page->addText('Hello World', 100, 750);
$document->addPageObject($page);

$document->save('output.pdf');
```

### Page Sizes

```php
use PdfLib\Page\PageSize;

// Standard sizes
$page = new Page(PageSize::a4());        // 595 x 842 points
$page = new Page(PageSize::letter());    // 612 x 792 points
$page = new Page(PageSize::legal());     // 612 x 1008 points
$page = new Page(PageSize::a3());        // 842 x 1191 points

// Custom size (width, height in points)
$page = new Page(PageSize::custom(400, 600));

// Landscape orientation
$page = new Page(PageSize::a4()->landscape());
```

---

## Text & Fonts

### Adding Text

```php
$page->addText('Simple text', 100, 750);

// With options
$page->addText('Styled text', 100, 700, [
    'fontSize' => 16,
    'fontFamily' => 'Helvetica',
    'color' => [0, 0, 255],      // RGB blue
    'align' => 'left',
]);

// Bold and italic
$page->addText('Bold text', 100, 650, ['fontWeight' => 'bold']);
$page->addText('Italic text', 100, 620, ['fontStyle' => 'italic']);
```

### Standard Fonts

The library includes the 14 standard PDF fonts:

- Helvetica, Helvetica-Bold, Helvetica-Oblique, Helvetica-BoldOblique
- Times-Roman, Times-Bold, Times-Italic, Times-BoldItalic
- Courier, Courier-Bold, Courier-Oblique, Courier-BoldOblique
- Symbol, ZapfDingbats

```php
$page->addText('Helvetica', 100, 750, ['fontFamily' => 'Helvetica']);
$page->addText('Times Roman', 100, 720, ['fontFamily' => 'Times-Roman']);
$page->addText('Courier', 100, 690, ['fontFamily' => 'Courier']);
```

---

## Images

### Adding Images

The `addImage` method signature is:
```php
addImage(string $path, float $x, float $y, float $width, float $height)
```

Where:
- `$path` - File path or raw image content
- `$x` - X position (from left edge)
- `$y` - Y position (from bottom edge) - **Note: this is the bottom of the image**
- `$width` - Image width in points
- `$height` - Image height in points

```php
// Basic image at position (100, 500) with size 200x150
$page->addImage('photo.jpg', 100, 500, 200, 150);

// From different sources
$page->addImage('/path/to/image.png', 100, 400, 200, 150);
$page->addImage($imageContent, 100, 300, 200, 150);  // Raw content

// Supported formats: JPEG, PNG, GIF
```

### QR Codes and Barcodes

```php
// QR Code positioning examples (A4 page: 595 x 842)

// Top-right corner
$page->addImage('qrcode.png', 480, 750, 80, 80);

// Bottom-right corner
$page->addImage('qrcode.png', 480, 50, 80, 80);

// Bottom-left corner
$page->addImage('qrcode.png', 50, 50, 80, 80);

// Next to text
$page->addText('Scan QR Code:', 50, 600);
$page->addImage('qrcode.png', 180, 560, 100, 100);
```

### Common Layout Patterns

```php
// Invoice/Document header layout
$page->addImage('company_logo.png', 50, 780, 150, 50);    // Logo left
$page->addImage('qrcode.png', 470, 760, 80, 80);          // QR right

// Certificate layout - centered elements
$pageWidth = 595;
$logoWidth = 100;
$page->addImage('seal.png', ($pageWidth - $logoWidth) / 2, 700, $logoWidth, $logoWidth);

// Footer with multiple images
$page->addImage('facebook.png', 50, 30, 20, 20);
$page->addImage('twitter.png', 80, 30, 20, 20);
$page->addImage('linkedin.png', 110, 30, 20, 20);

// Signature with stamp
$page->addImage('signature.png', 350, 150, 150, 50);
$page->addImage('stamp.png', 380, 100, 80, 80);
```

### Position Reference Chart

```
┌─────────────────────────────────────────┐
│  Logo (50, 780)          QR (470, 760)  │  ← Header area
│  ┌─────┐                      ┌────┐    │
│  │     │                      │ QR │    │
│  └─────┘                      └────┘    │
│                                         │
│         Content Area                    │
│         Images at (x, y)                │
│                                         │
│  ┌──────────────┐                       │
│  │   Photo      │  ← (50, 400, 200, 150)│
│  │   200x150    │                       │
│  └──────────────┘                       │
│                                         │
│  Signature (350, 100)    Stamp (430, 80)│  ← Footer area
│  ┌──────────┐            ┌─────┐        │
│  │ Sign     │            │Stamp│        │
│  └──────────┘            └─────┘        │
└─────────────────────────────────────────┘
(0,0)
```

---

## Tables

### Creating Tables

```php
use PdfLib\Content\Table\Table;
use PdfLib\Content\Table\TableStyle;

// Simple table
$data = [
    ['Name', 'Email', 'Status'],
    ['John Doe', 'john@example.com', 'Active'],
    ['Jane Smith', 'jane@example.com', 'Pending'],
];

$page->addTable($data, 50, 700, [
    'widths' => [150, 200, 100],
    'headerBackground' => [220, 220, 220],
]);

// Advanced table with styling
$table = new Table();
$table->setPosition(50, 700);
$table->setColumnWidths([150, 200, 100]);
$table->setStyle(TableStyle::create()
    ->setHeaderBackground([66, 139, 202])
    ->setHeaderTextColor([255, 255, 255])
    ->setAlternateRowBackground([245, 245, 245])
    ->setBorderColor([200, 200, 200])
    ->setPadding(8)
);

$table->addHeaderRow(['Name', 'Email', 'Status']);
$table->addRow(['John', 'john@example.com', 'Active']);
$table->addRow(['Jane', 'jane@example.com', 'Pending']);

$page->addTableObject($table);
```

---

## Graphics

### Drawing Shapes

```php
use PdfLib\Content\Graphics\Canvas;

$canvas = new Canvas($page);

// Rectangle
$canvas->drawRectangle(100, 600, 200, 100, [
    'fill' => [200, 200, 255],
    'stroke' => [0, 0, 255],
    'lineWidth' => 2,
]);

// Circle
$canvas->drawCircle(300, 500, 50, [
    'fill' => [255, 200, 200],
    'stroke' => [255, 0, 0],
]);

// Line
$canvas->drawLine(100, 400, 400, 400, [
    'stroke' => [0, 0, 0],
    'lineWidth' => 1,
]);

// Polygon
$canvas->drawPolygon([
    [100, 300], [150, 350], [200, 300], [150, 250]
], [
    'fill' => [200, 255, 200],
    'stroke' => [0, 128, 0],
]);
```

---

## PDF Parsing

### Reading PDF Information

```php
use PdfLib\Parser\PdfParser;

$parser = PdfParser::parseFile('document.pdf');

// Document info
$info = $parser->getInfo();
echo "Title: " . $info?->get('Title')?->getValue() . "\n";
echo "Author: " . $info?->get('Author')?->getValue() . "\n";
echo "Pages: " . $parser->getPageCount() . "\n";

// Get specific page
$page = $parser->getPage(1);

// Parse from content
$parser = PdfParser::parseString($pdfContent);
```

### Extracting Placeholder Coordinates

If you have a PDF template with form fields as placeholders, you can extract their coordinates to place content at the exact same positions:

```php
use PdfLib\Form\FormParser;

// Load template PDF with form field placeholders
$parser = FormParser::fromFile('template.pdf');

// Get all form fields and their coordinates
foreach ($parser->getFields() as $name => $field) {
    echo "$name:\n";
    echo "  Position: x={$field->getX()}, y={$field->getY()}\n";
    echo "  Size: {$field->getWidth()} x {$field->getHeight()}\n";
    echo "  Page: {$field->getPage()}\n";
    echo "  Rect: " . implode(', ', $field->getRect()) . "\n";
}

// Get a specific placeholder
$qrPlaceholder = $parser->getField('qr_code_field');
$signaturePlaceholder = $parser->getField('signature_field');
```

### Using Placeholder Coordinates

```php
use PdfLib\Form\FormParser;
use PdfLib\Document\PdfDocument;

// 1. Extract coordinates from template
$parser = FormParser::fromFile('invoice_template.pdf');
$logoField = $parser->getField('logo_placeholder');
$qrField = $parser->getField('qr_placeholder');

// 2. Create new document and place content at those coordinates
$pdf = PdfDocument::create();
$page = $pdf->addPage();

// Place logo at placeholder position
$page->addImage('company_logo.png',
    $logoField->getX(),
    $logoField->getY(),
    $logoField->getWidth(),
    $logoField->getHeight()
);

// Place QR code at placeholder position
$page->addImage('qrcode.png',
    $qrField->getX(),
    $qrField->getY(),
    $qrField->getWidth(),
    $qrField->getHeight()
);

$pdf->save('invoice.pdf');
```

### Creating Template with Placeholders

To create a template PDF with placeholders:

1. **Using Adobe Acrobat**: Add form fields where you want placeholders
2. **Using LibreOffice**: Create text fields in your document and export as PDF
3. **Using this library**: Create form fields programmatically

```php
use PdfLib\Document\PdfDocument;
use PdfLib\Form\TextField;
use PdfLib\Form\AcroForm;

// Create template with form field placeholders
$pdf = PdfDocument::create();
$page = $pdf->addPage();

// Add visible content
$page->addText('Invoice Template', 100, 800, ['fontSize' => 24]);

// Create form with placeholder fields
$form = AcroForm::create();

// Logo placeholder (top-left)
$logoField = TextField::create('logo_placeholder')
    ->setPosition(50, 750)
    ->setSize(150, 50);
$form->addField($logoField);

// QR code placeholder (top-right)
$qrField = TextField::create('qr_placeholder')
    ->setPosition(450, 750)
    ->setSize(100, 100);
$form->addField($qrField);

// Signature placeholder (bottom)
$sigField = TextField::create('signature_placeholder')
    ->setPosition(350, 100)
    ->setSize(200, 50);
$form->addField($sigField);

$pdf->setAcroForm($form);
$pdf->save('invoice_template.pdf');
```

---

## Manipulation

### Merging

```php
use PdfLib\Manipulation\Merger;

$merger = new Merger();

// Add entire documents
$merger->addFile('doc1.pdf');
$merger->addFile('doc2.pdf');

// Add specific pages
$merger->addFile('doc3.pdf', pages: [1, 3, 5]);      // Pages 1, 3, 5
$merger->addFile('doc4.pdf', pages: '1-5');          // Pages 1 through 5
$merger->addFile('doc5.pdf', pages: '1-3,7,9-12');   // Ranges

// Add from content
$merger->addContent($pdfContent);

// Merge and save
$merger->save('merged.pdf');

// Or get content
$content = $merger->merge();
```

### Splitting

```php
use PdfLib\Manipulation\Splitter;

$splitter = new Splitter('document.pdf');

// Extract specific pages
$result = $splitter->extractPages([1, 3, 5]);
$result->save('extracted.pdf');

// Extract ranges
$first5 = $splitter->extractFirst(5);
$last3 = $splitter->extractLast(3);
$range = $splitter->extractRange(5, 10);

// Split into chunks
$chunks = $splitter->splitByPageCount(10);
foreach ($chunks as $i => $chunk) {
    $chunk->save("chunk-{$i}.pdf");
}

// Odd/even pages
$oddPages = $splitter->extractOddPages();
$evenPages = $splitter->extractEvenPages();
```

### Rotating

```php
use PdfLib\Manipulation\Rotator;

$rotator = new Rotator('document.pdf');

// Rotate all pages
$rotator->rotateAllPages(90)->save('rotated.pdf');

// Rotate specific pages
$rotator->rotatePage(1, 90)
        ->rotatePage(3, 180)
        ->save('rotated.pdf');

// Convenience methods
$rotator->rotateClockwise();         // 90°
$rotator->rotateCounterClockwise();  // 270°
$rotator->rotateUpsideDown();        // 180°

// Rotate odd/even pages
$rotator->rotateOddPages(90);
$rotator->rotateEvenPages(90);
```

### Cropping

```php
use PdfLib\Manipulation\Cropper;

$cropper = new Cropper('document.pdf');

// Crop to standard size
$cropper->cropToSize('A5')->save('cropped.pdf');
$cropper->cropToSize('Letter')->save('cropped.pdf');

// Add margins (points)
$cropper->addMargins(50)->save('with-margins.pdf');
$cropper->addMargins(50, 30, 50, 30)->save('with-margins.pdf');  // top, right, bottom, left

// Custom crop box
$cropper->setCropBox(0, 0, 400, 600)->save('cropped.pdf');
```

### Stamping & Watermarks

```php
use PdfLib\Manipulation\Stamper;

$stamper = new Stamper('document.pdf');

// Watermark
$stamper->addWatermark('CONFIDENTIAL', rotation: 45, opacity: 0.3);
$stamper->addWatermark('DRAFT', fontSize: 72, color: [255, 0, 0]);

// Page numbers
$stamper->addPageNumbers('Page {n} of {total}', position: 'bottom-center');
$stamper->addPageNumbers('{n}', position: 'bottom-right');

// Headers and footers
$stamper->addHeader('Company Name', position: 'center');
$stamper->addFooter('© 2024 Company', position: 'left');

// Date stamp
$stamper->addDateStamp('Generated: {date}', position: 'top-right');

$stamper->save('stamped.pdf');
```

### Optimization

```php
use PdfLib\Manipulation\Optimizer;

$optimizer = new Optimizer('large-document.pdf');

// Standard optimization
$optimizer->optimize()->save('optimized.pdf');

// Optimization levels
$optimizer->optimizeMinimal()->save('optimized.pdf');   // Fast, minimal
$optimizer->optimizeMaximum()->save('optimized.pdf');   // Aggressive

// Get statistics
$stats = $optimizer->getStatistics();
echo "Original: {$stats['original_size']} bytes\n";
echo "Optimized: {$stats['optimized_size']} bytes\n";
echo "Reduction: {$stats['reduction']}%\n";
```

---

## Digital Signatures

### Single Signature

```php
use PdfLib\Security\Signature\Signer;
use PdfLib\Security\Signature\SignatureField;

$signer = new Signer();

// Load PDF and certificate
$signer->loadFile('document.pdf');
$signer->loadCertificate('certificate.p12', 'password');

// Set signature metadata
$signer->setReason('Document Approval');
$signer->setLocation('New York, USA');
$signer->setContactInfo('john@example.com');

// Sign and save
$signer->save('signed.pdf');

// With visible signature
$field = SignatureField::create('Signature1')
    ->setPosition(100, 100)
    ->setSize(200, 50)
    ->setSignerName('John Doe');

$signer->setSignatureField($field);
$signer->save('signed.pdf');
```

### Certificate Formats

```php
// PKCS#12 (.p12, .pfx)
$signer->loadCertificate('certificate.p12', 'password');

// PEM format (separate cert and key)
$signer->loadCertificate('certificate.pem', '', 'private-key.pem');

// From PEM strings
$signer->setCertificateFromPem($certPem, $keyPem);
```

### Multiple Signatures

Apply multiple signatures in one operation:

```php
use PdfLib\Security\Signature\Signer;

$signed = Signer::multiSignContent($pdfContent, [
    [
        'cert' => 'approver.p12',
        'password' => 'pass1',
        'reason' => 'Approved by Department',
        'location' => 'New York',
    ],
    [
        'cert' => 'reviewer.p12',
        'password' => 'pass2',
        'reason' => 'Reviewed for Compliance',
        'location' => 'Chicago',
    ],
    [
        'cert' => 'manager.p12',
        'password' => 'pass3',
        'reason' => 'Final Management Approval',
        'location' => 'Los Angeles',
    ],
]);

file_put_contents('multi-signed.pdf', $signed);
```

### Sequential Signing Workflow

Real-world workflow where each user signs and downloads:

```php
use PdfLib\Security\Signature\Signer;
use PdfLib\Security\Signature\SignatureField;

// === User 1 signs original document ===
$signer1 = new Signer();
$signedByUser1 = $signer1
    ->loadFile('contract.pdf')
    ->loadCertificate('user1.p12', 'password')
    ->setReason('Department Head Approval')
    ->setLocation('New York')
    ->sign();

// User 1 downloads
file_put_contents('contract_user1.pdf', $signedByUser1);

// === User 2 signs document from User 1 ===
$signer2 = new Signer();
$signedByUser1And2 = $signer2
    ->loadContent($signedByUser1)  // Load User 1's signed document
    ->loadCertificate('user2.p12', 'password')
    ->setReason('Legal Review Complete')
    ->setLocation('Chicago')
    ->sign();

// User 2 downloads
file_put_contents('contract_user1_user2.pdf', $signedByUser1And2);

// === User 3 signs document from User 2 ===
$signer3 = new Signer();
$fullySignedPdf = $signer3
    ->loadContent($signedByUser1And2)  // Load document with 2 signatures
    ->loadCertificate('user3.p12', 'password')
    ->setReason('CEO Final Approval')
    ->setLocation('Los Angeles')
    ->sign();

// User 3 downloads the fully signed document
file_put_contents('contract_final.pdf', $fullySignedPdf);
```

Each signature is preserved using PDF incremental updates per the PDF standard.

---

## Encryption

### Password Protection

```php
use PdfLib\Security\Encryption\Encryptor;

$encryptor = new Encryptor();
$encryptor->loadFile('document.pdf');

// Set passwords
$encryptor->setUserPassword('user123');      // Required to open
$encryptor->setOwnerPassword('admin456');    // Required for full access

// Set permissions
$encryptor->setPermissions([
    'print' => true,
    'modify' => false,
    'copy' => false,
    'annotate' => true,
]);

// Encrypt with different methods
$encryptor->encryptAes128();  // AES-128 (default)
$encryptor->encryptAes256();  // AES-256 (strongest)
$encryptor->encryptRc4();     // RC4 (legacy compatibility)

$encryptor->save('encrypted.pdf');
```

### Quick Encryption

```php
$encryptor = new Encryptor();
$content = $encryptor
    ->loadFile('document.pdf')
    ->setUserPassword('secret')
    ->encryptAes256();

file_put_contents('encrypted.pdf', $content);
```

---

## Form Filling

### Filling PDF Forms

```php
use PdfLib\Form\FormFiller;
use PdfLib\Form\FormFlattener;

// Get form fields
$filler = new FormFiller('form.pdf');
$fields = $filler->getFields();
print_r($fields);
// ['name' => ['type' => 'text'], 'email' => ['type' => 'text'], ...]

// Fill form
$filler->setFieldValue('name', 'John Doe');
$filler->setFieldValue('email', 'john@example.com');
$filler->setFieldValue('agree', true);  // Checkbox
$filler->setFieldValue('country', 'USA');  // Dropdown

$filled = $filler->fill();
file_put_contents('filled.pdf', $filled);

// Flatten form (make non-editable)
$flattener = new FormFlattener();
$flattener->loadContent($filled);
$flattened = $flattener->flatten();
file_put_contents('flattened.pdf', $flattened);
```

---

## Converters

PDF-Lib includes pure PHP converters for HTML to PDF and PDF to Office formats (DOCX, XLSX).

### HTML to PDF

Convert HTML content to PDF documents:

```php
use PdfLib\Html\HtmlConverter;

// Simple conversion
$converter = HtmlConverter::create();
$pdf = $converter->convert('<h1>Hello World</h1><p>This is a paragraph.</p>');
$pdf->save('output.pdf');

// With options
$converter = HtmlConverter::create()
    ->setPageSize('A4')
    ->setLandscape(true)
    ->setMargins(50, 50, 50, 50)
    ->setDefaultFont('Helvetica', 12);

$pdf = $converter->convert($html);
$pdf->save('styled.pdf');

// From file
$pdf = $converter->convertFile('document.html');
$pdf->save('from-file.pdf');

// From URL
$pdf = $converter->convertUrl('https://example.com/page.html');
$pdf->save('from-url.pdf');
```

**Supported HTML Elements:**
- Headings (h1-h6)
- Paragraphs, divs, spans
- Text formatting (bold, italic, underline)
- Links
- Lists (ordered and unordered)
- Tables with borders
- Blockquotes
- Code blocks (pre, code)
- Images
- Horizontal rules

**Supported CSS:**
- Inline styles (`style` attribute)
- Embedded styles (`<style>` tags)
- Colors, fonts, sizes
- Margins, padding
- Background colors
- Borders

### PDF to DOCX

Convert PDF documents to Microsoft Word format:

```php
use PdfLib\Export\Docx\PdfToDocxConverter;

// Simple conversion
$converter = new PdfToDocxConverter();
$converter->convert('document.pdf', 'output.docx');

// With options
$converter = PdfToDocxConverter::create()
    ->setPageSize('A4')
    ->setFont('Times New Roman', 12)
    ->preserveLayout(true)
    ->setPages([1, 2, 3]);  // Specific pages only

$converter->convert('document.pdf', 'output.docx');

// Get as binary (for HTTP response)
$content = $converter->convertToString('document.pdf');
```

**Create DOCX directly:**

```php
use PdfLib\Export\Docx\DocxWriter;
use PdfLib\Export\Docx\DocxParagraph;

$writer = new DocxWriter();
$writer->setPageSizeA4();
$writer->setDefaultFont('Arial', 12);

// Add headings and text
$writer->addHeading('Document Title', 1);
$writer->addText('This is body text.');

// Add formatted paragraph
$paragraph = new DocxParagraph();
$paragraph->addRun('Normal text, ');
$paragraph->addRun('bold text', true);
$paragraph->addRun(', and ');
$paragraph->addRun('italic text', false, true);
$writer->addParagraph($paragraph);

// Add page break
$writer->addPageBreak();

$writer->save('document.docx');
```

### PDF to XLSX

Convert PDF documents to Microsoft Excel format:

```php
use PdfLib\Export\Xlsx\PdfToXlsxConverter;

// Simple conversion
$converter = new PdfToXlsxConverter();
$converter->convert('document.pdf', 'output.xlsx');

// With table detection
$converter = PdfToXlsxConverter::create()
    ->detectTables(true)
    ->setColumnTolerance(15)
    ->setRowTolerance(8);

$converter->convert('document.pdf', 'output.xlsx');

// One sheet per page
$converter = PdfToXlsxConverter::create()
    ->setSheetPerPage(true);

$converter->convert('multipage.pdf', 'output.xlsx');

// Get as binary (for HTTP response)
$content = $converter->convertToString('document.pdf');
```

**Create XLSX directly:**

```php
use PdfLib\Export\Xlsx\XlsxWriter;

$writer = new XlsxWriter();

// Create sheet
$sheet = $writer->addSheet('Sales Data');

// Add header row (style 1 = bold)
$sheet->setRow(1, ['Product', 'Q1', 'Q2', 'Q3', 'Q4', 'Total'], 1);

// Add data rows
$sheet->setRow(2, ['Widget A', 1000, 1200, 1100, 1500, '=SUM(B2:E2)']);
$sheet->setRow(3, ['Widget B', 800, 900, 1000, 1100, '=SUM(B3:E3)']);

// Total row with formulas
$sheet->setRow(4, ['Total', '=SUM(B2:B3)', '=SUM(C2:C3)', '=SUM(D2:D3)', '=SUM(E2:E3)', '=SUM(F2:F3)'], 1);

// Set column widths
$sheet->setColumnWidth(1, 15);
$sheet->setColumnWidth(6, 12);

// Add another sheet
$summarySheet = $writer->addSheet('Summary');
$summarySheet->setCellByAddress('A1', 'Total Sales');
$summarySheet->setCellByAddress('B1', '=SUM(\'Sales Data\'!F2:F3)');

$writer->save('report.xlsx');
```

**XLSX Features:**
- Multiple sheets
- Cell addressing (A1, B2, etc.)
- Formulas (=SUM, =AVERAGE, etc.)
- Column widths
- Row heights
- Number, text, and boolean values
- Bold style for headers

---

## Laravel Integration

Install the Laravel package:

```bash
composer require pdf-lib/laravel
```

The package auto-registers. Use the `PDF` facade:

```php
use PdfLib\Laravel\Facades\PDF;

// Create and download
return PDF::download(
    PDF::create()->addPage()->addText('Hello', 100, 750),
    'document.pdf'
);

// Merge and download
return PDF::mergeAndDownload(['doc1.pdf', 'doc2.pdf'], 'merged.pdf');

// Sign with multiple signers
$signed = PDF::multiSign($pdf, [
    ['cert' => 'user1.p12', 'password' => 'pass1', 'reason' => 'Approved'],
    ['cert' => 'user2.p12', 'password' => 'pass2', 'reason' => 'Reviewed'],
]);

return PDF::download($signed, 'signed.pdf');

// Store to disk
PDF::store($pdf, 'documents/report.pdf', 's3');
```

See [laravel-pdf README](../laravel-pdf/README.md) for full documentation.

---

## Examples

Check the `examples/` directory for complete working examples:

- `sequential-signing.php` - Multi-user signing workflow
- `extract-placeholder-coordinates.php` - Get coordinates from PDF placeholders
- `html-to-pdf.php` - HTML to PDF conversion examples
- `pdf-to-docx.php` - PDF to DOCX conversion examples
- `pdf-to-xlsx.php` - PDF to XLSX conversion examples

---

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test suites
./vendor/bin/phpunit tests/SecurityTest.php
./vendor/bin/phpunit tests/ManipulationTest.php

# With coverage
./vendor/bin/phpunit --coverage-html coverage/
```

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

1. Fork the repository
2. Create a feature branch
3. Write tests for your changes
4. Ensure all tests pass
5. Submit a pull request