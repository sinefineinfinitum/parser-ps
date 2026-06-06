# Ponymator\Parser

PHP library for parsing [Ponymator Syntax v1.0](spec-ps-v1.md) (PSV1) — a minimal,
deterministic format for describing code structure as a graph.

## Install

```bash
composer require ponyrator/parser
```

Requires PHP `^8.0`.

## Usage

```php
use Ponymator\Parser\Parser;

$parser = new Parser();
$doc = $parser->parse(<<<'PSV1'
@class final App\Service\SearchService
>App\Core\BaseService

$vectorStore:App\Storage\VectorStore

.+search final
    $query:App\Query\SearchQuery
    :App\Search\SearchResult
PSV1);

foreach ($doc->entities as $entity) {
    // $entity->type, ->name, ->extends, ->implements, ->members
    foreach ($entity->members as $member) {
        // $member->type: 'property' | 'method' | 'constant' | 'function' | 'enum_case' | 'global_variable'
        // $member->name, ->visibility, ->dataType, ->returnType, ->value
    }
}
```

## API

| Method | Description |
| --- | --- |
| `Parser::parse(string $content): Document` | Parse a string. |
| `Parser::parseFile(string $path): Document` | Read file, parse, populate `sourcePath` and `sourceHash`. |
| `Parser::parseFiles(iterable $paths): Document[]` | Batch parse multiple files. |
| `Parser::VERSION` | Parser version stamped onto every `Document`. |

Parse errors throw `Ponymator\Parser\SyntaxException`. File I/O errors throw
`Ponymator\Parser\Internal\Filesystem\FilesystemException`.

## PSV1 Syntax

See [spec-ps-v1.md](spec-ps-v1.md) for the full specification (entity types, member
symbols, indentation rules, generic types, keywords).

## Development

```bash
composer install
vendor/bin/phpunit          # run tests
vendor/bin/phpstan analyse  # static analysis (level 9)
vendor/bin/phpcs            # code style
```

## Architecture

- `src/Parser.php` — orchestrator (`parse`, `parseFile`, `parseFiles`)
- `src/Ast/` — public AST nodes (`Document`, `EntityNode`, `MemberNode`, `ParameterNode`)
- `src/Internal/` — `@internal` helpers (`Lexer`, `Line`, `TokenParser`, `ParserState`)
- `src/Internal/Filesystem/` — `FileLoader`, `FilesystemException`
- `src/Contracts/ParserInterface.php` — public contract
- `src/SyntaxException.php` — parse errors

PSV1 grammar knowledge (sigil constants, prefix parsing, context rules) lives on the
AST node classes themselves. The Parser orchestrates; the Lexer validates indentation;
the FileLoader handles I/O.

## License

MIT (TBD).
