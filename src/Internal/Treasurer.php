<?php

namespace Webpractik\Bitrixapigen\Internal;

use PhpParser\Node;

class Treasurer
{

    /**
     * Add a new pet to the store
     *
     * @param Node\Stmt[] $exist
     * @param Node\Stmt[] $new
     */
    public static function analyze($exist, $new)
    {
        $oldMap = self::buildMapOfMethods($exist[0]);

        foreach ($new as $newElem) {
            if (get_class($newElem) == "PhpParser\Node\Stmt\Class_") {
                foreach ($newElem->stmts as $key => $value) {
                    if (array_key_exists($value->name->name, $oldMap)) {
                        $newElem->stmts[$key] = $oldMap[$value->name->name];
                    }
                }
            }
        }
    }

    public static function buildMapOfMethods($ast)
    {
        $data = [];
        foreach ($ast->stmts as $existN) {
            if (get_class($existN) == "PhpParser\Node\Stmt\Class_") {
                foreach($existN->stmts as $stmt) {

                    if (get_class($stmt) == "PhpParser\Node\Stmt\ClassMethod") {
                        $data[$stmt->name->name] = $stmt;
                    }
                }
            }

        }
        return $data;
    }
}
