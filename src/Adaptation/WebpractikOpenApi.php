<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Generator\ValidatorGenerator;
use Webpractik\Bitrixapigen\Adaptation\GeneratorFactory;
use Jane\Component\OpenApi3\Guesser\OpenApiSchema\GuesserFactory;
use Jane\Component\OpenApi3\SchemaParser\SchemaParser;
use Jane\Component\OpenApi3\WhitelistedSchema;
use Jane\Component\OpenApiCommon\Generator\NormalizerGenerator;
use Jane\Component\OpenApiCommon\Generator\RuntimeGenerator;
use Jane\Component\OpenApiCommon\JaneOpenApi as CommonJaneOpenApi;
use PhpParser\ParserFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webpractik\Bitrixapigen\Internal\BitrixModuleGenerator;

class WebpractikOpenApi extends CommonJaneOpenApi
{
    protected const OBJECT_NORMALIZER_CLASS = \Jane\Component\OpenApi3\JsonSchema\Normalizer\JaneObjectNormalizer::class;
    protected const WHITELIST_FETCH_CLASS = WhitelistedSchema::class;

    protected static function create(array $options = []): CommonJaneOpenApi
    {
        $serializer = self::buildSerializer();

        return new self(
            SchemaParser::class,
            GuesserFactory::create($serializer, $options),
            $options['strict'] ?? true
        );
    }

    protected static function generators(DenormalizerInterface $denormalizer, array $options = []): \Generator
    {
        $naming = new Naming();
        $parser = (new ParserFactory())->createForHostVersion();

        yield new ModelGenerator($naming, $parser);
        yield GeneratorFactory::build($denormalizer, $options['endpoint-generator'] ?: UseCaseGenerator::class);
    }
}
