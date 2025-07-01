<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use DateTimeInterface;
use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Guesser\ChainGuesser;
use Jane\Component\OpenApi3\Guesser\OpenApiSchema\AnyOfReferencefGuesser;
use Jane\Component\OpenApi3\Guesser\OpenApiSchema\OpenApiGuesser;
use Jane\Component\OpenApi3\Guesser\OpenApiSchema\SecurityGuesser;
use Jane\Component\OpenApi3\JsonSchema\Model\Schema;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\AdditionalPropertiesGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\AllOfGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\ArrayGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\CustomStringFormatGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\DateGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\DateTimeGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\ItemsGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\MultipleGuesser;
use Jane\Component\OpenApiCommon\Guesser\OpenApiSchema\SimpleTypeGuesser;
use Symfony\Component\Serializer\SerializerInterface;
use Webpractik\Bitrixapigen\Adaptation\Guessers\ReferenceGuesser;
use Webpractik\Bitrixapigen\Adaptation\Guessers\SchemaGuesser;

class GuesserFactory
{
    public static function create(SerializerInterface $serializer, array $options = []): ChainGuesser
    {
        $naming               = new Naming();
        $dateFormat           = $options['full-date-format'] ?? 'Y-m-d';
        $outputDateTimeFormat = $options['date-format'] ?? DateTimeInterface::RFC3339;
        $inputDateTimeFormat  = $options['date-input-format'] ?? null;
        $datePreferInterface  = $options['date-prefer-interface'] ?? null;

        $customStringFormatMapping = $options['custom-string-format-mapping'] ?? [];

        $chainGuesser = new ChainGuesser();
        $chainGuesser->addGuesser(new SecurityGuesser());
        $chainGuesser->addGuesser(new CustomStringFormatGuesser(Schema::class, $customStringFormatMapping));
        $chainGuesser->addGuesser(new DateGuesser(Schema::class, $dateFormat, $datePreferInterface));
        $chainGuesser->addGuesser(new DateTimeGuesser(Schema::class, $outputDateTimeFormat, $inputDateTimeFormat, $datePreferInterface));
        $chainGuesser->addGuesser(new ReferenceGuesser($serializer, Schema::class));
        $chainGuesser->addGuesser(new OpenApiGuesser($serializer));
        $chainGuesser->addGuesser(new SchemaGuesser($naming, $serializer));
        $chainGuesser->addGuesser(new AdditionalPropertiesGuesser(Schema::class));
        $chainGuesser->addGuesser(new AllOfGuesser($serializer, $naming, Schema::class));
        $chainGuesser->addGuesser(new AnyOfReferencefGuesser($serializer, $naming, Schema::class));
        $chainGuesser->addGuesser(new ArrayGuesser(Schema::class));
        $chainGuesser->addGuesser(new ItemsGuesser(Schema::class));
        $chainGuesser->addGuesser(new SimpleTypeGuesser(Schema::class));
        $chainGuesser->addGuesser(new MultipleGuesser(Schema::class));

        return $chainGuesser;
    }
}
