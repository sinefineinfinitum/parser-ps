<?php

namespace Ponymator\Parser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Ponymator\Parser\Parser;
use Ponymator\Parser\SyntaxException;

class ParserTest extends TestCase {
    public function testParseEmptyContentReturnsEmptyDocument() {
        $parser = new Parser();
        $doc = $parser->parse("");
        $this->assertEmpty($doc->entities);
    }

    public function testParseClassDefinitionReturnsDocumentWithEntity() {
        $parser = new Parser();
        $content = "@class TestClass";
        $doc = $parser->parse($content);
        $this->assertCount(1, $doc->entities);
        $this->assertEquals("TestClass", $doc->entities[0]->name);
        $this->assertEquals("class", $doc->entities[0]->type);
    }

    /**
     * @dataProvider entityDefinitionProvider
     */
    public function testParseEntityDefinitionReturnsDocumentWithEntity(string $content, string $expectedType, string $expectedName) {
        $parser = new Parser();

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEquals($expectedName, $doc->entities[0]->name);
        $this->assertEquals($expectedType, $doc->entities[0]->type);
    }

    public static function entityDefinitionProvider(): array {
        return [
            'interface' => [
                '@interface App\Contracts\SearchInterface',
                'interface',
                'App\Contracts\SearchInterface',
            ],
            'trait' => [
                '@trait App\Traits\LoggableTrait',
                'trait',
                'App\Traits\LoggableTrait',
            ],
            'file' => [
                '@file src/functions.php',
                'file',
                'src/functions.php',
            ],
            'enum' => [
                '@enum App\Status',
                'enum',
                'App\Status',
            ],
        ];
    }

    public function testParseClassExtendsParentClass() {
        $parser = new Parser();
        $content = implode("\n", [
            '@class App\Service\SearchService',
            '>App\Core\BaseService',
        ]);

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEquals(['App\Core\BaseService'], $doc->entities[0]->extends);
        $this->assertEmpty($doc->entities[0]->implements);
    }

    public function testParseClassImplementsInterfaces() {
        $parser = new Parser();
        $content = implode("\n", [
            '@class App\Service\SearchService',
            '<App\Contracts\SearchInterface',
            '<App\Contracts\LoggerAwareInterface',
        ]);

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEmpty($doc->entities[0]->extends);
        $this->assertEquals([
            'App\Contracts\SearchInterface',
            'App\Contracts\LoggerAwareInterface',
        ], $doc->entities[0]->implements);
    }

    public function testParseClassExtendsAndImplements() {
        $parser = new Parser();
        $content = implode("\n", [
            '@class App\Service\SearchService',
            '>App\Core\BaseService',
            '<App\Contracts\SearchInterface',
            '<App\Contracts\LoggerAwareInterface',
        ]);

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $this->assertEquals(['App\Core\BaseService'], $doc->entities[0]->extends);
        $this->assertEquals([
            'App\Contracts\SearchInterface',
            'App\Contracts\LoggerAwareInterface',
        ], $doc->entities[0]->implements);
    }

    public function testParseMultipleRootDocumentationFiles() {
        $parser = new Parser();
        $files = glob(__DIR__ . '/../docs/*.psv1');

        $this->assertNotEmpty($files);

        $parsedDocuments = [];
        foreach ($files as $file) {
            $doc = $parser->parse(file_get_contents($file));

            $this->assertCount(1, $doc->entities, basename($file));
            $this->assertEquals('class', $doc->entities[0]->type, basename($file));

            $parsedDocuments[] = $doc;
        }

        $this->assertGreaterThanOrEqual(2, count($parsedDocuments));
    }

    public function testParseMultipleDocumentationFilesFromNestedFolder() {
        $parser = new Parser();
        $files = glob(__DIR__ . '/../docs/Analyzer/*.psv1');

        $this->assertNotEmpty($files);

        $entityNames = [];
        foreach ($files as $file) {
            $doc = $parser->parse(file_get_contents($file));

            $this->assertCount(1, $doc->entities, basename($file));
            $this->assertEquals('class', $doc->entities[0]->type, basename($file));

            $entityNames[] = $doc->entities[0]->name;
        }

        $this->assertContains('SineFine\Ponymator\Analyzer\Parser', $entityNames);
        $this->assertGreaterThanOrEqual(2, count($entityNames));
    }

