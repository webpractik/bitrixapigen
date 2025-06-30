<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess as BaseClassGuess;
use Jane\Component\OpenApi3\Guesser\OpenApiSchema\SchemaGuesser as JaneSchemaGuesser;
use Jane\Component\OpenApi3\JsonSchema\Model\Schema;

class SchemaGuesser extends JaneSchemaGuesser
{
    /**
     * @param Schema $object
     */
    protected function createClassGuess($object, $reference, $name, $extensions): BaseClassGuess
    {
        $betterName = $this->getBetterName($reference, $name);

        return parent::createClassGuess($object, $reference, $betterName, $extensions);
    }

    private function getBetterName($reference, $name): string
    {
        $nameParts = [];
        $isMultiFileSchema = preg_match('/Response\d{3}(?<tail>.*)/', $name, $nameParts);
        $referenceParts = [];
        $isReferenceParsed = preg_match('~/?(?<fileName>[^/.]+).(json|ya?ml)#.*/(?<schemaName>[^/]+)$~', $reference, $referenceParts);
        if ($isMultiFileSchema && $isReferenceParsed) {
            $isNestedSchema = $nameParts['tail'] && $nameParts['tail'] !== $referenceParts['schemaName'];
            $schemaName = $isNestedSchema ? $nameParts['tail'] : $referenceParts['schemaName'];

            return ucfirst($referenceParts['fileName']) . ucfirst($schemaName);
        }

        return $name;
    }
}
