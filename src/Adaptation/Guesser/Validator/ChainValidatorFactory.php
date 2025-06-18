<?php

namespace Webpractik\Bitrixapigen\Adaptation\Guesser\Validator;

use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Guesser\Validator\ValidatorInterface;
use Jane\Component\JsonSchema\Registry\Registry;
use Symfony\Component\Serializer\SerializerInterface;
use Jane\Component\JsonSchema\Guesser\Validator\ChainValidatorFactory as BaseChainValidatorFactory;
use Webpractik\Bitrixapigen\Adaptation\Guesser\Validator\Object_\SubPropertyArrayOfObjectsValidator;

class ChainValidatorFactory extends BaseChainValidatorFactory
{
    public static function create(Naming $naming, Registry $registry, SerializerInterface $denormalizer): ValidatorInterface
    {
        $chainValidator = parent::create($naming, $registry, $denormalizer);

        $chainValidator->addValidator(new SubPropertyArrayOfObjectsValidator($naming, $registry, $denormalizer));

        return $chainValidator;
    }
}
