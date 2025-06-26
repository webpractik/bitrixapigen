<?php

namespace Webpractik\Bitrixapigen\Internal;

use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Expr;

class BoilerplateSchema
{
    public static function getSecondOpIfForSettings($operation): Stmt\If_
    {
        return new Stmt\If_(
            new Expr\BooleanNot(
                new Expr\MethodCall(
                    new Expr\Variable("serviceLocator"),
                    new Identifier("has"),
                    [
                        new Node\Arg(
                            new Scalar\String_("webpractik.bitrixgen.".$operation)
                        )
                    ]
                )
            ),
            [
                'stmts' => [
                    new Stmt\Expression(
                        new Expr\Assign(
                            new Expr\ArrayDimFetch(
                                new Expr\Variable("serviceValue"),
                                new Scalar\String_("webpractik.bitrixgen.".$operation)
                            ),
                            new Expr\Array_(
                                [
                                    new Node\ArrayItem(
                                        new Expr\ClassConstFetch(
                                            new Name\FullyQualified("Webpractik\Bitrixgen\UseCase\\".ucfirst($operation)),
                                            new Identifier("class")
                                        ),
                                        new Scalar\String_("className"),
                                    )
                                ]
                            )
                        )
                    )
                ]
            ]
        );
    }
    public static function getFirstOpIfForSettings($operation): Stmt\If_
    {
        return new Stmt\If_(
            new Expr\MethodCall(
                new Expr\Variable("serviceLocator"),
                new Identifier("has"),
                [
                    new Node\Arg(
                        new Scalar\String_("webpractik.bitrixgen.".$operation)
                    )
                ]
            ),
            [
                'stmts' => [
                    new Stmt\If_(
                        new Expr\BooleanNot(
                            new Expr\FuncCall(
                                new Name("in_array")
                                ,
                                [
                                    new Node\Arg(
                                        new Scalar\String_("\Webpractik\Bitrixgen\UseCase\I".ucfirst($operation))
                                    ),
                                    new Node\Arg(
                                        new Expr\FuncCall(
                                            new Name("class_implements"),
                                            [
                                                new Node\Arg(
                                                    new Expr\MethodCall(
                                                        new Expr\Variable("serviceLocator"),
                                                        new Identifier("get"),
                                                        [
                                                            new Node\Arg(
                                                                new Scalar\String_("webpractik.bitrixgen.".$operation)
                                                            )
                                                        ]
                                                    )
                                                )
                                            ]
                                        )
                                    )
                                ]
                            )
                        ),
                        [
                            'stmts' => [
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\ArrayDimFetch(
                                            new Expr\Variable("serviceValue"),
                                            new Scalar\String_("webpractik.bitrixgen.".$operation)
                                        ),
                                        new Expr\Array_(
                                            [
                                                new Node\ArrayItem(
                                                    new Expr\ClassConstFetch(
                                                        new Name\FullyQualified("Webpractik\Bitrixgen\UseCase\\".ucfirst($operation)),
                                                        new Identifier("class")
                                                    )
                                                )
                                            ]
                                        )
                                    )
                                )
                            ]
                        ]
                    )
                ]
            ]
        );
    }

    public static function getUse($path): Use_
    {
        return new Stmt\Use_(
            [
                new Node\UseItem(
                    new Name($path)
                )
            ]
        );
    }

    public static function getMethodForRouter(string $method, $route, $className, $methodName): Stmt\Expression
    {
        return new Stmt\Expression(
            new Expr\MethodCall(
                new Expr\Variable('configurator'),
                new Node\Identifier(strtolower($method)), // метод гет\пост
                [
                    new Node\Arg(
                        new Scalar\String_($route) // роут
                    ),
                    new Node\Arg(
                        new Expr\Array_(
                            [
                                new Node\ArrayItem(
                                    new Expr\ClassConstFetch(
                                        new Name($className), // имя класса
                                        new Node\Identifier('class')
                                    )
                                ),
                                new Node\ArrayItem(
                                    new Scalar\String_($methodName) // имя метода
                                ),
                            ]
                        )
                    )
                ]
            )
        );
    }

    public static function getRouterBody(): Stmt\Return_
    {
        return new Stmt\Return_(new Expr\Closure([
            'attrGroups' => [],
            'static' => true,
            'byRef' => false,
            'params' => [new Param(
                new Expr\Variable('configurator'), null, new Name('RoutingConfigurator'),
                false, false, [], 0)
            ],
            'uses' => [],
            'returnType' => null,
            'stmts' => []
        ]));
    }

    public static function getInstallFileBody(): Stmt\Class_
    {
        return new Stmt\Class_(
            new Node\Identifier('webpractik_bitrixgen'),
            [
                'attrGroups' => [],
                'flags' => 0,
                'extends' => new Name('CModule'),
                'implements' => [],
                'stmts' => [
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('MODULE_ID')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('MODULE_VERSION')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('MODULE_VERSION_DATE')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('MODULE_NAME')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('MODULE_DESCRIPTION')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('MODULE_GROUP_RIGHTS')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('PARTNER_NAME')]
                    ),
                    new Stmt\Property(
                        Modifiers::PUBLIC,
                        [new Stmt\PropertyProperty('PARTNER_URI')]
                    ),

                    new Stmt\ClassMethod(
                        new Node\Identifier('__construct'),
                        [
                            'attrGroups' => [],
                            'flags' => 1,
                            'byRef' => false,
                            'params' => [],
                            'returnType' => null,
                            'stmts' => [
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('MODULE_VERSION'),
                                        ),
                                        new Scalar\String_(self::getModuleVersion())
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('MODULE_VERSION_DATE'),
                                        ),
                                        new Scalar\String_(self::getModuleVersionDate())
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('MODULE_ID'),
                                        ),
                                        new Scalar\String_('webpractik.bitrixgen')
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('MODULE_NAME'),
                                        ),
                                        new Scalar\String_('Bitrixgen')
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('MODULE_DESCRIPTION'),
                                        ),
                                        new Scalar\String_('Модуль серверной части, сгенерированной по OA файлу')
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('MODULE_GROUP_RIGHTS'),
                                        ),
                                        new Scalar\String_('N')
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('PARTNER_NAME'),
                                        ),
                                        new Scalar\String_('Webpractik')
                                    )
                                ),
                                new Stmt\Expression(
                                    new Expr\Assign(
                                        new Expr\PropertyFetch(
                                            new Expr\Variable('this'),
                                            new Node\Identifier('PARTNER_URI'),
                                        ),
                                        new Scalar\String_('https://webpractik.ru')
                                    )
                                ),
                            ]
                        ]
                    ),
                    new Stmt\ClassMethod(
                        new Node\Identifier('doInstall'),
                        [
                            'attrGroups' => [],
                            'flags' => 1,
                            'byRef' => false,
                            'params' => [],
                            'returnType' => new Node\Identifier('void'),
                            'stmts' => [
                                new Stmt\Expression(
                                    new Expr\FuncCall(
                                        new Name('RegisterModule'),
                                        [
                                            new Node\Arg(
                                                new Expr\PropertyFetch(
                                                    new Expr\Variable('this'),
                                                    new Node\Identifier('MODULE_ID'),
                                                )
                                            )
                                        ]
                                    )
                                ),
                            ]
                        ]
                    ),
                    new Stmt\ClassMethod(
                        new Node\Identifier('doUninstall'),
                        [
                            'attrGroups' => [],
                            'flags' => 1,
                            'byRef' => false,
                            'params' => [],
                            'returnType' => new Node\Identifier('void'),
                            'stmts' => [
                                new Stmt\Expression(
                                    new Expr\FuncCall(
                                        new Name('UnRegisterModule'),
                                        [
                                            new Node\Arg(
                                                new Expr\PropertyFetch(
                                                    new Expr\Variable('this'),
                                                    new Node\Identifier('MODULE_ID'),
                                                )
                                            )
                                        ]
                                    )
                                ),
                            ]
                        ]
                    )
                ]
            ]
        );
    }

    private static function getModuleVersion(): string
    {
        $packageFilename = getcwd() . '/composer.json';
        $packageSettingsRaw = file_get_contents($packageFilename);
        $packageSettings = json_decode($packageSettingsRaw, true);

        return $packageSettings['version'];
    }

    private static function getModuleVersionDate(): string
    {
        $packageFilename = getcwd() . '/composer.json';
        $modificationTime = filemtime($packageFilename);

        return date('Y-m-d', $modificationTime);
    }
}
