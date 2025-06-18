<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guesser\Validator\Object_;

use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess;
use Jane\Component\JsonSchema\Guesser\Guess\Property;
use Jane\Component\JsonSchema\Guesser\GuesserResolverTrait;
use Jane\Component\JsonSchema\Guesser\Validator\ObjectCheckTrait;
use Jane\Component\JsonSchema\Guesser\Validator\ValidatorGuess;
use Jane\Component\JsonSchema\Guesser\Validator\ValidatorInterface;
use Jane\Component\JsonSchema\JsonSchema\Model\JsonSchema;
use Jane\Component\JsonSchema\Registry\Registry;
use Jane\Component\JsonSchemaRuntime\Reference;
use Symfony\Component\Serializer\SerializerInterface;

class SubPropertyArrayOfObjectsValidator implements ValidatorInterface
{
    use GuesserResolverTrait;
    use ObjectCheckTrait;

    /** @var Naming */
    private $naming;

    /** @var Registry */
    private $registry;

    public function __construct(Naming $naming, Registry $registry, SerializerInterface $denormalizer)
    {
        $this->naming     = $naming;
        $this->registry   = $registry;
        $this->serializer = $denormalizer;
    }

    public function supports($object): bool
    {
        return $this->checkObject($object) && (\is_array($object->getType()) ? \in_array('object', $object->getType()) : 'object' === $object->getType());
    }

    /**
     * @param JsonSchema          $object
     * @param ClassGuess|Property $guess
     */
    public function guess($object, string $name, $guess): void
    {
        if (strpos($guess->getReference(), 'properties') !== false) {
            return; // we don't want to guess on properties here, only on classes
        }

        foreach ($object->getProperties() ?? [] as $localName => $property) {
            if ($property instanceof Reference) {
                continue;
            }

            if (!(\is_array($property->getType()) ? \in_array('array', $property->getType()) : 'array' === $property->getType())) {
                continue;
            }

            $classGuess = method_exists($property->getItems(), 'getMergedUri') ? $this->registry->getClass((string)$property->getItems()->getMergedUri()) : null;
            if ($classGuess === null) {
                continue;
            }

            $className           = $classGuess->getName();
            $constraintClassName = $className . 'CollectionConstraint';

            $guess->addValidatorGuess(new ValidatorGuess(
                $constraintClassName,
                [
                ],
                $localName
            ));
        }
    }
}
