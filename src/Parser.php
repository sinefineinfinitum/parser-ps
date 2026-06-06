<?php declare(strict_types=1);

namespace Ponymator\Parser;

use Ponymator\Parser\Ast\Document;
use Ponymator\Parser\Ast\EntityNode;
use Ponymator\Parser\Ast\MemberNode;
use Ponymator\Parser\Ast\ParameterNode;
use Ponymator\Parser\Contracts\ParserInterface;
use Ponymator\Parser\Internal\Filesystem\FileLoader;
use Ponymator\Parser\Internal\Lexer;
use Ponymator\Parser\Internal\Line;
use Ponymator\Parser\Internal\ParserState;
use Ponymator\Parser\Internal\TokenParser;

class Parser implements ParserInterface
{
    public const VERSION = '1.0';

    private Lexer $lexer;
    private FileLoader $loader;

    public function __construct(?Lexer $lexer = null, ?FileLoader $loader = null)
    {
        $this->lexer = $lexer ?? new Lexer();
        $this->loader = $loader ?? new FileLoader();
    }

    public function parse(string $content): Document
    {
        $document = new Document();
        $document->parserVersion = self::VERSION;

        $state = new ParserState();
        foreach ($this->lexer->tokenize($content) as $line) {
            if ($line->indentation === 0) {
                $this->parseTopLevelLine($line, $document, $state);
            } elseif ($line->indentation === Lexer::INDENT_WIDTH) {
                $this->parseIndentedLine($line, $state);
            }
        }

        return $document;
    }

    /**
     * @throws \RuntimeException If the file cannot be read.
     * @throws SyntaxException If the file contents are not valid PSV1.
     */
    public function parseFile(string $path): Document
    {
        $content = $this->loader->load($path);
        $document = $this->parse($content);
        $document->sourcePath = $path;
        $document->sourceHash = hash('sha256', $content);
        return $document;
    }

    /**
     * @param  iterable<string> $paths
     * @return Document[]
     * @throws \RuntimeException If any file cannot be read.
     * @throws SyntaxException If any file's contents are not valid PSV1.
     */
    public function parseFiles(iterable $paths): array
    {
        $documents = [];
        foreach ($paths as $path) {
            $documents[] = $this->parseFile($path);
        }
        return $documents;
    }

    /**
     * Dispatches a non-indented line: entity declaration ("@…"), relation
     * marker (">"/"<"/"%"), or member declaration. Mutates the document and
     * the parser state in place.
     */
    private function parseTopLevelLine(Line $line, Document $document, ParserState $state): void
    {
        $trimmed = $line->trimmed;

        if (str_starts_with($trimmed, EntityNode::ENTITY_START)) {
            $entity = $this->parseEntityDirective($line);
            $document->entities[] = $entity;
            $state->openEntity($entity);
            return;
        }

        if (EntityNode::isRelationMarker($trimmed[0])) {
            $this->parseEntityRelationDirective($line, $state->entity());
            return;
        }

        if ($state->entity() === null) {
            throw $this->syntaxError('Member declaration found without active entity', $line);
        }

        $entity = $state->entity();
        $member = $this->parseMemberDirective($line, $entity);
        $entity->members[] = $member;

        if ($member->type === 'method' || $member->type === 'function') {
            $state->openMethod($member);
        } else {
            $state->closeMethod();
        }
    }

    /**
     * Dispatches an indented line inside a method/function block.
     *
     * @throws SyntaxException If the line is indented but no method/function block is active.
     */
    private function parseIndentedLine(Line $line, ParserState $state): void
    {
        if ($state->method() === null) {
            throw $this->syntaxError('Indented line found without active method/function block', $line);
        }

        $this->parseMethodChildDirective($line, $state->method());
    }

    /**
     * Parses an entity directive (e.g., "@class MyClass").
     *
     * @throws SyntaxException If the entity directive is invalid.
     */
    private function parseEntityDirective(Line $line): EntityNode
    {
        $entityType = EntityNode::detectType($line->trimmed);
        if ($entityType === null) {
            throw $this->syntaxError('Unknown or invalid entity type in directive', $line);
        }

        $rest = substr($line->trimmed, strlen('@' . $entityType));
        if ($rest !== '' && !str_starts_with($rest, ' ')) {
            throw $this->syntaxError('Entity directive must be followed by space', $line);
        }

        $rest = trim($rest);
        if ($rest === '') {
            throw $this->syntaxError('Entity name is required', $line);
        }

        $parsed = TokenParser::splitNameAndAttributes($rest);

        $entity = new EntityNode($entityType, $parsed->name);
        $entity->attributes = $parsed->attributes;
        return $entity;
    }

