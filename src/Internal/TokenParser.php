<?php declare(strict_types=1);

namespace Ponymator\Parser\Internal;

use Ponymator\Parser\SyntaxException;

/**
 * @internal
 */
final class TokenParser
{
    public const KEYWORDS = [
        'final',
        'abstract',
        'static',
        'readonly',
    ];

    /**
     * Parses a declaration string in the format "name:type=value".
     * Handles generics (angle brackets) for type and value parsing.
     *
     * @throws SyntaxException If the angle brackets in the declaration are unbalanced.
     */
    public static function parseTypedDeclaration(string $declarationString): TypedDeclaration
    {
        $colonPos = false;
        $equalPos = false;
        $depth = 0;
        $len = strlen($declarationString);

        for ($i = 0; $i < $len; $i++) {
            $char = $declarationString[$i];
            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth--;
            } elseif ($char === ':' && $depth === 0) {
                if ($colonPos === false) {
                    $colonPos = $i;
                }
            } elseif ($char === '=' && $depth === 0) {
                if ($equalPos === false) {
                    $equalPos = $i;
                }
            }
        }

        if ($depth !== 0) {
            throw SyntaxException::atLine(
                sprintf('Unbalanced angle brackets in declaration "%s"', $declarationString),
                0,
                $declarationString,
            );
        }

        $dataType = null;
        $value = null;

        if ($colonPos !== false && $equalPos !== false && $colonPos < $equalPos) {
            $nameAndKeywords = substr($declarationString, 0, $colonPos);
            $dataType = substr($declarationString, $colonPos + 1, $equalPos - $colonPos - 1);
            $value = substr($declarationString, $equalPos + 1);
        } elseif ($colonPos !== false && ($equalPos === false || $colonPos > $equalPos)) {
            $nameAndKeywords = substr($declarationString, 0, $colonPos);
            $dataType = substr($declarationString, $colonPos + 1);
        } elseif ($equalPos !== false) {
            $nameAndKeywords = substr($declarationString, 0, $equalPos);
            $value = substr($declarationString, $equalPos + 1);
        } else {
            $nameAndKeywords = $declarationString;
        }

        return new TypedDeclaration(
            trim($nameAndKeywords),
            $dataType !== null ? trim($dataType) : null,
            $value !== null ? trim($value) : null,
        );
    }

    /**
     * Splits a string into a name and an array of attributes (keywords).
     */
    public static function splitNameAndAttributes(string $inputString): NameAndAttributes
    {
        $words = preg_split('/\s+/', $inputString);
        $name = '';
        $attributes = [];

        if ($words === false) {
            return new NameAndAttributes($name, $attributes);
        }

        foreach ($words as $word) {
            if (in_array($word, self::KEYWORDS, true)) {
                $attributes[] = $word;
            } else {
                if ($name !== '') {
                    $name .= ' ' . $word;
                } else {
                    $name = $word;
                }
            }
        }

        return new NameAndAttributes($name, $attributes);
    }
}