    public function testParseComplexClassWithAllFeatures() {
        $parser = new Parser();
        $content = implode("\n", [
            '@class final App\Service\SearchService',
            '>App\Core\BaseService',
            '<App\Contracts\SearchInterface',
            '%App\LoggableTrait',
            '',
            '$-readonly vectorStore:App\Storage\VectorStore',
            '$-mixedResult:int|string|null',
            '',
            '!+DEFAULT_LIMIT:int=25',
            '',
            '.+search final',
            '    $query:App\Query\SearchQuery',
            '    :App\Search\SearchResult|null',
            '    ^App\Search\SearchResult',
            '',
            '.+merge static',
            '    &$source:array',
            '    $limit:int=10',
            '    :array',
        ]);

        $doc = $parser->parse($content);

        $this->assertCount(1, $doc->entities);
        $entity = $doc->entities[0];
        $this->assertEquals('class', $entity->type);
        $this->assertEquals('App\Service\SearchService', $entity->name);
        $this->assertEquals(['final'], $entity->attributes);
        $this->assertEquals(['App\Core\BaseService'], $entity->extends);
        $this->assertEquals(['App\Contracts\SearchInterface'], $entity->implements);
        $this->assertEquals(['App\LoggableTrait'], $entity->traits);

        $this->assertCount(5, $entity->members);

        // 1. $-readonly vectorStore:App\Storage\VectorStore
        $prop1 = $entity->members[0];
        $this->assertEquals('vectorStore', $prop1->name);
        $this->assertEquals('property', $prop1->type);
        $this->assertEquals('private', $prop1->visibility);
        $this->assertEquals(['readonly'], $prop1->attributes);
        $this->assertEquals('App\Storage\VectorStore', $prop1->dataType);
        $this->assertNull($prop1->value);

        // 2. $-mixedResult:int|string|null
        $prop2 = $entity->members[1];
        $this->assertEquals('mixedResult', $prop2->name);
        $this->assertEquals('property', $prop2->type);
        $this->assertEquals('private', $prop2->visibility);
        $this->assertEquals('int|string|null', $prop2->dataType);

        // 3. !+DEFAULT_LIMIT:int=25
        $const1 = $entity->members[2];
        $this->assertEquals('DEFAULT_LIMIT', $const1->name);
        $this->assertEquals('constant', $const1->type);
        $this->assertEquals('public', $const1->visibility);
        $this->assertEquals('int', $const1->dataType);
        $this->assertEquals('25', $const1->value);

        // 4. .+search final
        $method1 = $entity->members[3];
        $this->assertEquals('search', $method1->name);
        $this->assertEquals('method', $method1->type);
        $this->assertEquals('public', $method1->visibility);
        $this->assertEquals(['final'], $method1->attributes);
        $this->assertEquals('App\Search\SearchResult|null', $method1->returnType);
        $this->assertEquals(['App\Search\SearchResult'], $method1->creates);
        $this->assertCount(1, $method1->parameters);

        $param1 = $method1->parameters[0];
        $this->assertEquals('query', $param1->name);
        $this->assertEquals('App\Query\SearchQuery', $param1->type);
        $this->assertFalse($param1->byRef);
        $this->assertNull($param1->value);

        // 5. .+merge static
        $method2 = $entity->members[4];
        $this->assertEquals('merge', $method2->name);
        $this->assertEquals('method', $method2->type);
        $this->assertEquals('public', $method2->visibility);
        $this->assertEquals(['static'], $method2->attributes);
        $this->assertEquals('array', $method2->returnType);
        $this->assertCount(2, $method2->parameters);

        $param2_1 = $method2->parameters[0];
        $this->assertEquals('source', $param2_1->name);
        $this->assertEquals('array', $param2_1->type);
        $this->assertTrue($param2_1->byRef);

        $param2_2 = $method2->parameters[1];
        $this->assertEquals('limit', $param2_2->name);
        $this->assertEquals('int', $param2_2->type);
        $this->assertEquals('10', $param2_2->value);
    }

