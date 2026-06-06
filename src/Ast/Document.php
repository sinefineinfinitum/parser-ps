<?php declare(strict_types=1);

namespace Ponymator\Parser\Ast;

class Document
{
    /**
     * @var EntityNode[] 
     */
    public array $entities = [];

    /**
     * Version of the parser that produced this Document.
     * Populated by {@see \Ponymator\Parser\Parser} from its VERSION constant.
     */
    public string $parserVersion = '1.0';

    /**
     * Absolute or user-supplied path to the source file, if the Document
     * was produced by {@see \Ponymator\Parser\Parser::parseFile()}.
     * Null when parsed from an in-memory string.
     */
    public ?string $sourcePath = null;

    /**
     * SHA-256 hash of the raw source content (hex-encoded).
     * Populated by {@see \Ponymator\Parser\Parser::parseFile()} for cache
     * invalidation. Null when parsed from an in-memory string.
     */
    public ?string $sourceHash = null;
}
