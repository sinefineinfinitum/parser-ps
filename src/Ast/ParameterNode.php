<?php declare(strict_types=1);

namespace Ponymator\Parser\Ast;

class ParameterNode
{
    public const BY_REF_MARKER = '&';
    public const VARIABLE_PREFIX = '$';

    public ?string $type = null;
    public bool $byRef = false;
    public ?string $value = null;

    public function __construct(
        public string $name,
    ) {
    }

    /**
     * Splits a parameter directive line into its by-reference flag and
     * the declaration body (everything after the "$" sigil).
     *
     * Returns null if the line lacks the required "$" prefix
     * (with or without a leading "&").
     *
     * @return array{byRef: bool, body: string}|null
     */
    public static function parsePrefix(string $line): ?array
    {
        $byRef = false;
        if (str_starts_with($line, self::BY_REF_MARKER)) {
            $byRef = true;
            $line = substr($line, 1);
        }

        if (!str_starts_with($line, self::VARIABLE_PREFIX)) {
            return null;
        }

        return ['byRef' => $byRef, 'body' => substr($line, 1)];
    }
}
