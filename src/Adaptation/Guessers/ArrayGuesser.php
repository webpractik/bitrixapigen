<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guessers;

use Jane\Component\JsonSchema\Generator\Naming;
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
        $items = $object->getItems();
        if ($items instanceof Reference || is_a($items, $this->getSchemaClass())) {
            $this->chainGuesser->guessClass($items, $name . 'Item', $reference . '/items', $registry);
        }
    }
}
