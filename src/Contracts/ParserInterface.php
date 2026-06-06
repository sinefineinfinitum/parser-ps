<?php declare(strict_types=1);

namespace Ponymator\Parser\Contracts;

use Ponymator\Parser\Ast\Document;

interface ParserInterface
{
    /**
     * Parses an in-memory PSV1 string into a Document.
     */
    public function parse(string $content): Document;

    /**
     * Reads and parses a single PSV1 file.
     *
     * The returned Document has {@see Document::$sourcePath} and
     * {@see Document::$sourceHash} populated.
     *
     * @throws \RuntimeException If the file cannot be read.
     */
    public function parseFile(string $path): Document;

    /**
     * Reads and parses multiple PSV1 files.
     *
     * @param  iterable<string> $paths
     * @return Document[]
     * @throws \RuntimeException If any file cannot be read.
     */
    public function parseFiles(iterable $paths): array;
}
