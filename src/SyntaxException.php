<?php declare(strict_types=1);

namespace Ponymator\Parser;

class SyntaxException extends \RuntimeException
{
    public static function atLine(string $message, int $lineNumber, string $line): self
    {
        return new self(
            sprintf(
                '%s on line %d: %s',
                $message,
                $lineNumber,
                $line
            )
        );
    }
}
