<?php

namespace Webpractik\Bitrixapigen\Internal\Utils;

class Aliases
{
    /**
     * @param string   $fullClassName
     * @param string[] $allFullClassNames
     *
     * @return string|null
     */
    public static function getUseAlias(string $fullClassName, array $allFullClassNames): null|string
    {
        $className = preg_replace('/.+\\\\/', '', $fullClassName);

        $uniqueFullClassNames = array_unique($allFullClassNames);

        $sameClassNames = array_filter($uniqueFullClassNames, static function ($name) use ($className) {
            return str_ends_with($name, '\\' . $className);
        });

        if (count($sameClassNames) > 1) {
            $index = array_search($fullClassName, $sameClassNames);

            return $className . $index;
        }

        return null;
    }
}
