<?php declare(strict_types=1);

namespace Ponymator\Parser\Internal;

/**
 * @internal
 */
final class TypedDeclaration
{
    public function __construct(
        public string $nameAndKeywords,
        public ?string $dataType,
        public ?string $value,
    ) {
    }
}
