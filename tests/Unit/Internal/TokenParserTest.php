<?php declare(strict_types=1);

namespace Ponymator\Parser\Tests\Unit\Internal;

use PHPUnit\Framework\TestCase;
use Ponymator\Parser\Internal\TokenParser;

final class TokenParserTest extends TestCase
{
    public function testKeywordsConstantContainsAllExpectedEntries(): void
    {
        $this->assertSame(
            ['final', 'abstract', 'static', 'readonly'],
            TokenParser::KEYWORDS,
        );
    }

    public function testClassIsFinalAndUsesInternalNamespace(): void
    {
        $reflection = new \ReflectionClass(TokenParser::class);

        $this->assertTrue($reflection->isFinal(), 'TokenParser must be final');
        $this->assertSame('Ponymator\\Parser\\Internal', $reflection->getNamespaceName());
    }

    public function testPublicMethodsAreStatic(): void
    {
        $reflection = new \ReflectionClass(TokenParser::class);

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->assertTrue(
                $method->isStatic(),
                sprintf('TokenParser::%s must be static', $method->getName()),
            );
        }
    }
}
