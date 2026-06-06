<?php declare(strict_types=1);

namespace Ponymator\Parser\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ponymator\Parser\Internal\NameAndAttributes;
use Ponymator\Parser\Internal\TokenParser;

final class NameAndAttributesTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string[]}>
     */
    public static function nameAndKeywordCases(): array
    {
        return [
            'plain name' => ['foo', 'foo', []],
            'final keyword' => ['final foo', 'foo', ['final']],
            'static keyword' => ['static foo', 'foo', ['static']],
            'abstract keyword' => ['abstract foo', 'foo', ['abstract']],
            'readonly keyword' => ['readonly foo', 'foo', ['readonly']],
            'multiple keywords' => ['final static foo', 'foo', ['final', 'static']],
            'all four keywords' => ['final abstract static readonly foo', 'foo', ['final', 'abstract', 'static', 'readonly']],
            'non-keyword suffix treated as name' => ['something foo', 'something foo', []],
            'keyword anywhere is extracted' => ['foo final bar', 'foo bar', ['final']],
        ];
    }

    #[DataProvider('nameAndKeywordCases')]
    public function testSplitsNameAndAttributes(string $input, string $expectedName, array $expectedAttributes): void
    {
        $result = TokenParser::splitNameAndAttributes($input);

        $this->assertSame($expectedName, $result->name);
        $this->assertSame($expectedAttributes, $result->attributes);
    }

    public function testReturnsNameAndAttributesInstance(): void
    {
        $this->assertInstanceOf(
            NameAndAttributes::class,
            TokenParser::splitNameAndAttributes('foo'),
        );
    }

    public function testEmptyStringYieldsEmptyNameAndNoAttributes(): void
    {
        $result = TokenParser::splitNameAndAttributes('');

        $this->assertSame('', $result->name);
        $this->assertSame([], $result->attributes);
    }

    public function testCollapsesMultipleSpaces(): void
    {
        $result = TokenParser::splitNameAndAttributes('foo    bar');

        $this->assertSame('foo bar', $result->name);
        $this->assertSame([], $result->attributes);
    }

    public function testIgnoresUnknownKeywords(): void
    {
        $result = TokenParser::splitNameAndAttributes('volatile foo');

        $this->assertSame('volatile foo', $result->name);
        $this->assertSame([], $result->attributes);
    }
}
