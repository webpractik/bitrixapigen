<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;
use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;
use Webpractik\Bitrixapigen\Internal\Wrappers\OperationWrapper;

class ControllerBoilerplateSchema
{

    public static function getBoilerplateForProcessWithDtoBody(OperationGuess $operation, string $name, array $methodParams, array $returnTypes, bool $isOctetStreamFile): ClassMethod
    {
        $operationWrapped = (new OperationWrapper($operation));
        $stmts            = [];
        $args             = [];
        $params           = [];

        $stmtsGetData = [];

        foreach ($methodParams as $m) {
            $typeName = $m->type?->name ?? '';

            if ($m->var->name === "accept" || $m->var->name === null || $typeName === '' || str_contains($m->var->name, 'headerParameters')) {
                continue;
            }

            //read data
            if ($operationWrapped->isMultipartFormData() && $m->var->name === 'requestBody') {
                $stmtsGetData = array_merge($stmtsGetData, self::getDataFromRequestBodyMultipart());
            } elseif ($operationWrapped->isApplicationJson() && $m->var->name === 'requestBody') {
                $stmtsGetData = array_merge($stmtsGetData, self::getDataFromRequestBodyJson());
            } elseif ($m->var->name === 'queryParameters') {
                $args[]         = new Arg(
                    new Variable($m->var->name)
                );
                $stmtsGetData[] = self::getQueryParamsResolver();
            } else {
                $args[]   = new Arg(
                    new Variable($m->var->name)
                );
                $params[] = new Param(
                    var: new Variable($m->var->name),
                    type: new Identifier($typeName)
                );
            }

            if (str_contains($typeName, 'Webpractik\Bitrixgen')) {
                $args[]          = new Arg(
                    new Variable('dto')
                );
                $dtoTypeName     = str_replace('?', '', $typeName);

                $stmts = self::getValidatorResolver($stmts, mb_substr(str_replace("Model", "Validator", $dtoTypeName . 'Constraint'), 1));
                $dtoNameResolver = DtoNameResolver::createByModelFullName($dtoTypeName);
                $stmts = self::getDataToDtoConverter($stmts, $dtoNameResolver->getDtoFullClassName());

                if ($operationWrapped->isMultipartFormData()) {
                    $stmtsGetData = array_merge($stmtsGetData, self::getFilesFromMultipart());
                } else {
                    $stmtsGetData = array_merge($stmtsGetData, self::getFilesAsEmptyArray());
                }
                continue;
            }

            if (str_contains($typeName, 'array')) {
                $arElementType = $operationWrapped->getArrayItemType();

                if ($arElementType !== null && DtoNameResolver::isDtoFullClassName($arElementType)) {
                    $args[]              = new Arg(
                        new Variable('collection')
                    );

                    $constraintClassName = 'Webpractik\Bitrixgen\Validator\\'.ucfirst($operation->getOperation()->getOperationId() . 'OperationConstraint');
                    $stmts = self::getValidatorResolver($stmts,  $constraintClassName);

                    $dtoNameResolver     = DtoNameResolver::createByDtoFullClassName($arElementType);
                    $collectionClassName = new Name($dtoNameResolver->getCollectionFullClassName());
                    $stmts               = self::getDtoCollectionResolver($stmts, $collectionClassName);

                    if ($operationWrapped->isMultipartFormData()) {
                        $stmtsGetData = array_merge($stmtsGetData, self::getFilesFromMultipart());
                    } else {
                        $stmtsGetData = array_merge($stmtsGetData, self::getFilesAsEmptyArray());
                    }
                }
                continue;
            }
        }

        if ($isOctetStreamFile) {
            $args[] = new Arg(
                new Variable('octetStreamRawContent')
            );

            $stmts[] = new Expression(
                new Assign(
                    new Variable("octetStreamRawContent"),
                    new StaticCall(
                        new MethodCall(
                            new Variable("this"),
                            new Identifier("getRequest")
                        ),
                        new Identifier("getInput")
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
                        ),
                    ]
                )
            )
        );

        $useCaseCallMethod = new MethodCall(
            new Variable("class"),
            new Identifier('process'),
            $args
        );

