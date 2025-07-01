<?php

namespace Webpractik\Bitrixapigen\Adaptation\Generator;

use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\JsonSchema\Generator\GeneratorInterface;
use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess;
use Jane\Component\JsonSchema\Guesser\Validator\Array_\MaxItemsValidator;
use Jane\Component\JsonSchema\Guesser\Validator\Array_\MinItemsValidator;
use Jane\Component\JsonSchema\Guesser\Validator\Array_\UniqueItemsValidator;
use Jane\Component\JsonSchema\Guesser\Validator\ValidatorGuess;
use Jane\Component\JsonSchema\Registry\Schema;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use Webpractik\Bitrixapigen\Adaptation\Guesser\Validator\Array_\CollectionOfObjectsValidator;

/**
 * Класс создает классы Constraint для requestBody, которое не описано отдельной схемой.
 * В данный момент обрабатывает только массив, в том числе массив объектов, в остальных случаях нужно описывать отдельной схемой.
 */
class OperationValidatorGenerator implements GeneratorInterface
{
    public const FILE_TYPE_VALIDATOR = 'validator';

    /** @var Naming */
    protected $naming;

    public function __construct(Naming $naming)
    {
        $this->naming = $naming;
    }

    public function generate(Schema $schema, string $className, Context $context): void
    {
        $namespace = $schema->getNamespace() . '\\Validator';

        $validators   = [];
        $validators[] = new MaxItemsValidator();
        $validators[] = new MinItemsValidator();
        $validators[] = new UniqueItemsValidator();
        $validators[] = new CollectionOfObjectsValidator($context->getRegistry());

        foreach ($schema->getOperations() as $operationSchema) {
            $operation   = $operationSchema->getOperation();
            $requestBody = $operation?->getRequestBody();

            $constraints = [];
            if ($requestBody === null) {
                continue;
            }
            foreach ($requestBody->getContent() as $mime => $media) {
                if ('application/json' !== $mime) {
                    continue;
                }

                $object = $media->getSchema();
                if (!method_exists($object, 'getType') || !(\is_array($object->getType()) ? \in_array('array', $object->getType()) : 'array' === $object->getType())) {
                    continue;
                }

                $tempClassGuess = new ClassGuess($object, $operationSchema->getReference(), $operation->getOperationId());
                foreach ($validators as $validator) {
                    if ($validator->supports($object)) {
                        $validator->guess($object, $operation->getOperationId(), $tempClassGuess);
                    }
                }
                $validatorGuesses = $tempClassGuess->getValidatorGuesses();
                foreach ($validatorGuesses as $validatorGuess) {
                    if (null !== $validatorGuess->getClassReference()) {
                        $validatorGuess->setConstraintClass(sprintf('%s\%s', $namespace, $validatorGuess->getConstraintClass()));
                    }
                    $constraints[] = new Expr\ArrayItem($this->generateConstraint($validatorGuess));
                }
                $className = ucfirst($operation->getOperationId() . 'OperationConstraint');

                $optionsVariable = new Expr\Variable('options');
                $class           = new Node\Stmt\Class_(
                    $className,
                    [
                        'stmts'   => [
                            new Node\Stmt\ClassMethod(
                                'getConstraints',
                                [
                                    'type'       => Modifiers::PROTECTED,
                                    'params'     => [new Node\Param($optionsVariable)],
                                    'stmts'      => [
                                        new Node\Stmt\Return_(new Expr\Array_($constraints)),
                                    ],
                                    'returnType' => new Node\Identifier('array'),
                                ]
                            ),
                        ],
                        'extends' => new Node\Name('\\Symfony\\Component\\Validator\\Constraints\\Compound'),
                    ]
                );

                $namespaceStmt = new Node\Stmt\Namespace_(new Node\Name($namespace), [$class]);
                $schema->addFile(new File($schema->getDirectory() . '/Validator/' . $className . '.php', $namespaceStmt, self::FILE_TYPE_VALIDATOR));
            }
        }
    }

    protected function generateConstraint(ValidatorGuess $guess): Expr
    {
        $args = [];
        foreach ($guess->getArguments() as $argName => $argument) {
            $value = null;
            if (\is_array($argument)) {
                $values = [];
                foreach ($argument as $item) {
                    $values[] = new Expr\ArrayItem(new Scalar\String_($item));
                }
                $value = new Expr\Array_($values);
            } elseif (\is_string($argument)) {
                $value = new Scalar\String_($argument);
            } elseif (\is_int($argument)) {
                $value = new Scalar\LNumber($argument);
            } elseif (\is_float($argument)) {
                $value = new Scalar\DNumber($argument);
            }

            if (null !== $value) {
                $args[] = new Expr\ArrayItem($value, new Scalar\String_($argName));
            }
        }

        return new Expr\New_(new Node\Name\FullyQualified($guess->getConstraintClass()), [
            new Node\Arg(new Expr\Array_($args)),
        ]);
    }
}
