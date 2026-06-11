<?php declare(strict_types=1);

namespace Ponymator\Parser\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use Ponymator\Parser\Internal\NameAndAttributes;
use Ponymator\Parser\Internal\TokenParser;
use Ponymator\Parser\SyntaxException;

final class NameAndAttributesTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string[]}>
     */
    public static function nameAndKeywordCases(): array
    {
        return [
            'plain name' => ['foo', 'foo', []],
            'final keyword before name' => ['final foo', 'foo', ['final']],
            'static keyword before name' => ['static foo', 'foo', ['static']],
            'abstract keyword before name' => ['abstract foo', 'foo', ['abstract']],
            'readonly keyword before name' => ['readonly foo', 'foo', ['readonly']],
            'multiple keywords before name' => ['final static foo', 'foo', ['final', 'static']],
            'all four keywords before name' => ['final abstract static readonly foo', 'foo', ['final', 'abstract', 'static', 'readonly']],
            'single keyword as name' => ['final', 'final', []],
            'all keywords last is name' => ['static final', 'final', ['static']],
            'all four keywords last is name' => ['final abstract static readonly', 'readonly', ['final', 'abstract', 'static']],
        ];
    }

    /**
     * @dataProvider nameAndKeywordCases
     */
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

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        TokenParser::splitNameAndAttributes('');
    }

    public function testWhitespaceStringThrowsException(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Name cannot be empty');
        TokenParser::splitNameAndAttributes('   ');
    }

    public function testUnknownAttributeThrowsException(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown attribute "volatile"');
        TokenParser::splitNameAndAttributes('volatile foo');
    }

    public function testMultipleSpacesWithFirstWordAsUnknownAttributeThrows(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown attribute "something"');
        TokenParser::splitNameAndAttributes('something    foo');
    }

    public function testKeywordInMiddleWithUnknownBeforeThrows(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown attribute "foo"');
        TokenParser::splitNameAndAttributes('foo final bar');
    }
}