    /**
     * Parses an inheritance/trait directive (">Parent", "<Interface", "%Trait").
     *
     * @throws SyntaxException If the directive is invalid or not allowed in context.
     */
    private function parseEntityRelationDirective(Line $line, ?EntityNode $entity): void
    {
        if ($entity === null) {
            throw $this->syntaxError('Inheritance/trait directive found without active entity', $line);
        }

        if (!$entity->canHaveRelations()) {
            throw $this->syntaxError('Inheritance/trait directive not allowed in @file context', $line);
        }

        $target = trim(substr($line->trimmed, 1));

        if ($target === '') {
            throw $this->syntaxError('Inheritance/trait directive cannot be empty', $line);
        }

        $entity->addRelation($line->trimmed[0], $target);
    }

    /**
     * Parses a member declaration (property, constant, method, enum case).
     *
     * @throws SyntaxException If the member declaration is invalid.
     */
    private function parseMemberDirective(Line $line, EntityNode $currentEntity): MemberNode
    {
        $trimmed = $line->trimmed;
        $firstChar = $trimmed[0];
        if (!MemberNode::isValidSymbol($firstChar)) {
            throw $this->syntaxError('Invalid line starting symbol', $line);
        }

        if ($firstChar === '~' && !$currentEntity->canHaveEnumCases()) {
            throw $this->syntaxError("Enum case '~' not allowed in @file context", $line);
        }

        $symbol = $firstChar;
        $visibility = null;
        $rest = substr($trimmed, 1);

        $hasVisibility = ($rest !== '' && MemberNode::hasVisibility($rest[0]));
        if ($hasVisibility) {
            if (!$currentEntity->canHaveVisibility()) {
                throw $this->syntaxError('Visibility modifiers not allowed in @file context', $line);
            }

            $visibility = MemberNode::resolveVisibility($rest[0]);
            $rest = substr($rest, 1);
        }

        $declaration = TokenParser::parseTypedDeclaration($rest);
        $parsed = TokenParser::splitNameAndAttributes($declaration->nameAndKeywords);

        if ($parsed->name === '') {
            throw $this->syntaxError('Member name cannot be empty', $line);
        }

        $member = new MemberNode($parsed->name, MemberNode::resolveType($symbol, $currentEntity), $currentEntity);
        $member->visibility = $visibility;
        $member->attributes = $parsed->attributes;
        $member->dataType = $declaration->dataType;
        $member->returnType = $declaration->dataType;
        $member->value = $declaration->value;

        return $member;
    }

    /**
     * Dispatches an indented line under a method/function block to the matching
     * directive handler (instantiation, return type, or parameter).
     *
     * @throws SyntaxException If the child directive is invalid.
     */
    private function parseMethodChildDirective(Line $line, MemberNode $currentMethod): void
    {
        $trimmed = $line->trimmed;
        if (str_starts_with($trimmed, '^')) {
            $this->parseInstantiationDirective($line, $currentMethod);
            return;
        }

        if (str_starts_with($trimmed, ':')) {
            $this->parseReturnTypeDirective($line, $currentMethod);
            return;
        }

        $this->parseParameterDirective($line, $currentMethod);
    }

    /**
     * Parses an instantiation directive ("^ClassName") inside a method/function block.
     *
     * @throws SyntaxException If the class name is empty.
     */
    private function parseInstantiationDirective(Line $line, MemberNode $currentMethod): void
    {
        $instantiatedClass = trim(substr($line->trimmed, 1));
        if ($instantiatedClass === '') {
            throw $this->syntaxError('Instantiation class name cannot be empty', $line);
        }
        $currentMethod->creates[] = $instantiatedClass;
    }

    /**
     * Parses a return type directive (":Type") inside a method/function block.
     *
     * @throws SyntaxException If the return type is empty.
     */
    private function parseReturnTypeDirective(Line $line, MemberNode $currentMethod): void
    {
        $retType = trim(substr($line->trimmed, 1));
        if ($retType === '') {
            throw $this->syntaxError('Return type cannot be empty', $line);
        }
        $currentMethod->returnType = $retType;
    }

    /**
     * Parses a parameter directive ("$name:type=value", optionally prefixed with "&")
     * inside a method/function block.
     *
     * @throws SyntaxException If the parameter declaration is invalid.
     */
    private function parseParameterDirective(Line $line, MemberNode $currentMethod): void
    {
        $prefix = ParameterNode::parsePrefix($line->trimmed);
        if ($prefix === null) {
            throw $this->syntaxError('Invalid child line format', $line);
        }

        $declaration = TokenParser::parseTypedDeclaration($prefix['body']);
        $parsed = TokenParser::splitNameAndAttributes($declaration->nameAndKeywords);

        if ($parsed->name === '') {
            throw $this->syntaxError('Parameter name cannot be empty', $line);
        }

        $paramNode = new ParameterNode($parsed->name);
        $paramNode->type = $declaration->dataType;
        $paramNode->byRef = $prefix['byRef'];
        $paramNode->value = $declaration->value;

        $currentMethod->parameters[] = $paramNode;
    }

    private function syntaxError(string $message, Line $line): SyntaxException
    {
        return SyntaxException::atLine($message, $line->number + 1, $line->raw);
    }
}
