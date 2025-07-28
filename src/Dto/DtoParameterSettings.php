<?php

namespace Webpractik\Bitrixapigen\Dto;

use PhpParser\Node;

class DtoParameterSettings
{
    public function __construct(
        public readonly string $name,
        public readonly Node $type,
        public readonly null|string $fullClassName,
        public readonly bool $isRequired,
        public readonly bool $isNullable,
    ) {
    }
}
