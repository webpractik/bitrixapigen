<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guessers;

use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess;
use Jane\Component\JsonSchema\Registry\Registry;
use Jane\Component\JsonSchemaRuntime\Reference;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\ArrayGuesser as JaneArrayGuesser;

class ArrayGuesser extends JaneArrayGuesser
{
    protected Naming $naming;

    public function __construct(Naming $naming, string $schemaClass)
    {
        parent::__construct($schemaClass);

        $this->naming = $naming;
    }

    /**
     * {@inheritdoc}
     */
    public function guessClass($object, string $name, string $reference, Registry $registry): void
    {
        if (!$registry->hasClass($reference)) {
            $schema = $registry->getSchema($reference);
            if (null !== $schema) {
                $classGuess = $this->createClassGuess($object, $reference, $name);
                $schema->addClass($reference, $classGuess);
            }
        }

        $items = $object->getItems();
        if ($items instanceof Reference || is_a($items, $this->getSchemaClass())) {
            $this->chainGuesser->guessClass($items, $name . 'Item', $reference . '/items', $registry);
        }
    }

    private function createClassGuess($object, string $reference, string $name): ClassGuess
    {
        return new ClassGuess($object, $reference, $this->naming->getClassName($name), [], $object->getDeprecated());
    }
}
