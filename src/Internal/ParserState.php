<?php declare(strict_types=1);

namespace Ponymator\Parser\Internal;

use Ponymator\Parser\Ast\EntityNode;
use Ponymator\Parser\Ast\MemberNode;

/**
 * @internal Carries the parser's "what is currently open" context across
 *           iterations of the top-level parse loop. Mutated in place.
 */
final class ParserState
{
    public ?EntityNode $currentEntity = null;
    public ?MemberNode $currentMethod = null;
}
