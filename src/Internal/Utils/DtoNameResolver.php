<?php

namespace Webpractik\Bitrixapigen\Internal\Utils;

use RuntimeException;

/**
 * @internal
 * Класс для преобразования названия модели в названия классов Dto и Collection
 */
class DtoNameResolver
{
    private const MODULE_NAMESPACE     = 'Webpractik\\Bitrixgen';
    private const DTO_NAMESPACE        = 'Dto';
    private const COLLECTION_NAMESPACE = 'Collection';

    private function __construct(private string $modelName)
    {
    }

    public static function createByModelFullName(string $modelFullName): self
    {
        $matches = [];
        if (!preg_match('#^.*\\\\Model\\\\([A-Z][A-Za-z0-9_]*)$#', $modelFullName, $matches)) {
            return throw new RuntimeException('Invalid model full name: ' . $modelFullName, 400);
        }

        return new self($matches[1]);
    }

    public static function createByModelName(string $modelName): self
    {
        if (!preg_match('#^[A-Z][A-Za-z0-9_]+$#', $modelName)) {
            return throw new RuntimeException('Invalid model name: ' . $modelName, 400);
        }

        return new self($modelName);
    }

    public static function createByDtoFullClassName(string $dtoFullClassName): self
    {
        $matches = [];
        if (!preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\(?!Collection\\\\)([A-Z][A-Za-z0-9_]+)Dto$#', $dtoFullClassName, $matches)) {
            return throw new RuntimeException('Invalid full dto class name: ' . $dtoFullClassName, 400);
        }

        return new self($matches[1]);
    }

    public static function createByDtoClassName(string $dtoClassName): self
    {
        $matches = [];
        if (!preg_match('#^([A-Z][A-Za-z0-9_]+)Dto$#', $dtoClassName, $matches)) {
            return throw new RuntimeException('Invalid dto class name: ' . $dtoClassName, 400);
        }

        return new self($matches[1]);
    }

    public static function createByCollectionFullClassName(string $dtoFullCollectionClassName): ?self
    {
        $matches = [];
        if (!preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\' . self::COLLECTION_NAMESPACE . '\\\\([A-Z][A-Za-z0-9_]+)DtoCollection$#', $dtoFullCollectionClassName, $matches)) {
            return throw new RuntimeException('Invalid dto collection class name: ' . $dtoFullCollectionClassName, 400);
        }

        return new self($matches[1]);
    }

    public static function isCollectionFullClassName(string $dtoFullCollectionClassName): bool
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\' . self::COLLECTION_NAMESPACE . '\\\\([A-Z][A-Za-z0-9_]+)DtoCollection$#', $dtoFullCollectionClassName);
    }

    public static function isDtoFullClassName(string $dtoFullClassName): bool
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\(?!' . self::COLLECTION_NAMESPACE . '\\\\)([A-Z][A-Za-z0-9_]+)Dto$#', $dtoFullClassName);
    }

    public static function isModelFullName(string $modelFullName): string
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\Model\\\\(?!' . self::COLLECTION_NAMESPACE . '\\\\)([A-Z][A-Za-z0-9_]+)$#', $modelFullName);
    }

    public static function getCollectionNamespace(): string
    {
        return self::getDtoNamespace() . '\\' . self::COLLECTION_NAMESPACE;
    }

    public static function getDtoNamespace(): string
    {
        return self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE;
    }

    public static function getModelNamespace(): string
    {
        return self::MODULE_NAMESPACE . '\\Model';
    }

    public function getDtoFullClassName(): string
    {
        return self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE . '\\' . $this->getDtoClassName();
    }

    public function getCollectionFullClassName(): string
    {
        return self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE . '\\' . self::COLLECTION_NAMESPACE . '\\' . $this->getCollectionClassName();
    }

    public function getDtoClassName(): string
    {
        return $this->modelName . 'Dto';
    }

    public function getCollectionClassName(): string
    {
        return $this->modelName . 'DtoCollection';
    }
}
