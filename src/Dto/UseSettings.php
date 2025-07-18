<?php

namespace Webpractik\Bitrixapigen\Dto;

class UseSettings
{
    public function __construct(
        public readonly string $fullClassName,
        public readonly null|string $alias,
    ) {
    }
}
