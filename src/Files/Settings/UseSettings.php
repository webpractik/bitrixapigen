<?php

declare(strict_types=1);

namespace Webpractik\Bitrixapigen\Files\Settings;

class UseSettings
{
    public function __construct(
        public readonly string $fullClassName,
        public readonly null|string $alias,
    ) {
    }
}
