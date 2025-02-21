<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;

class UseCaseBoilerplateSchema
{
    public static function getUseCaseBoilerplate($dPath, $dName, $opName, $methodParams, $returnTypes)
    {
        $params = [];
        foreach ($methodParams as $m) {
            if ($m->var->name !== "accept" && $m->var->name !== null && $m->type->name !== null) {
                if (str_contains($m->type->name, 'Webpractik\Bitrixgen')) {
                    $params[] = new Param(
                        new Variable($m->var->name),
                        null,
                        new Name(str_replace("Model", "Dto", $m->type->name))
                    );
                } else {
                    $params[] = new Param(
                        new Variable($m->var->name),
                        null,
                        new Identifier($m->type->name)
                    );
                }
            }
        }

        $dto = "";
        $returnType = [];
        if (count($returnTypes) == 1) {
            if ($returnTypes[0] == "null") {
                $returnType = new Identifier("null");
            } else {
                $returnType = new Name\FullyQualified($returnTypes[0]);
                $dto = new Name\FullyQualified($returnTypes[0]);
            }

        } else {
            foreach ($returnTypes as $v) {
                if ($v == "null") {
                    $returnType[] = new Identifier("null");
                } else {
                    $dto = new Name($v);
                    $returnType[] = new Name($v);
                }
            }
        }

        $stmts = [];
        if ($dto !== "") {
            $stmts[] = new Return_(
                new New_(
                    $dto
                )
            );
        } else {
            $stmts[] = new Return_(
                new ConstFetch(new Name("null"))
            );

        }
        return new File(
            $dPath, new Namespace_(
            new Name("Webpractik\Bitrixgen\UseCase"),
            [
                new Class_(
                    new Identifier($dName),
                    [
                        'implements' => [
                            new Name\FullyQualified("Webpractik\Bitrixgen\Interfaces\I" . $dName)
                        ],
                        'stmts' => [
                            new ClassMethod(
                                new Identifier("Process"),
                                [
                                    'flags' => Class_::MODIFIER_PUBLIC,
                                    'params' => $params,
                                    'returnType' => count($returnTypes) == 1 ? $returnType : new UnionType($returnType),
                                    'stmts' => $stmts
                                ]
                            )
                        ]
                    ]
                )
            ]
        ),
            'client'
        );
    }
}
