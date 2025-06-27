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
        $isMultiFileSchema = preg_match('/Response\d{3}$/', $name);
        $referenceParts = [];
        $isReferenceParsed = preg_match('~/?(?<fileName>[^/.]+).(json|ya?ml)#.*/(?<schemaName>[^/]+)$~', $reference, $referenceParts);
        if ($isMultiFileSchema && $isReferenceParsed) {
            return ucfirst($referenceParts['fileName']) . ucfirst($referenceParts['schemaName']);
        }

        return $name;
    }
}
