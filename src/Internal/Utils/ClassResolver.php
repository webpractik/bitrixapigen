<?php

declare(strict_types=1);

namespace Webpractik\Bitrixapigen\Internal\Utils;

class ClassResolver
{
    /**
     * @var string[] Части пространства имён
     */
    private readonly array $namespaceParts;

    /**
     * @param string          $name
     * @param string|string[] $namespace Пространство имён или его части
     */
    public function __construct(
        private readonly string $name,
        string|array $namespace
    ) {
        if (is_array($namespace)) {
            $this->namespaceParts = $namespace;
        } else {
            $this->namespaceParts = explode('\\', $namespace);
        }
    }

    public function getNamespace(): string
    {
        return implode('\\', $this->namespaceParts);
    }

    public function getClassName(): string
    {
        return $this->name;
    }

    public function getFullClassName(): string
    {
        return $this->getNamespace() . '\\' . $this->getClassName();
    }
}
