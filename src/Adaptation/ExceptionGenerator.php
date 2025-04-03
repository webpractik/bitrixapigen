<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\OpenApiCommon\Generator\ExceptionGenerator as JaneExceptionGenerator;
use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\File;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;

class ExceptionGenerator extends JaneExceptionGenerator
{
    public function createBaseExceptions(Context $context): void
    {
        parent::createBaseExceptions($context);

        $schema = $context->getCurrentSchema();
        $namespace = $schema->getNamespace() . '\\Exception';
        $dir = $schema->getDirectory() . '/Exception';

        // === Генерация класса BitrixFormatException ===

        $class = new Stmt\Class_(
            'BitrixFormatException',
            [
                'flags' => 0,
                'extends' => new Name('RuntimeException'),
                'implements' => [new Name('ApiException')],
                'stmts' => [],
            ]
        );

        // Метод static from(Throwable $e): self
        $fromMethod = new Stmt\ClassMethod('from', [
            'flags' => Modifiers::PUBLIC | Modifiers::STATIC,
            'params' => [
                new Param(new Expr\Variable('e'), null, new Name('Throwable')),
            ],
            'returnType' => new Name('self'),
            'stmts' => [
                new Stmt\Return_(
                    new Expr\New_(new Name('self'), [
                        new Node\Arg(new Expr\MethodCall(new Expr\Variable('e'), 'getMessage')),
                        new Node\Arg(new Expr\MethodCall(new Expr\Variable('e'), 'getCode')),
                        new Node\Arg(new Expr\Variable('e')),
                    ])
                ),
            ],
        ]);

        $class->stmts[] = $fromMethod;

        // Пространство имён с use-ами
        $namespaceNode = new Stmt\Namespace_(new Name($namespace), [
            new Stmt\Use_([new Stmt\UseUse(new Name('RuntimeException'))]),
            new Stmt\Use_([new Stmt\UseUse(new Name('Throwable'))]),
            $class,
        ]);

        $schema->addFile(new File($dir . '/BitrixFormatException.php', $namespaceNode, 'Exception'));
    }
}
