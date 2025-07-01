<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guessers;

use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess as BaseClassGuess;
use Jane\Component\OpenApi3\Guesser\OpenApiSchema\SchemaGuesser as JaneSchemaGuesser;
use Jane\Component\OpenApi3\JsonSchema\Model\Schema;
use Webpractik\Bitrixapigen\Internal\BetterNaming;

class SchemaGuesser extends JaneSchemaGuesser
{
    /**
     * @param Schema $object
     */
    protected function createClassGuess($object, $reference, $name, $extensions): BaseClassGuess
    {
        $betterName = BetterNaming::getClassName($reference, $name);

        return parent::createClassGuess($object, $reference, $betterName, $extensions);
    }
}
