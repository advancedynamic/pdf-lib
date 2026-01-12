<?php

declare(strict_types=1);

namespace PdfLib\Parser;

use PdfLib\Exception\ParseException;
use PdfLib\Parser\Object\PdfArray;
use PdfLib\Parser\Object\PdfBoolean;
use PdfLib\Parser\Object\PdfDictionary;
use PdfLib\Parser\Object\PdfName;
use PdfLib\Parser\Object\PdfNull;
use PdfLib\Parser\Object\PdfNumber;
use PdfLib\Parser\Object\PdfObject;
use PdfLib\Parser\Object\PdfReference;
use PdfLib\Parser\Object\PdfStream;
use PdfLib\Parser\Object\PdfString;

/**
 * Parses PDF objects from a lexer stream.
 */
final class ObjectParser
{
    public function __construct(
        private readonly Lexer $lexer
    ) {
    }

    /**
     * Parse the next object from the stream.
     */
    public function parse(): PdfObject
    {
        $this->lexer->skipWhitespace();

        if ($this->lexer->isEof()) {
            throw ParseException::unexpectedEndOfFile($this->lexer->getPosition());
        }

        $char = $this->lexer->peek();

        // Dictionary or hex string
        if ($char === '<') {
            if ($this->lexer->peekAt(1) === '<') {
                return $this->parseDictionary();
            }
            return $this->parseHexString();
        }

        // Literal string
        if ($char === '(') {
            return $this->parseLiteralString();
        }

        // Array
        if ($char === '[') {
            return $this->parseArray();
        }

        // Name
        if ($char === '/') {
            return $this->parseName();
        }

        // Number or reference
        if ($char === '+' || $char === '-' || $char === '.' || ctype_digit($char)) {
            return $this->parseNumberOrReference();
        }

        // Keywords (true, false, null)
        return $this->parseKeyword();
    }

    /**
     * Parse a dictionary object.
     */
    public function parseDictionary(): PdfDictionary
    {
        $this->lexer->skipWhitespace();

        // Consume <<
        if ($this->lexer->read() !== '<' || $this->lexer->read() !== '<') {
            throw ParseException::unexpectedToken('<<', 'unknown', $this->lexer->getPosition());
        }

        $entries = [];

        while (true) {
            $this->lexer->skipWhitespace();

            // Check for end of dictionary
            if ($this->lexer->peek() === '>' && $this->lexer->peekAt(1) === '>') {
                $this->lexer->read();
                $this->lexer->read();
                break;
            }

            // Parse key (must be a name)
            if ($this->lexer->peek() !== '/') {
                throw ParseException::invalidObject('Dictionary key must be a name', $this->lexer->getPosition());
            }

            $key = $this->parseName();
            $this->lexer->skipWhitespace();

            // Parse value
            $value = $this->parse();

            $entries[$key->getValue()] = $value;
        }

        return new PdfDictionary($entries);
    }

    /**
     * Parse an array object.
     */
    public function parseArray(): PdfArray
    {
        $this->lexer->skipWhitespace();

        if ($this->lexer->read() !== '[') {
            throw ParseException::unexpectedToken('[', 'unknown', $this->lexer->getPosition());
        }

        $items = [];

        while (true) {
            $this->lexer->skipWhitespace();

            if ($this->lexer->peek() === ']') {
                $this->lexer->read();
                break;
            }

            $items[] = $this->parse();
        }

        return new PdfArray($items);
    }

    /**
     * Parse a name object.
     */
    public function parseName(): PdfName
    {
        $encoded = $this->lexer->readName();
        return PdfName::fromEncoded($encoded);
    }

    /**
     * Parse a literal string object.
     */
    public function parseLiteralString(): PdfString
    {
        $literal = $this->lexer->readLiteralString();
        return PdfString::fromLiteral($literal);
    }

    /**
     * Parse a hexadecimal string object.
     */
    public function parseHexString(): PdfString
    {
        $hex = $this->lexer->readHexString();
        return PdfString::fromHex($hex);
    }

    /**
     * Parse a number or indirect reference.
     */
    public function parseNumberOrReference(): PdfNumber|PdfReference
    {
        $startPos = $this->lexer->getPosition();
        $num1 = $this->lexer->readNumber();

        // Check if this might be a reference (integer followed by integer followed by R)
        if (is_int($num1)) {
            $this->lexer->skipWhitespace();
            $posAfterNum1 = $this->lexer->getPosition();

            $char = $this->lexer->peek();
            if ($char !== null && ctype_digit($char)) {
                $num2 = $this->lexer->readNumber();

                if (is_int($num2)) {
                    $this->lexer->skipWhitespace();

                    if ($this->lexer->matchKeyword('R')) {
                        $this->lexer->skipKeyword('R');
                        return PdfReference::create($num1, $num2);
                    }
                }

                // Not a reference, backtrack
                $this->lexer->setPosition($posAfterNum1);
            }
        }

        return PdfNumber::create($num1);
    }

