<?php

namespace Webpractik\Bitrixapigen\Internal;

class BetterNaming
{
    public static function getClassName(string $reference, string $name): string
    {
        $nameParts         = [];
        $isMultiFileSchema = preg_match('/Response\d{3}(?<tail>.*)/', $name, $nameParts);
        $referenceParts    = [];
        $isReferenceParsed = preg_match('~/?(?<fileName>[^/.]+).(json|ya?ml)#(?<basePath>.*/)(?<name>[^/]+)$~', $reference, $referenceParts);
        if ($isMultiFileSchema && $isReferenceParsed) {
            $isNestedSchema = substr_count($referenceParts['basePath'], '/') > 1;
            $schemaName     = $isNestedSchema ? $nameParts['tail'] : $referenceParts['name'];

            return ucfirst($referenceParts['fileName']) . ucfirst($schemaName);
        }

        return $name;
    }
}
