<?php

namespace Webpractik\Bitrixapigen\Internal;

class BetterNaming
{
    public static function getSchemaName(string $reference): string
    {
        $fragmentIdentifier = preg_replace('/.+#\//', '', $reference);

        return str_replace('components/schemas/', '', $fragmentIdentifier);
    }

    public static function getClassName(string $reference, string $name): string
    {
        $nameParts         = [];
        $isMultiFileSchema = preg_match('/Response\d{3}(?<tail>.*)/', $name, $nameParts);
        $referenceParts    = [];
        $isReferenceParsed = preg_match('~/?([^/.]+).(json|ya?ml)#(?<basePath>.*/)(?<name>[^/]+)$~', $reference, $referenceParts);
        if ($isMultiFileSchema && $isReferenceParsed) {
            $isNestedSchema = substr_count($referenceParts['basePath'], '/') > 1;
            $schemaName     = $isNestedSchema ? $nameParts['tail'] : $referenceParts['name'];

            return $schemaName ? ucfirst($schemaName) : $name;
        }

        return $name;
    }

    /**
     * Получить части подпространства имён
     *
     * @param string $reference
     * @param string $schemaOrigin
     *
     * @return string[]
     */
    public static function getSubNamespaceParts(string $reference, string $schemaOrigin): array
    {
        if (str_starts_with($reference, $schemaOrigin)) {
            return [];
        }

        $shortReference = self::getShortReference($reference, $schemaOrigin);
        [$fileReference] = explode('#', $shortReference);
        $parts = preg_split('/[\/.]/', $fileReference);
        // Убрать расширение
        array_pop($parts);

        return array_map(static function ($part) {
            return self::classify($part);
        }, $parts);
    }

    /**
     * Converts a word into the format for a Doctrine class name. Converts 'table_name' to 'TableName'.
     */
    public static function classify(string $word): string
    {
        return str_replace([' ', '_', '-'], '', ucwords($word, ' _-'));
    }

    /**
     * Camelizes a word. This uses the classify() method and turns the first character to lowercase.
     */
    public static function camelize(string $word): string
    {
        return lcfirst(self::classify($word));
    }

    private static function getShortReference(string $reference, string $schemaOrigin): string
    {
        $lastSeparatorPosition = strrpos($schemaOrigin, '/');
        if (false === $lastSeparatorPosition) {
            return $reference;
        }

        return substr($reference, $lastSeparatorPosition + 1);
    }
}
