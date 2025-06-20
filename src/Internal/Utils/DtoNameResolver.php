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

    public static function createByFullModelName(string $fullModelName): self
    {
        $matches = [];
        if (!preg_match('#^.*\\\\Model\\\\([A-Z][A-Za-z0-9_]*)$#', $fullModelName, $matches)) {
            return throw new RuntimeException('Invalid model full name: ' . $fullModelName, 400);
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

    public static function createByFullDtoClassName(string $fullDtoClassName): self
    {
        $matches = [];
        if (!preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\(?!Collection\\\\)([A-Z][A-Za-z0-9_]+)Dto$#', $fullDtoClassName, $matches)) {
            return throw new RuntimeException('Invalid full dto class name: ' . $fullDtoClassName, 400);
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

    public static function createByFullCollectionClassName(string $fullDtoCollectionClassName): ?self
    {
        $matches = [];
        if (!preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\' . self::COLLECTION_NAMESPACE . '\\\\([A-Z][A-Za-z0-9_]+)DtoCollection$#', $fullDtoCollectionClassName, $matches)) {
            return throw new RuntimeException('Invalid dto collection class name: ' . $fullDtoCollectionClassName, 400);
        }

        return new self($matches[1]);
    }

    public static function isFullCollectionClassName(string $fullDtoCollectionClassName): bool
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\' . self::COLLECTION_NAMESPACE . '\\\\([A-Z][A-Za-z0-9_]+)DtoCollection$#', $fullDtoCollectionClassName);
    }

    public static function isFullDtoClassName(string $fullDtoClassName): bool
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\(?!' . self::COLLECTION_NAMESPACE . '\\\\)([A-Z][A-Za-z0-9_]+)Dto$#', $fullDtoClassName);
    }

    public function getFullDtoClassName(): string
    {
        return self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE . '\\' . $this->getDtoClassName();
    }

    public static function isModelFullName(string $modelFullName): string
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\Model\\\\(?!' . self::COLLECTION_NAMESPACE . '\\\\)([A-Z][A-Za-z0-9_]+)$#', $modelFullName);
    }

    public function getFullCollectionClassName(): string
    {
        return self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE . '\\' . self::COLLECTION_NAMESPACE . '\\' . $this->getDtoCollectionClassName();
    }

    public function getDtoClassName(): string
    {
        return $this->modelName . 'Dto';
    }

    public function getDtoCollectionClassName(): string
    {
        return $this->modelName . 'DtoCollection';
    }

    public static function getDtoCollectionNamespace(): string
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
}
