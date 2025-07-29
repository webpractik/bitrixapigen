<?php

declare(strict_types=1);

namespace Webpractik\Bitrixapigen\Files\Settings;

use LogicException;
use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\NullableType;

class MethodParameterSettings
{
    public function __construct(
        public readonly string $name,
        public readonly Node $type,
        public readonly bool $isRequired,
        public readonly bool $isNullable,
        public readonly int $flags = 0,
        private readonly mixed $default = null,
    ) {
        if ($type instanceof NullableType) {
            throw new LogicException(sprintf('Неожиданный тип "%s". Используйте флаг isRequired.', $type->getType()));
        }
    }

    /**
     * @return Expr|null
     */
    public function getDefault(): ?Expr
    {
        if (null === $this->default) {
            return null;
        }

        return BuilderHelpers::normalizeValue($this->default);
    }
}