    public function testParseEnumWithCases() {
        $parser = new Parser();
        $content = implode("\n", [
            '@enum App\Status',
            '~Active=1',
            '~Inactive:int=2',
            '~Pending',
        ]);

        $doc = $parser->parse($content);
        $this->assertCount(1, $doc->entities);
        $entity = $doc->entities[0];
        $this->assertEquals('enum', $entity->type);
        $this->assertCount(3, $entity->members);

        $case1 = $entity->members[0];
        $this->assertEquals('Active', $case1->name);
        $this->assertEquals('enum_case', $case1->type);
        $this->assertEquals('1', $case1->value);
        $this->assertNull($case1->dataType);

        $case2 = $entity->members[1];
        $this->assertEquals('Inactive', $case2->name);
        $this->assertEquals('enum_case', $case2->type);
        $this->assertEquals('2', $case2->value);
        $this->assertEquals('int', $case2->dataType);

        $case3 = $entity->members[2];
        $this->assertEquals('Pending', $case3->name);
        $this->assertEquals('enum_case', $case3->type);
        $this->assertNull($case3->value);
    }

    public function testParseProceduralFile() {
        $parser = new Parser();
        $content = implode("\n", [
            '@file src/functions.php',
            '',
            '.getUser',
            '    $id:int',
            '    :App\Entity\User|null',
            '',
            '!MAX_RETRIES:int=3',
            '',
            '$debugMode:bool=false',
        ]);

        $doc = $parser->parse($content);
        $this->assertCount(1, $doc->entities);
        $entity = $doc->entities[0];
        $this->assertEquals('file', $entity->type);
        $this->assertCount(3, $entity->members);

        $func = $entity->members[0];
        $this->assertEquals('getUser', $func->name);
        $this->assertEquals('function', $func->type);
        $this->assertNull($func->visibility);
        $this->assertEquals('App\Entity\User|null', $func->returnType);
        $this->assertCount(1, $func->parameters);
        $this->assertEquals('id', $func->parameters[0]->name);
        $this->assertEquals('int', $func->parameters[0]->type);

        $const = $entity->members[1];
        $this->assertEquals('MAX_RETRIES', $const->name);
        $this->assertEquals('constant', $const->type);
        $this->assertNull($const->visibility);
        $this->assertEquals('int', $const->dataType);
        $this->assertEquals('3', $const->value);

        $globalVar = $entity->members[2];
        $this->assertEquals('debugMode', $globalVar->name);
        $this->assertEquals('global_variable', $globalVar->type);
        $this->assertNull($globalVar->visibility);
        $this->assertEquals('bool', $globalVar->dataType);
        $this->assertEquals('false', $globalVar->value);
    }

    public function testSyntaxExceptionOnInvalidIndentation() {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@class Test\n  \$prop:int"); // 2 spaces is invalid
    }

    public function testSyntaxExceptionOnTabsIndentation() {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@class Test\n\t\$prop:int");
    }

    public function testSyntaxExceptionOnUnknownEntity() {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@unknown App\\SomeClass");
    }

    public function testSyntaxExceptionOnVisibilityInFileContext() {
        $this->expectException(SyntaxException::class);
        $parser = new Parser();
        $parser->parse("@file src/functions.php\n!+MAX_RETRIES:int=3");
    }

    public function testParseSetsParserVersionFromConstant() {
        $parser = new Parser();
        $doc = $parser->parse("@class Foo");

        $this->assertEquals(Parser::VERSION, $doc->parserVersion);
    }

    public function testParseDoesNotSetSourcePathOrHash() {
        $parser = new Parser();
        $doc = $parser->parse("@class Foo");

        $this->assertNull($doc->sourcePath);
        $this->assertNull($doc->sourceHash);
    }

    public function testParseFileReturnsDocumentWithSourceMetadata() {
        $parser = new Parser();
        $path = __DIR__ . '/../docs/Ponymator.psv1';

        $doc = $parser->parseFile($path);

        $this->assertEquals($path, $doc->sourcePath);
        $this->assertEquals(hash('sha256', file_get_contents($path)), $doc->sourceHash);
        $this->assertCount(1, $doc->entities);
    }

    public function testParseFileThrowsOnMissingFile() {
        $this->expectException(\RuntimeException::class);
        $parser = new Parser();
        $parser->parseFile(__DIR__ . '/does-not-exist.psv1');
    }

    public function testParseFilesReturnsArrayOfDocuments() {
        $parser = new Parser();
        $paths = [
            __DIR__ . '/../docs/Ponymator.psv1',
            __DIR__ . '/../docs/Config.psv1',
        ];

        $docs = $parser->parseFiles($paths);

        $this->assertCount(2, $docs);
        $this->assertEquals($paths[0], $docs[0]->sourcePath);
        $this->assertEquals($paths[1], $docs[1]->sourcePath);
    }
}
