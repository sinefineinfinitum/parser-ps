<?php declare(strict_types=1);

namespace Ponymator\Parser\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Ponymator\Parser\Parser;

final class CallGraphPerformanceTest extends TestCase
{
    public function testParseCallGraphCompletesInUnder500ms(): void
    {
        $parser = new Parser();
        $files = glob(__DIR__ . '/../docs/*.psv1');
        $this->assertNotEmpty($files);

        $start = microtime(true);
        foreach ($files as $file) {
            $parser->parseFile($file);
        }
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500.0, $elapsed, sprintf('Parsing took %.2fms, expected < 500ms', $elapsed));
    }
}
