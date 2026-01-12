<?php

declare(strict_types=1);

namespace PdfLib\Manipulation;

/**
 * PDF Optimizer - Reduce PDF file size.
 *
 * @example
 * ```php
 * $optimizer = new Optimizer('document.pdf');
 * $optimizer->setLevel(Optimizer::LEVEL_MAXIMUM)
 *           ->save('optimized.pdf');
 *
 * $stats = $optimizer->getStatistics();
 * echo "Reduced by {$stats['compressionRatio']}%";
 * ```
 */
final class Optimizer
{
    // Optimization levels
    public const LEVEL_MINIMAL = 1;   // Stream compression only
    public const LEVEL_STANDARD = 2;  // Streams + unused object removal
    public const LEVEL_MAXIMUM = 3;   // All optimizations + duplicate detection

    private string $content = '';
    private string $optimizedContent = '';
    private string $version = '1.7';
    private int $level = self::LEVEL_STANDARD;
    private int $compressionLevel = 6;

    /** @var array<int, array{content: string, stream: string}> */
    private array $objects = [];

    /** @var array<int, bool> */
    private array $usedObjects = [];

    /** @var array<string, int> Hash => object number */
    private array $duplicates = [];

    /** @var array<int, int> Old object number => new object number */
    private array $objectMap = [];

    private int $objectsRemoved = 0;
    private int $duplicatesRemoved = 0;

    public function __construct(?string $filePath = null)
    {
        if ($filePath !== null) {
            $this->loadFile($filePath);
        }
    }

