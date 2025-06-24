<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guesser\Validator\Array_;

use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess;
use Jane\Component\JsonSchema\Guesser\Guess\Property;
use Jane\Component\JsonSchema\Guesser\Validator\ObjectCheckTrait;
use Jane\Component\JsonSchema\Guesser\Validator\ValidatorGuess;
use Jane\Component\JsonSchema\Guesser\Validator\ValidatorInterface;
use Jane\Component\JsonSchema\JsonSchema\Model\JsonSchema;
use Jane\Component\JsonSchema\Registry\Registry;

class CollectionOfObjectsValidator implements ValidatorInterface
{
    use ObjectCheckTrait;

    public function __construct(protected Registry $registry)
    {
    }

    public function supports($object): bool
    {
        return $this->checkObject($object) && (\is_array($object->getType()) ? \in_array('array', $object->getType()) : 'array' === $object->getType());
    }

    /**
     * @param JsonSchema          $object
     * @param ClassGuess|Property $guess
     */
    public function guess($object, string $name, $guess): void
    {
        $classGuess = method_exists($object->getItems(), 'getMergedUri') ? $this->registry->getClass((string)$object->getItems()->getMergedUri()) : null;
        if ($classGuess === null) {
            return;
        }

        $className           = $classGuess->getName();
        $constraintClassName = $className . 'CollectionConstraint';
        $reference           = (string)$object->getItems()?->getMergedUri();
        $guess->addValidatorGuess(new ValidatorGuess(
            $constraintClassName,
            [
            ],
            $name,
            $reference
        ));
    }
}
