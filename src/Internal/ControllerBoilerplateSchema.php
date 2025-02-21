<?php

namespace Webpractik\Bitrixapigen\Internal;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Return_;

class ControllerBoilerplateSchema
{

    public static function getBoilerplateForProcessWithDtoBody($name, $methodParams): ClassMethod
    {
        $stmts = [];
        $args = [];
        $params = [];
        foreach ($methodParams as $m) {
            if ($m->var->name !== "accept" && $m->var->name !== null && $m->type->name !== null) {
                $args[] = new Arg(
                    new Variable($m->var->name)
                );
                if (str_contains($m->type->name, 'Webpractik\Bitrixgen')) {
                    $stmts = self::getDtoResolver($stmts, mb_substr(str_replace("Model", "Dto", $m->type->name), 1));
                }
                if (str_contains($m->type->name, 'array')) {
                    $stmts[] = self::getQueryParamsResolver();
                }
                $params[] = new Param(
                    new Variable($m->var->name)
                );

            }
        }

        $stmts[] = new Expression(
            new Assign(
                new Variable("serviceLocator"),
                new StaticCall(
                    new FullyQualified("Bitrix\Main\DI\ServiceLocator\ServiceLocator"),
                    new Identifier("getInstance")
                )
            )
        );
        $stmts[] = new Expression(
            new Assign(
                new Variable("class"),
                new MethodCall(
                    new Variable("serviceLocator"),
                    new Identifier("get"),
                    [
                        new Arg(
                            new String_("webpractik.bitrixgen." . $name)
                        )
                    ]
                )
            )
        );
        $stmts[] = new Return_(
            new New_(
                new FullyQualified("Bitrix\Main\Engine\Response\Json"),
                [
                    new Arg(
                        new MethodCall(
                            new Variable("class"),
                            new Identifier("Process"),
                            $args
                        )
                    )
                ]
            )
        );


        return new ClassMethod(
            new Identifier($name),
            [
                'params' => $params,
                'type' => Class_::MODIFIER_PUBLIC,
                'stmts' => $stmts
            ]
        );
    }

    public static function getDtoResolver($stmts, $dtoPath)
    {
        $stmts[] = new Expression(
            new Assign(
                new Variable("input"),
                new StaticCall(
                    new MethodCall(
                        new Variable("this"),
                        new Identifier("getRequest")
                    ),
                    new Identifier("getInput")
                )
            )
        );
        $stmts[] = new Expression(
            new Assign(
                new Variable("dto"),
                new New_(
                    new FullyQualified($dtoPath)
                )
            )
        );
        $stmts[] = new Foreach_(
            new Variable("input"),
            new Variable("v"),
            [
                'keyVar' => new Variable("k"),
                'byRef' => false,
                'stmts' => [
                    new Expression(
                        new Assign(
                            new PropertyFetch(
                                new Variable("dto"),
                                new Variable("k")
                            ),
                            new Variable("v")
                        )
                    )
                ]
            ]
        );

        return $stmts;
    }

    public static function getQueryParamsResolver(): Expression
    {
        return new Expression(
            new Assign(
                new Variable("queryParameters"),
                new MethodCall(
                    new MethodCall(
                        new Variable("this"),
                        new Identifier("getRequest")
                    ),
                    new Identifier("getValues")
                )
            )
        );
    }
}