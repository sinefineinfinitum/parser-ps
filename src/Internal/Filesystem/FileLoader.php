<?php declare(strict_types=1);

namespace Ponymator\Parser\Internal\Filesystem;

/**
 * @internal
 */
final class FileLoader
{
    /**
     * Reads a single file from disk and returns its raw contents.
     *
     * @throws FilesystemException
     */
    public function load(string $path): string
    {
        if (!is_file($path)) {
            throw new FilesystemException(sprintf('File not found: %s', $path));
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new FilesystemException(sprintf('Failed to read file: %s', $path));
        }

        return $content;
    }

    /**
     * Reads multiple files in order, short-circuiting on the first error.
     *
     * @param  iterable<string> $paths
     * @return string[]
     * @throws FilesystemException
     */
    public function loadAll(iterable $paths): array
    {
        $contents = [];
        foreach ($paths as $path) {
            $contents[] = $this->load($path);
        }
        return $contents;
    }
}
