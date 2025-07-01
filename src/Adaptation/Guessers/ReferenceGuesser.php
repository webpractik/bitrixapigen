<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guessers;

use Jane\Component\JsonSchema\Registry\Registry;
use Jane\Component\JsonSchemaRuntime\Reference;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\ReferenceGuesser as JaneReferenceGuesser;

class ReferenceGuesser extends JaneReferenceGuesser
{
    /**
     * {@inheritdoc}
     *
     * @param Reference $object
     */
    public function guessClass($object, string $name, string $reference, Registry $registry): void
    {
        $chainReference = (string) $object->getMergedUri()->withFragment('') === (string) $object->getMergedUri() ? $object->getMergedUri()->withFragment('') . '#' : (string) $object->getMergedUri();
        if ($registry->hasClass($chainReference)) {
            return;
        }

        $mergedReference = (string) $object->getMergedUri();

        if (null === $registry->getSchema($mergedReference)) {
            $schema = $registry->getSchema((string) $object->getOriginUri());
            /** @noinspection NullPointerExceptionInspection Скопировано из Jane */
            $schema->addReference((string) $object->getMergedUri()->withFragment(''));
        }

        $this->chainGuesser->guessClass(
            $this->resolve($object, $this->getSchemaClass()),
            $name,
            $chainReference,
            $registry
        );
    }
}
