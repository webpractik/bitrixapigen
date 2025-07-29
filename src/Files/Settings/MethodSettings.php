<?php

declare(strict_types=1);

namespace Webpractik\Bitrixapigen\Files\Settings;

class MethodSettings
{
    /**
     * @param string                    $name
     * @param int                       $flags
     * @param MethodParameterSettings[] $parameters
     */
    public function __construct(
        public readonly string $name,
        public readonly int $flags = 0,
        public readonly array $parameters = [],
    ) {
    }
}
