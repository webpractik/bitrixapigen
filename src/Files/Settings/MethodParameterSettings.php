<?php

declare(strict_types=1);

namespace Webpractik\Bitrixapigen\Files\Settings;

use LogicException;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar;
use UnitEnum;

use function get_class;

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

        return $this->normalizeValue($this->default);
    }

    private function normalizeValue($value): ?Expr
    {
        if ($value instanceof Expr) {
            return $value;
        }

        if (is_null($value)) {
            return new Expr\ConstFetch(
                new Name('null')
            );
        }

        if (is_bool($value)) {
            return new Expr\ConstFetch(
                new Name($value ? 'true' : 'false')
            );
        }

        if (is_int($value)) {
            return new Scalar\Int_($value);
        }

        if (is_float($value)) {
            return new Scalar\Float_($value);
        }

        if (is_string($value)) {
            return new Scalar\String_($value);
        }

        // TODO
        if (is_array($value)) {
            return null;
        }

        if ($value instanceof UnitEnum) {
            return new Expr\ClassConstFetch(new FullyQualified(get_class($value)), new Identifier($value->name));
        }

        throw new LogicException('Invalid value');
    }
}
