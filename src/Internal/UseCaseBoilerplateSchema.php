<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\UnionType;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;
use Webpractik\Bitrixapigen\Internal\Wrappers\OperationWrapper;

class UseCaseBoilerplateSchema
{
    public static function getUseCaseBoilerplate(OperationGuess $operation, string $dPath, string $dName, string $opName, array $methodParams, array $returnTypes, bool $isOctetStreamFile)
    {
        $params = [];
        foreach ($methodParams as $m) {
            $typeName = $m->type?->name ?? '';

            if ($m->var->name === "accept" || $m->var->name === null || $typeName === '' || str_contains($m->var->name, 'headerParameters')) {
                continue;
            }

            if ($m->var->name === 'queryParameters') {
                $params[] = new Param(
                    new Variable('queryParameters'),
                    null,
                    new Identifier('array')
                );
            } elseif ($m->var->name !== 'requestBody') {
                $params[] = new Param(
                    var: new Variable($m->var->name),
                    type: new Identifier($typeName)
                );
            }

            if (str_contains($typeName, 'Webpractik\Bitrixgen')) {
                $dtoNameResolver = DtoNameResolver::createByModelFullName($typeName);
                $params[]        = new Param(
                    new Variable('requestDto'),
                    null,
                    new Name('\\' . $dtoNameResolver->getDtoFullClassName())
                );
                continue;
            }

            if (str_contains($typeName, 'array')) {
                $arElementType = (new OperationWrapper($operation))->getArrayItemType();
                if (str_contains($arElementType, '\\Model\\')) {
                    $dtoNameResolver = DtoNameResolver::createByModelFullName($arElementType);
                    $collectionClass = new Name('\\' . $dtoNameResolver->getCollectionFullClassName());
                    $params[]        = new Param(
                        new Variable('requestDtoCollection'),
                        null,
                        $collectionClass
                    );
                }
                continue;
            }
        }

        if ($isOctetStreamFile) {
            $params[] = new Param(
                new Variable('octetStreamRawContent'),
                null,
                new Identifier('string')
            );
        }

        $dto             = "";
        $collectionClass = null;
        $returnType      = [];
        if (count($returnTypes) == 1) {
            $v = $returnTypes[0];
            if ($returnTypes[0] == "null") {
                $returnType = new Identifier('void');
            } else {
                $modelName = $v;
                if (self::ifIsModelArray($modelName)) {
                    $modelName       = str_replace('[]', '', $modelName);
                    $dtoNameResolver = DtoNameResolver::createByModelFullName($modelName);
                    $collectionClass = new Name($dtoNameResolver->getCollectionFullClassName());
                    $returnType      = $collectionClass;
                } else {
                    $returnType = new Name($v);
                }
                $dto = new Name($v);
            }
        } else {
            foreach ($returnTypes as $v) {
                if ($v == "null") {
                    $returnType[] = new Identifier("null");
                } else {
                    $modelName = $v;
                    if (self::ifIsModelArray($modelName)) {
                        $modelName        = str_replace('[]', '', $modelName);
                        $dtoNameResolver  = DtoNameResolver::createByModelFullName($modelName);
                        $collectionClass  = new Name('\\' . $dtoNameResolver->getCollectionFullClassName());
                        $resultReturnType = $collectionClass;
                        $returnType[]     = $resultReturnType;
                    } elseif (str_contains($v, '\\Model\\')) {
                        $dtoNameResolver  = DtoNameResolver::createByModelFullName($v);
                        $resultReturnType = new Name('\\' . $dtoNameResolver->getDtoFullClassName());
                        $returnType[]     = $resultReturnType;
                    } else {
                        $resultReturnType = new Name($v);
                        $returnType[]     = $resultReturnType;
                    }
                    $dto = $resultReturnType;
                }
            }
        }

        $stmts = [];
        if ($dto !== "") {
            if ($collectionClass !== null) {
                $collectionVar = new Variable('collection');
                $stmts[]       = new Expression(
                    new Assign(
                        $collectionVar,
                        new New_($collectionClass)
                    )
                );

                $stmts[] = new Expression(
                    new MethodCall(
                        $collectionVar,
                        'add',
                        [new New_($dto)]
                    )
                );

                $stmts[] = new Return_($collectionVar);
            } else {
                $stmts[] = new Return_(
                    new New_(
                        $dto
                    )
                );
            }
        }

        return new File(
            $dPath, new Namespace_(
            new Name("Webpractik\Bitrixgen\UseCase"),
            [
                new Class_(
                    new Identifier($dName),
                    [
                        'implements' => [
                            new Name\FullyQualified("Webpractik\Bitrixgen\Interfaces\I" . $dName),
                        ],
                        'stmts'      => [
                            new ClassMethod(
                                new Identifier('process'),
                                [
                                    'flags'      => Modifiers::PUBLIC,
                                    'params'     => $params,
                                    'returnType' => count($returnTypes) == 1 ? $returnType : new UnionType($returnType),
                                    'stmts'      => $stmts,
                                ]
                            ),
                        ],
                    ]
                ),
            ]
        ),
            'client'
        );
    }

    private static function ifIsModelArray(string $v): bool
    {
        return str_contains($v, '[]') && str_contains($v, '\\Model\\');
    }
}