    /**
     * Load PDF from file.
     */
    public function loadFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("PDF file not found: $filePath");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Could not read PDF file: $filePath");
        }

        return $this->loadContent($content);
    }

    /**
     * Load PDF from string content.
     */
    public function loadContent(string $content): self
    {
        if (!str_starts_with($content, '%PDF-')) {
            throw new \InvalidArgumentException('Invalid PDF content');
        }

        $this->content = $content;
        $this->optimizedContent = '';
        $this->objects = [];
        $this->usedObjects = [];
        $this->duplicates = [];
        $this->objectMap = [];
        $this->objectsRemoved = 0;
        $this->duplicatesRemoved = 0;

        // Extract version
        if (preg_match('/^%PDF-(\d+\.\d+)/', $content, $matches)) {
            $this->version = $matches[1];
        }

        return $this;
    }

    /**
     * Set optimization level.
     */
    public function setLevel(int $level): self
    {
        if (!in_array($level, [self::LEVEL_MINIMAL, self::LEVEL_STANDARD, self::LEVEL_MAXIMUM], true)) {
            throw new \InvalidArgumentException('Invalid optimization level');
        }

        $this->level = $level;

        // Set compression level based on optimization level
        $this->compressionLevel = match ($level) {
            self::LEVEL_MINIMAL => 6,
            self::LEVEL_STANDARD => 9,
            self::LEVEL_MAXIMUM => 9,
        };

        return $this;
    }

    /**
     * Set compression level (0-9).
     */
    public function setCompressionLevel(int $level): self
    {
        $this->compressionLevel = max(0, min(9, $level));
        return $this;
    }

    /**
     * Optimize the PDF and return content.
     */
    public function optimize(): string
    {
        $this->ensureLoaded();

        // Extract all objects
        $this->extractObjects();

        // Remove unused objects (standard and maximum levels)
        if ($this->level >= self::LEVEL_STANDARD) {
            $this->removeUnusedObjects();
        }

        // Remove duplicate objects (maximum level only)
        if ($this->level >= self::LEVEL_MAXIMUM) {
            $this->removeDuplicateObjects();
        }

        // Rebuild PDF with optimized objects
        $this->optimizedContent = $this->rebuildPdf();

        return $this->optimizedContent;
    }

    /**
     * Optimize and save to file.
     */
    public function save(string $outputPath): bool
    {
        $content = $this->optimize();
        return file_put_contents($outputPath, $content) !== false;
    }

    /**
     * Optimize and save to file (alias for save).
     */
    public function optimizeToFile(string $outputPath): bool
    {
        return $this->save($outputPath);
    }

    /**
     * Get optimization statistics.
     *
     * @return array{
     *     originalSize: int,
     *     optimizedSize: int,
     *     compressionRatio: float,
     *     objectsRemoved: int,
     *     duplicatesRemoved: int
     * }
     */
    public function getStatistics(): array
    {
        $originalSize = strlen($this->content);
        $optimizedSize = strlen($this->optimizedContent ?: $this->content);

        $ratio = 0.0;
        if ($originalSize > 0) {
            $ratio = (1 - ($optimizedSize / $originalSize)) * 100;
        }

        return [
            'originalSize' => $originalSize,
            'optimizedSize' => $optimizedSize,
            'compressionRatio' => round($ratio, 2),
            'objectsRemoved' => $this->objectsRemoved,
            'duplicatesRemoved' => $this->duplicatesRemoved,
        ];
    }

    /**
     * Ensure PDF is loaded.
     */
    private function ensureLoaded(): void
    {
        if ($this->content === '') {
            throw new \RuntimeException('No PDF loaded. Call loadFile() or loadContent() first.');
        }
    }

    /**
     * Extract all objects from PDF.
     */
    private function extractObjects(): void
    {
        // Match objects: "n 0 obj ... endobj"
        $pattern = '/(\d+)\s+0\s+obj\s*(.*?)\s*endobj/s';

        if (preg_match_all($pattern, $this->content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $objNum = (int) $match[1];
                $objContent = $match[2];

                // Separate stream data if present
                $stream = '';
                if (preg_match('/(.*)stream\r?\n(.*?)\r?\nendstream/s', $objContent, $streamMatch)) {
                    $objContent = $streamMatch[1];
                    $stream = $streamMatch[2];

                    // Try to compress stream
                    $stream = $this->optimizeStream($objContent, $stream);
                }

                $this->objects[$objNum] = [
                    'content' => $objContent,
                    'stream' => $stream,
                ];
            }
        }
    }

    /**
     * Optimize a stream (compress if not already compressed).
     */
    private function optimizeStream(string $dictionary, string $streamData): string
    {
        // Check if already compressed
        if (str_contains($dictionary, '/Filter')) {
            // Already has a filter, check if it's FlateDecode
            if (str_contains($dictionary, '/FlateDecode')) {
                // Try to re-compress with higher level
                $decompressed = @gzuncompress($streamData);
                if ($decompressed === false) {
                    $decompressed = @gzinflate($streamData);
                }

                if ($decompressed !== false) {
                    $recompressed = @gzcompress($decompressed, $this->compressionLevel);
                    if ($recompressed !== false && strlen($recompressed) < strlen($streamData)) {
                        return $recompressed;
                    }
                }
            }
            return $streamData;
        }

        // Compress uncompressed stream
        $compressed = @gzcompress($streamData, $this->compressionLevel);
        if ($compressed !== false && strlen($compressed) < strlen($streamData)) {
            return $compressed;
        }

        return $streamData;
    }

    /**
     * Remove unused objects by tracing from root.
     */
    private function removeUnusedObjects(): void
    {
        // Find root object
        if (preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $this->content, $match)) {
            $rootNum = (int) $match[1];
            $this->traceReferences($rootNum);
        }

        // Find info object
        if (preg_match('/\/Info\s+(\d+)\s+\d+\s+R/', $this->content, $match)) {
            $infoNum = (int) $match[1];
            $this->traceReferences($infoNum);
        }

        // Count removed objects
        $originalCount = count($this->objects);
        $this->objects = array_intersect_key($this->objects, $this->usedObjects);
        $this->objectsRemoved = $originalCount - count($this->objects);
    }

    /**
     * Recursively trace object references.
     */
    private function traceReferences(int $objNum): void
    {
        if (isset($this->usedObjects[$objNum]) || !isset($this->objects[$objNum])) {
            return;
        }

        $this->usedObjects[$objNum] = true;

        $content = $this->objects[$objNum]['content'];

        // Find all references in this object
        if (preg_match_all('/(\d+)\s+\d+\s+R/', $content, $matches)) {
            foreach ($matches[1] as $refNum) {
                $this->traceReferences((int) $refNum);
            }
        }
    }

    /**
     * Remove duplicate objects.
     */
    private function removeDuplicateObjects(): void
    {
        $hashes = [];

        foreach ($this->objects as $objNum => $obj) {
            $hash = md5($obj['content'] . $obj['stream']);

            if (isset($hashes[$hash])) {
                // This is a duplicate
                $this->duplicates[$objNum] = $hashes[$hash];
                unset($this->objects[$objNum]);
                $this->duplicatesRemoved++;
            } else {
                $hashes[$hash] = $objNum;
            }
        }
    }

    /**
     * Rebuild PDF with optimized objects.
     */
    private function rebuildPdf(): string
    {
        $output = "%PDF-{$this->version}\n";
        $output .= "%\xE2\xE3\xCF\xD3\n"; // Binary marker

        $offsets = [];
        $newObjNum = 1;

        // Create object number mapping
        foreach (array_keys($this->objects) as $oldNum) {
            $this->objectMap[$oldNum] = $newObjNum++;
        }

        // Add duplicate mappings
        foreach ($this->duplicates as $oldNum => $targetNum) {
            $this->objectMap[$oldNum] = $this->objectMap[$targetNum];
        }

        // Write objects
        foreach ($this->objects as $oldNum => $obj) {
            $newNum = $this->objectMap[$oldNum];
            $offsets[$newNum] = strlen($output);

            // Update references in content
            $content = $this->updateReferences($obj['content']);

            $output .= "{$newNum} 0 obj\n";

            if ($obj['stream'] !== '') {
                // Update dictionary with new stream length
                $streamLen = strlen($obj['stream']);
                $content = preg_replace('/\/Length\s+\d+/', "/Length {$streamLen}", $content);

                // Add filter if we compressed
                if (!str_contains($content, '/Filter') && $this->isCompressed($obj['stream'])) {
                    $content = rtrim($content);
                    if (!str_ends_with($content, '>>')) {
                        $content .= "\n/Filter /FlateDecode\n>>";
                    } else {
                        $content = substr($content, 0, -2) . "\n/Filter /FlateDecode\n>>";
                    }
                }

                $output .= "{$content}\nstream\n{$obj['stream']}\nendstream\n";
            } else {
                $output .= "{$content}\n";
            }

            $output .= "endobj\n";
        }

        // Write xref table
        $xrefOffset = strlen($output);
        $numObjects = count($this->objects) + 1;

        $output .= "xref\n";
        $output .= "0 {$numObjects}\n";
        $output .= "0000000000 65535 f \n";

        for ($i = 1; $i < $numObjects; $i++) {
            $offset = $offsets[$i] ?? 0;
            $output .= sprintf("%010d 00000 n \n", $offset);
        }

        // Find root and info in mapped objects
        $rootNum = 1;
        $infoNum = null;

        if (preg_match('/\/Root\s+(\d+)\s+\d+\s+R/', $this->content, $match)) {
            $oldRoot = (int) $match[1];
            $rootNum = $this->objectMap[$oldRoot] ?? 1;
        }

        if (preg_match('/\/Info\s+(\d+)\s+\d+\s+R/', $this->content, $match)) {
            $oldInfo = (int) $match[1];
            $infoNum = $this->objectMap[$oldInfo] ?? null;
        }

        // Write trailer
        $output .= "trailer\n";
        $output .= "<<\n";
        $output .= "/Size {$numObjects}\n";
        $output .= "/Root {$rootNum} 0 R\n";
        if ($infoNum !== null) {
            $output .= "/Info {$infoNum} 0 R\n";
        }
        $output .= ">>\n";
        $output .= "startxref\n";
        $output .= "{$xrefOffset}\n";
        $output .= "%%EOF\n";

        return $output;
    }

    /**
     * Update object references in content.
     */
    private function updateReferences(string $content): string
    {
        return preg_replace_callback(
            '/(\d+)\s+(\d+)\s+R/',
            function ($matches) {
                $oldNum = (int) $matches[1];
                $newNum = $this->objectMap[$oldNum] ?? $oldNum;
                return "{$newNum} 0 R";
            },
            $content
        );
    }

    /**
     * Check if data appears to be compressed.
     */
    private function isCompressed(string $data): bool
    {
        // Check for zlib header (0x78)
        if (strlen($data) >= 2) {
            $header = ord($data[0]);
            return $header === 0x78;
        }
        return false;
    }
}
