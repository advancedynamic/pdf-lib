<?php

declare(strict_types=1);

namespace PdfLib\Parser\Object;

/**
 * PDF String object.
 *
 * Strings can be literal (enclosed in parentheses) or hexadecimal (enclosed in angle brackets).
 * This class stores the decoded string value and can output in either format.
 */
final class PdfString extends PdfObject
{
    public const FORMAT_LITERAL = 'literal';
    public const FORMAT_HEX = 'hex';

    private function __construct(
        private readonly string $value,
        private readonly string $format = self::FORMAT_LITERAL
    ) {
    }

    /**
     * Create a string with literal format (parentheses).
     */
    public static function literal(string $value): self
    {
        return new self($value, self::FORMAT_LITERAL);
    }

    /**
     * Create a string with hexadecimal format (angle brackets).
     */
    public static function hex(string $value): self
    {
        return new self($value, self::FORMAT_HEX);
    }

    /**
     * Create from a hexadecimal encoded string.
     */
    public static function fromHex(string $hexString): self
    {
        // Remove whitespace
        $hexString = preg_replace('/\s/', '', $hexString);

        // Pad odd-length hex strings
        if (strlen($hexString) % 2 !== 0) {
            $hexString .= '0';
        }

        $decoded = hex2bin($hexString);
        if ($decoded === false) {
            $decoded = '';
        }

        return new self($decoded, self::FORMAT_HEX);
    }

    /**
     * Create from a literal encoded string (with escape sequences).
     */
    public static function fromLiteral(string $literal): self
    {
        $decoded = self::decodeLiteral($literal);
        return new self($decoded, self::FORMAT_LITERAL);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the preferred output format.
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    public function toPdfString(): string
    {
        if ($this->format === self::FORMAT_HEX) {
            return $this->toHexString();
        }
        return $this->toLiteralString();
    }

    /**
     * Output as literal string with proper escaping.
     */
    public function toLiteralString(): string
    {
        $escaped = '';
        $len = strlen($this->value);

        for ($i = 0; $i < $len; $i++) {
            $char = $this->value[$i];
            $escaped .= match ($char) {
                "\n" => '\n',
                "\r" => '\r',
                "\t" => '\t',
                "\x08" => '\b',
                "\x0C" => '\f',
                '(' => '\(',
                ')' => '\)',
                '\\' => '\\\\',
                default => (ord($char) < 32 || ord($char) > 126)
                    ? sprintf('\\%03o', ord($char))
                    : $char,
            };
        }

        return '(' . $escaped . ')';
    }

    /**
     * Output as hexadecimal string.
     */
    public function toHexString(): string
    {
        return '<' . strtoupper(bin2hex($this->value)) . '>';
    }

    /**
     * Get string length in bytes.
     */
    public function length(): int
    {
        return strlen($this->value);
    }

    /**
     * Decode a literal string with escape sequences.
     */
    private static function decodeLiteral(string $literal): string
    {
        $result = '';
        $len = strlen($literal);
        $i = 0;

        while ($i < $len) {
            $char = $literal[$i];

            if ($char === '\\' && $i + 1 < $len) {
                $next = $literal[$i + 1];
                $i++;

                $result .= match ($next) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\x08",
                    'f' => "\x0C",
                    '(' => '(',
                    ')' => ')',
                    '\\' => '\\',
                    "\n" => '', // Line continuation
                    "\r" => '', // Line continuation
                    default => self::decodeOctal($literal, $i),
                };
            } else {
                $result .= $char;
            }

            $i++;
        }

        return $result;
    }

    /**
     * Decode an octal escape sequence.
     */
    private static function decodeOctal(string $literal, int &$pos): string
    {
        $octal = '';
        $start = $pos;

        for ($j = 0; $j < 3 && $pos < strlen($literal); $j++) {
            $char = $literal[$pos];
            if ($char >= '0' && $char <= '7') {
                $octal .= $char;
                if ($j < 2) {
                    $pos++;
                }
            } else {
                break;
            }
        }

        if ($octal !== '') {
            return chr(octdec($octal));
        }

        // Not an octal sequence, return the character after backslash
        $pos = $start;
        return $literal[$start];
    }
}