        $stmts = array_merge($stmtsGetData, $stmts);

        $isNeedReturnData = count($returnTypes) > 1 || reset($returnTypes) !== 'null';

        if ($isNeedReturnData) {
            $stmts[] = new Return_(
                new New_(
                    new FullyQualified("Bitrix\Main\Engine\Response\Json"),
                    [
                        new Arg(
                            $useCaseCallMethod
                        ),
                    ]
                )
            );
        } else {
            $stmts[] = new Expression(
                $useCaseCallMethod
            );

            $stmts[] = new Return_(
                new New_(
                    new FullyQualified("Bitrix\Main\HttpResponse"),
                    []
                )
            );
        }

        return new ClassMethod(
            new Identifier($name . 'Action'),
            [
                'params' => $params,
                'type'   => Modifiers::PUBLIC,
                'stmts'  => $stmts,
            ]
        );
    }

    public static function getDataFromRequestBodyJson(): array
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
                                    new Arg(new ConstFetch(new Name('JSON_THROW_ON_ERROR'))),
                                ]
                            )
                        )
                    ),
                ],
            ]
        );

        return $stmts;
    }

    public static function getFilesFromMultipart(): array
    {
        $stmts   = [];
        $stmts[] = new Expression(
            new Assign(
                new Variable('files'),
                new MethodCall(
                    new MethodCall(
                        new MethodCall(new Variable('this'), 'getRequest'),
                        'getFileList'
                    ),
                    'getValues'
                )
            )
        );

        $stmts[] = new Expression(
            new Assign(
                new Variable('normalizedFiles'),
                new MethodCall(
                    new New_(new Name('\Webpractik\Bitrixgen\Http\Normalizer\BitrixFileNormalizer')),
                    'normalize',
                    [
                        new Arg(new Variable('files')),
                    ]
                )
            )
        );

        return $stmts;
    }

    public static function getFilesAsEmptyArray(): array
    {
        $stmts   = [];
        $stmts[] = new Expression(
            new Assign(
                new Variable('normalizedFiles'),
                new Array_([])
            )
        );

        return $stmts;
    }

    public static function getDataFromRequestBodyMultipart(): array
    {
        $stmts[] = new Expression(
            new Assign(
                new Variable('requestBody'),
                new MethodCall(
                    new MethodCall(
                        new MethodCall(new Variable('this'), 'getRequest'),
                        'getPostList'
                    ),
                    'getValues'
                )
            )
        );

        return $stmts;
    }

    public static function getValidatorResolver($stmts, $constraintPath)
    {
        $stmts[] = new Expression(
            new Assign(
                new Variable("constraint"),
                new New_(
                    new FullyQualified($constraintPath)
                )
            )
        );

        $stmts[] = new Expression(
            new Assign(
                new Variable('locale'),
                new MethodCall(
                    new StaticCall(
                        new Name('ModuleContext'),
                        'get'
                    ),
                    'getLocale'
                )
            )
        );

        $stmts[] = new Expression(
            new MethodCall(
                new Variable('this'),
                'validate',
                [
                    new Variable('requestBody'),
                    new Variable('constraint'),
                    new Variable('locale'),
                ]
            )
        );

        return $stmts;
    }

    public static function getDataToDtoConverter($stmts, $dtoPath)
    {
        $stmts[] = new Expression(
            new Assign(
                new Variable('dtoClass'),
                new ClassConstFetch(
                    new FullyQualified($dtoPath),
                    'class'
                )
            )
        );
        $stmts[] = new Expression(
            new Assign(
                new Variable('dto'),
                new MethodCall(
                    new Variable('this'),
                    'convertDataToDto',
                    [
                        new Variable('dtoClass'),
                        new Variable('requestBody'),
                        new Variable('normalizedFiles'),
                    ]
                )
            )
        );

        return $stmts;
    }

    public static function getDtoCollectionResolver($stmts, $collectionClassName)
    {
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
                    new Variable('normalizedFiles'),
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
                        new MethodCall(
                            new Variable("this"),
                            new Identifier("getRequest")
                        ),
                        new Identifier("getQueryList")
                    ),
                    'getValues'
                )
            )
        );
    }
}
