<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;
use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;
use Webpractik\Bitrixapigen\Internal\Wrappers\OperationWrapper;

class ControllerBoilerplateSchema
{

    public static function getBoilerplateForProcessWithDtoBody(OperationGuess $operation, string $name, array $methodParams, bool $isOctetStreamFile): ClassMethod
    {
        $stmts = [];
        $args = [];
        $params = [];

        foreach ($methodParams as $m) {
            $typeName = $m->type?->name ?? '';

            if ($m->var->name === "accept" || $m->var->name === null || $typeName === '' || str_contains($m->var->name, 'headerParameters')) {
                continue;
            }

            if (str_contains($typeName, 'Webpractik\Bitrixgen')) {
                $args[] = new Arg(
                    new Variable('dto')
                );
                $dtoTypeName = str_replace('?', '', $typeName);
                $stmts = self::getDtoResolver($stmts, mb_substr(str_replace("Model", "Dto", $dtoTypeName), 1));
                continue;
            }

            if (str_contains($typeName, 'array')) {
                $arElementType = (new OperationWrapper($operation))->getArrayItemType();

                if ($arElementType !== null && DtoNameResolver::isFullDtoClassName($arElementType)) {
                    $args[] = new Arg(
                        new Variable('collection')
                    );
                    $dtoNameResolver = DtoNameResolver::createByFullDtoClassName($arElementType);
                    $collectionClassName = new Name($dtoNameResolver->getFullCollectionClassName());
                    $stmts = self::getDtoCollectionResolver($stmts, mb_substr($collectionClassName, 1));
                } else {
                    $args[] = new Arg(
                        new Variable($m->var->name)
                    );
                    $stmts[] = self::getQueryParamsResolver();
                }
                continue;
            }

            $args[] = new Arg(
                new Variable($m->var->name)
            );
            $params[] = new Param(
                var: new Variable($m->var->name),
                type: new Identifier($typeName)
            );
        }

        if ($isOctetStreamFile) {
            $args[] = new Arg(
                new Variable('octetStreamRawContent')
            );
            $stmts[] = new Expression(
                new Assign(
                    new Variable('octetStreamRawContent'),
                    new FuncCall(
                        new Name('file_get_contents'),
                        [
                            new Arg(new String_('php://input'))
                        ]
                    )
                )
            );
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
        $stmts[] = new Expression(
            new MethodCall(
                new Variable('this'),
                'initializeDto',
                [
                    new Variable('dto'),
                    new Variable('requestBody'),
                ]
            )
        );

        return $stmts;
    }

    public static function getDtoCollectionResolver($stmts, $collectionClassName)
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
                new Variable("collection"),
                new New_(
                    new FullyQualified($collectionClassName)
                )
            )
        );
        $stmts[] = new Expression(
            new MethodCall(
                new Variable('this'),
                'initializeDtoCollection',
                [
                    new Variable('collection'),
                    new Variable('requestBody'),
                ]
            )
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
