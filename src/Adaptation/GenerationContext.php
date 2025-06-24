<?php

namespace Webpractik\Bitrixapigen\Adaptation;

class GenerationContext
{
    private static ?self $instance = null;

    private function __construct(
        private readonly string $locale,
    )
    {
    }

    public static function init(string $locale): void
    {
        self::$instance = new self(
            $locale
        );
    }

    public static function get(): self
    {
        if (!self::$instance) {
            throw new \RuntimeException('GenerationContext not initialized');
        }

        return self::$instance;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }
}
