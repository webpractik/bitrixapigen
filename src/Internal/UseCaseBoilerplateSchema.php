<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
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
                if (str_contains($typeName, 'Webpractik\Bitrixgen')) {
                    $params[] = new Param(
                        new Variable($m->var->name),
                        null,
                        new Name(str_replace("Model", "Dto", $typeName))
                    );
                    continue;
                }

            if (str_contains($typeName, 'array')) {
                $arElementType = (new OperationWrapper($operation))->getArrayItemType();
                if (str_contains($arElementType, '\\Dto\\')) {
                    $collectionClass = new Name(self::makeCollectionClassName($arElementType));
                    $params[] = new Param(
                        new Variable($m->var->name),
                        null,
                        $collectionClass
                    );
                }
                continue;
            }

                    $params[] = new Param(
                        new Variable($m->var->name),
                        null,
                        new Identifier($m->type?->name)
                    );
        }

        if ($isOctetStreamFile) {
            $params[] = new Param(
                new Variable('octetStreamRawContent'),
                null,
                new Identifier('string')
            );
        }

        $dto = "";
        $collectionClass = null;
        $returnType = [];
        if (count($returnTypes) == 1) {
            $v = $returnTypes[0];
            if ($returnTypes[0] == "null") {
                $returnType = new Identifier('void');
            } else {
                $dtoClassName = $v;
                if (self::ifIsDtoArray($dtoClassName)) {
                    $dtoClassName = str_replace('[]', '', $dtoClassName);
                    $collectionClass = new Name(self::makeCollectionClassName($dtoClassName));
                    $returnType = $collectionClass;
                } else {
                    $returnType = new Name($dtoClassName);
                }
                $dto = new Name($dtoClassName);
            }

        } else {
            foreach ($returnTypes as $v) {
                if ($v == "null") {
                    $returnType[] = new Identifier("null");
                } else {
                    $dtoClassName = $v;
                    if (self::ifIsDtoArray($dtoClassName)) {
                            $dtoClassName = str_replace('[]', '', $dtoClassName);
                            $collectionClass = new Name(self::makeCollectionClassName($dtoClassName));
                            $returnType[] = $collectionClass;
                    } else {
                        $returnType[] = new Name($dtoClassName);
                    }
                    $dto = new Name($dtoClassName);
                }
            }
        }

        $stmts = [];
        if ($dto !== "") {
            if ($collectionClass !== null) {
                $collectionVar = new Variable('collection');
                $stmts[] = new Expression(
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
                            new Name\FullyQualified("Webpractik\Bitrixgen\Interfaces\I" . $dName)
                        ],
                        'stmts' => [
                            new ClassMethod(
                                new Identifier('process'),
                                [
                                    'flags' => Modifiers::PUBLIC,
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

    private static function makeCollectionClassName(string $dtoClassName): string
    {
        return str_replace('\\Dto\\', '\\Dto\\Collection\\', $dtoClassName) . 'Collection';
    }

    private static function ifIsDtoArray(string $v): bool
    {
        return str_contains($v, '[]') && str_contains($v, '\\Dto\\');
    }
}
