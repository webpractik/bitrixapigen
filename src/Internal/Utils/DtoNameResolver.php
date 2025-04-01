<?php

namespace Webpractik\Bitrixapigen\Internal\Utils;

/**
 * @internal
 * Класс для преобразования названия модели в названия классов Dto и Collection
 */
class DtoNameResolver
{
    private const MODULE_NAMESPACE = 'Webpractik\\Bitrixgen';
    private const DTO_NAMESPACE = 'Dto';
    private const COLLECTION_NAMESPACE = 'Collection';

    private function __construct(private string $modelName)
    {
    }

    public static function createByFullModelName(string $fullModelName): ?self
    {
        $matches = [];
        if (!preg_match('#^(.*)\\\\Model\\\\([A-Z][A-Za-z0-9_]*)$#', $fullModelName, $matches)) {
            return null;
        }
        return new self($matches[1]);
    }

    public static function createByModelName(string $modelName): ?self
    {
        if (!preg_match('#^[A-Z][A-Za-z0-9_]+$#', $modelName)) {
            return null;
        }
        return new self($modelName);
    }

    public static function createByFullDtoClassName(string $fullDtoClassName): ?self
    {
        $matches = [];
        if (!preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\(?!Collection\\\\)([A-Z][A-Za-z0-9_]+)$#', $fullDtoClassName, $matches)) {
            return null;
        }
        return new self($matches[1]);
    }


    public static function createByFullCollectionClassName(string $fullDtoCollectionClassName): ?self
    {
        $matches = [];
        if (!preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\' . self::COLLECTION_NAMESPACE . '\\\\([A-Z][A-Za-z0-9_]+)Collection$#', $fullDtoCollectionClassName, $matches)) {
            return null;
        }
        return new self($matches[1]);
    }

    public static function isFullCollectionClassName(string $fullDtoCollectionClassName): bool
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\' . self::COLLECTION_NAMESPACE . '\\\\([A-Z][A-Za-z0-9_]+)Collection$#', $fullDtoCollectionClassName);
    }

    public static function isFullDtoClassName(string $fullDtoClassName): bool
    {
        return preg_match('#^\\\?' . str_replace('\\', '\\\\', self::MODULE_NAMESPACE) . '\\\\' . self::DTO_NAMESPACE . '\\\\(?!' . self::COLLECTION_NAMESPACE . '\\\\)([A-Z][A-Za-z0-9_]+)$#', $fullDtoClassName);
    }


    public function getFullDtoClassName(): string
    {
        return '\\' . self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE . '\\' . $this->modelName;
    }

    public function getFullCollectionClassName(): string
    {
        return '\\' . self::MODULE_NAMESPACE . '\\' . self::DTO_NAMESPACE . '\\' . self::COLLECTION_NAMESPACE . '\\' . $this->modelName . 'Collection';
    }
}
