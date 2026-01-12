<?php

declare(strict_types=1);

namespace PdfLib\Parser;

use PdfLib\Exception\ParseException;

/**
 * PDF Lexer - Tokenizes PDF byte stream.
 *
 * Handles PDF's lexical structure including:
 * - Whitespace and comments
 * - Delimiters
 * - Regular characters
 * - Tokens (numbers, names, keywords)
 */
final class Lexer
{
    private string $data;
    private int $length;
    private int $position = 0;

    /**
     * PDF whitespace characters.
     */
    private const WHITESPACE = [
        0x00, // Null
        0x09, // Tab
        0x0A, // Line feed
        0x0C, // Form feed
        0x0D, // Carriage return
        0x20, // Space
    ];

    /**
     * PDF delimiter characters.
     */
    private const DELIMITERS = ['(', ')', '<', '>', '[', ']', '{', '}', '/', '%'];

    public function __construct(string $data)
    {
        $this->data = $data;
        $this->length = strlen($data);
    }

    /**
     * Get current position in the stream.
     */
    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * Set position in the stream.
     */
    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    /**
     * Check if we've reached the end of the stream.
     */
    public function isEof(): bool
    {
        return $this->position >= $this->length;
    }

    /**
     * Get total length of the data.
     */
    public function getLength(): int
    {
        return $this->length;
    }

    /**
     * Read a single byte without advancing position.
     */
    public function peek(): ?string
    {
        if ($this->isEof()) {
            return null;
        }
        return $this->data[$this->position];
    }

    /**
     * Peek at a byte at offset from current position.
     */
    public function peekAt(int $offset): ?string
    {
        $pos = $this->position + $offset;
        if ($pos < 0 || $pos >= $this->length) {
            return null;
        }
        return $this->data[$pos];
    }

    /**
     * Read a single byte and advance position.
     */
    public function read(): ?string
    {
        if ($this->isEof()) {
            return null;
        }
        return $this->data[$this->position++];
    }

    /**
     * Read multiple bytes.
     */
    public function readBytes(int $count): string
    {
        $result = substr($this->data, $this->position, $count);
        $this->position += strlen($result);
        return $result;
    }

    /**
     * Skip whitespace and comments.
     */
    public function skipWhitespace(): void
    {
        while (!$this->isEof()) {
            $char = $this->data[$this->position];
            $ord = ord($char);

            if (in_array($ord, self::WHITESPACE, true)) {
                $this->position++;
            } elseif ($char === '%') {
                // Skip comment until end of line
                $this->skipComment();
            } else {
                break;
            }
        }
    }

    /**
     * Skip a comment (from % to end of line).
     */
    private function skipComment(): void
    {
        while (!$this->isEof()) {
            $char = $this->read();
            if ($char === "\n" || $char === "\r") {
                // Handle \r\n as single line ending
                if ($char === "\r" && $this->peek() === "\n") {
                    $this->read();
                }
                break;
            }
        }
    }

    /**
     * Check if character is whitespace.
     */
    public function isWhitespace(?string $char): bool
    {
        if ($char === null) {
            return false;
        }
        return in_array(ord($char), self::WHITESPACE, true);
    }

    /**
     * Check if character is a delimiter.
     */
    public function isDelimiter(?string $char): bool
    {
        if ($char === null) {
            return false;
        }
        return in_array($char, self::DELIMITERS, true);
    }

    /**
     * Check if character is a regular character (not whitespace or delimiter).
     */
    public function isRegular(?string $char): bool
    {
        return $char !== null && !$this->isWhitespace($char) && !$this->isDelimiter($char);
    }

    /**
     * Read a token (sequence of regular characters).
     */
    public function readToken(): string
    {
        $this->skipWhitespace();
        $token = '';

        while ($this->isRegular($this->peek())) {
            $token .= $this->read();
        }

        return $token;
    }

    /**
     * Read a number (integer or real).
     */
    public function readNumber(): int|float
    {
        $this->skipWhitespace();
        $number = '';
        $hasDecimal = false;

        // Handle sign
        $char = $this->peek();
        if ($char === '+' || $char === '-') {
            $number .= $this->read();
        }

        while (!$this->isEof()) {
            $char = $this->peek();

            if ($char >= '0' && $char <= '9') {
                $number .= $this->read();
            } elseif ($char === '.' && !$hasDecimal) {
                $hasDecimal = true;
                $number .= $this->read();
            } else {
                break;
            }
        }

        if ($hasDecimal) {
            return (float) $number;
        }
        return (int) $number;
    }