    /**
     * Parse a keyword (true, false, null).
     */
    public function parseKeyword(): PdfObject
    {
        $this->lexer->skipWhitespace();

        if ($this->lexer->skipKeyword('true')) {
            return PdfBoolean::true();
        }

        if ($this->lexer->skipKeyword('false')) {
            return PdfBoolean::false();
        }

        if ($this->lexer->skipKeyword('null')) {
            return PdfNull::instance();
        }

        // Read the unknown token for error message
        $token = $this->lexer->readToken();
        throw ParseException::invalidObject("Unknown keyword: $token", $this->lexer->getPosition());
    }

    /**
     * Parse an indirect object definition (obj ... endobj).
     *
     * @return array{object: PdfObject, objectNumber: int, generationNumber: int}
     */
    public function parseIndirectObject(): array
    {
        $this->lexer->skipWhitespace();

        // Read object number
        $objectNumber = $this->lexer->readNumber();
        if (!is_int($objectNumber)) {
            throw ParseException::invalidObject('Object number must be an integer', $this->lexer->getPosition());
        }

        $this->lexer->skipWhitespace();

        // Read generation number
        $generationNumber = $this->lexer->readNumber();
        if (!is_int($generationNumber)) {
            throw ParseException::invalidObject('Generation number must be an integer', $this->lexer->getPosition());
        }

        $this->lexer->skipWhitespace();

        // Expect 'obj' keyword
        if (!$this->lexer->skipKeyword('obj')) {
            throw ParseException::unexpectedToken('obj', 'unknown', $this->lexer->getPosition());
        }

        $this->lexer->skipWhitespace();

        // Parse the object
        $object = $this->parse();

        $this->lexer->skipWhitespace();

        // Check for stream
        if ($object instanceof PdfDictionary && $this->lexer->matchKeyword('stream')) {
            $object = $this->parseStream($object);
        }

        $this->lexer->skipWhitespace();

        // Expect 'endobj' keyword
        if (!$this->lexer->skipKeyword('endobj')) {
            throw ParseException::unexpectedToken('endobj', 'unknown', $this->lexer->getPosition());
        }

        $object->setIndirect($objectNumber, $generationNumber);

        return [
            'object' => $object,
            'objectNumber' => $objectNumber,
            'generationNumber' => $generationNumber,
        ];
    }

    /**
     * Parse a stream following a dictionary.
     */
    public function parseStream(PdfDictionary $dictionary): PdfStream
    {
        // Skip 'stream' keyword
        $this->lexer->skipKeyword('stream');

        // Skip single newline after 'stream' keyword
        $char = $this->lexer->peek();
        if ($char === "\r") {
            $this->lexer->read();
            if ($this->lexer->peek() === "\n") {
                $this->lexer->read();
            }
        } elseif ($char === "\n") {
            $this->lexer->read();
        }

        // Get stream length
        $lengthObj = $dictionary->get(PdfName::LENGTH);
        if ($lengthObj === null) {
            throw ParseException::invalidObject('Stream dictionary must have /Length entry', $this->lexer->getPosition());
        }

        $length = 0;
        if ($lengthObj instanceof PdfNumber) {
            $length = $lengthObj->toInt();
        } elseif ($lengthObj instanceof PdfReference) {
            // Length is an indirect reference - we'll handle this later when resolving references
            // For now, search for endstream
            $startPos = $this->lexer->getPosition();
            $endPos = $this->lexer->searchForward('endstream');
            if ($endPos === -1) {
                throw ParseException::invalidObject('Could not find endstream', $startPos);
            }
            $length = $endPos - $startPos;
            // Trim trailing whitespace
            while ($length > 0) {
                $lastChar = $this->lexer->getSubstring($startPos + $length - 1, 1);
                if ($lastChar === "\n" || $lastChar === "\r") {
                    $length--;
                } else {
                    break;
                }
            }
        }

        // Read stream data
        $data = $this->lexer->readBytes($length);

        // Skip newline before endstream
        $this->lexer->skipWhitespace();

        // Skip 'endstream' keyword
        if (!$this->lexer->skipKeyword('endstream')) {
            throw ParseException::unexpectedToken('endstream', 'unknown', $this->lexer->getPosition());
        }

        return PdfStream::fromEncoded($dictionary, $data);
    }
}
