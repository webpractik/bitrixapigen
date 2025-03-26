<?php

namespace Webpractik\Bitrixapigen\Internal;

use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Scalar\LNumber;

class ControllerBoilerplateSchema
{

    public static function getBoilerplateForProcessWithDtoBody($name, $methodParams): ClassMethod
    {
        $stmts = [];
        $args = [];
        $params = [];

        foreach ($methodParams as $m) {
            $typeName = $m->type?->name ?? '';

            if ($m->var->name !== "accept" && $m->var->name !== null && $typeName !== '') {
                if (str_contains($typeName, 'Webpractik\Bitrixgen')) {
                    $args[] = new Arg(
                        new Variable('dto')
                    );
                } elseif (!str_contains($m->var->name, 'headerParameters') ) {
                    $args[] = new Arg(
                        new Variable($m->var->name)
                    );
                }



                if (str_contains($typeName, 'Webpractik\Bitrixgen')) {
                    $dtoTypeName = str_replace('?', '', $typeName);
                    $stmts = self::getDtoResolver($stmts, mb_substr(str_replace("Model", "Dto", $dtoTypeName), 1));
                } elseif (str_contains($typeName, 'array')) {
                    $stmts[] = self::getQueryParamsResolver();
                } else {
                    $params[] = new Param(
                        var: new Variable($m->var->name),
                        type: new Identifier($typeName)
                    );
                }
            }
        }

        $stmts[] = new Expression(
            new Assign(
                new Variable("serviceLocator"),
                new StaticCall(
                    new FullyQualified("Bitrix\Main\DI\ServiceLocator"),
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
                            new Identifier('process'),
                            $args
                        )
                    )
                ]
            )
        );


        return new ClassMethod(
            new Identifier($name . 'Action'),
            [
                'params' => $params,
                'type' => Modifiers::PUBLIC,
                'stmts' => $stmts
            ]
        );
    }

    public static function getDtoResolver($stmts, $dtoPath)
    {
        $stmts[] = new Expression(
            new Assign(
                new Variable("requestBody"),
                new StaticCall(
                    new MethodCall(
                        new Variable("this"),
                        new Identifier("getRequest")
                    ),
                    new Identifier("getInput")
                )
            )
        );

        $stmts[] = new If_(
            new MethodCall(
                new Variable('this->getRequest()'),
                new Identifier('isJson'),
                []
            ),
            [
                'stmts' => [
                    new Expression(
                        new Assign(
                            new Variable('requestBody'),
                            new FuncCall(
                                new FullyQualified('json_decode'),
                                [
                                    new Arg(new Variable('requestBody')),
                                    new Arg(new ConstFetch(new Name('true'))),
                                    new Arg(new LNumber(512)),
                                    new Arg(new ConstFetch(new Name('JSON_THROW_ON_ERROR')))
                                ]
                            )
                        )
                    )
                ]
            ]
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
            new Variable("requestBody"),
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