    /**
     * Read a literal string (enclosed in parentheses).
     */
    public function readLiteralString(): string
    {
        $this->skipWhitespace();

        if ($this->read() !== '(') {
            throw ParseException::unexpectedToken('(', $this->peek() ?? 'EOF', $this->position);
        }

        $result = '';
        $depth = 1;

        while (!$this->isEof() && $depth > 0) {
            $char = $this->read();

            if ($char === '\\') {
                // Escape sequence
                $result .= '\\' . ($this->read() ?? '');
            } elseif ($char === '(') {
                $depth++;
                $result .= $char;
            } elseif ($char === ')') {
                $depth--;
                if ($depth > 0) {
                    $result .= $char;
                }
            } else {
                $result .= $char;
            }
        }

        if ($depth !== 0) {
            throw ParseException::unexpectedEndOfFile($this->position);
        }

        return $result;
    }

    /**
     * Read a hexadecimal string (enclosed in angle brackets).
     */
    public function readHexString(): string
    {
        $this->skipWhitespace();

        if ($this->read() !== '<') {
            throw ParseException::unexpectedToken('<', $this->peek() ?? 'EOF', $this->position);
        }

        $hex = '';

        while (!$this->isEof()) {
            $char = $this->peek();

            if ($char === '>') {
                $this->read();
                break;
            } elseif (ctype_xdigit($char) || $this->isWhitespace($char)) {
                if (!$this->isWhitespace($char)) {
                    $hex .= $char;
                }
                $this->read();
            } else {
                throw ParseException::invalidObject("Invalid hex character: $char", $this->position);
            }
        }

        return $hex;
    }

    /**
     * Read a name (starts with /).
     */
    public function readName(): string
    {
        $this->skipWhitespace();

        if ($this->read() !== '/') {
            throw ParseException::unexpectedToken('/', $this->peek() ?? 'EOF', $this->position);
        }

        $name = '';

        while ($this->isRegular($this->peek())) {
            $name .= $this->read();
        }

        return $name;
    }

    /**
     * Check if the next characters match a keyword.
     */
    public function matchKeyword(string $keyword): bool
    {
        $len = strlen($keyword);
        $pos = $this->position;

        if ($pos + $len > $this->length) {
            return false;
        }

        for ($i = 0; $i < $len; $i++) {
            if ($this->data[$pos + $i] !== $keyword[$i]) {
                return false;
            }
        }

        // Make sure keyword ends at a delimiter or whitespace
        if ($pos + $len < $this->length) {
            $nextChar = $this->data[$pos + $len];
            if ($this->isRegular($nextChar)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Skip a keyword if it matches.
     */
    public function skipKeyword(string $keyword): bool
    {
        if ($this->matchKeyword($keyword)) {
            $this->position += strlen($keyword);
            return true;
        }
        return false;
    }

    /**
     * Search backwards for a pattern from a given position.
     */
    public function searchBackward(string $pattern, int $fromPosition): int
    {
        $pos = strrpos($this->data, $pattern, $fromPosition - $this->length);
        return $pos !== false ? $pos : -1;
    }

    /**
     * Search forward for a pattern from current position.
     */
    public function searchForward(string $pattern): int
    {
        $pos = strpos($this->data, $pattern, $this->position);
        return $pos !== false ? $pos : -1;
    }

    /**
     * Get a substring from the data.
     */
    public function getSubstring(int $start, int $length): string
    {
        return substr($this->data, $start, $length);
    }

    /**
     * Read until a pattern is found.
     */
    public function readUntil(string $pattern): string
    {
        $pos = strpos($this->data, $pattern, $this->position);
        if ($pos === false) {
            $result = substr($this->data, $this->position);
            $this->position = $this->length;
            return $result;
        }

        $result = substr($this->data, $this->position, $pos - $this->position);
        $this->position = $pos;
        return $result;
    }

    /**
     * Read a line (until newline).
     */
    public function readLine(): string
    {
        $line = '';

        while (!$this->isEof()) {
            $char = $this->read();

            if ($char === "\n") {
                break;
            } elseif ($char === "\r") {
                if ($this->peek() === "\n") {
                    $this->read();
                }
                break;
            }

            $line .= $char;
        }

        return $line;
    }
}
