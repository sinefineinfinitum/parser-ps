# Agent Guidelines

Working on the `ponymator/parser` library — a PHP 8.0+ parser for Ponymator
Syntax v1.0 (PSV1). Read [spec-ps-v1.md](spec-ps-v1.md) before changing parser
semantics.

## Commands

| Task | Command (run from repo root) |
| --- | --- |
| Install | `composer install` |
| Tests | `vendor/bin/phpunit` |
| Static analysis | `vendor/bin/phpstan analyse` (level 9, `src/` only) |
| Code style | `vendor/bin/phpcs` |

All three must pass before considering work done.

## Layout

```
src/
  Parser.php                    # parse(), parseFile(), parseFiles()
  SyntaxException.php           # atLine($message, $lineNumber, $line)
  Ast/                          # public AST
    Document.php
    EntityNode.php              # TYPES, RELATION_MARKERS, detectType, addRelation
    MemberNode.php              # SYMBOLS, VISIBILITY_MAP, INSTANTIATION_MARKER,
                                #   RETURN_TYPE_MARKER, parseChildDirective
    ParameterNode.php           # BY_REF_MARKER, VARIABLE_PREFIX, parsePrefix
  Internal/                     # @internal, do not reference from outside src/
    Lexer.php                   # tokenize(): string -> Line[]; INDENT_WIDTH=4
    Line.php                    # (number, raw, trimmed, indentation)
    ParserState.php             # currentEntity, currentMethod
    TokenParser.php             # parseTypedDeclaration, splitNameAndAttributes
    NameAndAttributes.php
    TypedDeclaration.php
    Filesystem/
      FileLoader.php            # load(), loadAll(); throws FilesystemException
      FilesystemException.php
  Contracts/
    ParserInterface.php

tests/
  Unit/                         # PHPUnit 10
    Internal/                   # LexerTest, FileLoaderTest, TokenParserTest, …
    ParserTest.php
  Integration/
    ApiTraversalTest.php
  docs/                         # *.psv1 fixture files
```

## Conventions

- **PHP 8.0+ constraints** — no `enum`, no `readonly` props, no first-class
  callables, no `never` return type.
- **All classes `final`** in `Internal/` (where mutable by convention).
- **`declare(strict_types=1)`** on every PHP file.
- **Grammar lives on nodes**: marker constants and `parse*` helpers are static
  methods on the AST node that owns them (`EntityNode::TYPES`,
  `MemberNode::parseChildDirective`, `ParameterNode::parsePrefix`).
- **Lexer owns line-level concerns**: rtrim, trim, indentation validation,
  blank-line skipping. Parser must not construct `Line` instances directly.
- **FileLoader owns I/O**: Parser does not call `is_file` / `file_get_contents`.
- **Errors**: throw `SyntaxException::atLine($msg, $number, $rawLine)` — never
  inline `sprintf` a new `SyntaxException` at the call site.
- **Indentation**: `Lexer::INDENT_WIDTH` (4 spaces) is the only allowed indent.
  Tabs anywhere on a line throw.
- **No code comments** unless the user explicitly asks for them.
- **No new dependencies** beyond the three dev tools already in `composer.json`.

## Adding a feature

1. If the feature is a new PSV1 sigil/marker, the constant + prefix/recognition
   helper go on the relevant AST node class. Add a unit test in
   `tests/Unit/Internal/`.
2. If the feature changes parser orchestration, edit `Parser.php` and add a test
   in `tests/Unit/ParserTest.php` or `tests/Integration/`.
3. After every change: run `phpunit`, `phpstan`, `phpcs`. All must pass.

## Adding tests

- Use `#[DataProvider]` for parametrized cases.
- `expectException(SyntaxException::class)` for parse errors,
  `expectException(FilesystemException::class)` for file I/O errors.
- For integration coverage, prefer real PSV1 strings over mocks.
- New fixture files belong in `tests/docs/`.

## Don't

- Do not commit. Wait for explicit `git commit` instructions.
- Do not introduce PHP 8.1+ syntax (readonly, enums, first-class callables).
- Do not make `Ast/` classes depend on `Internal/` (one-way dependency).
- Do not bypass `Lexer` to construct `Line` outside `Lexer::tokenize()`.
- Do not call `is_file` / `file_get_contents` outside `FileLoader`.
- Do not add PSR-3 logger calls, deprecation stubs, or "future-proof" hooks
  unless explicitly requested.
