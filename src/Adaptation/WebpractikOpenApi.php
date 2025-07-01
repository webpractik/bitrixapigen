<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Generator;
use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\OpenApi3\JsonSchema\Normalizer\JaneObjectNormalizer;
use Jane\Component\JsonSchema\Generator\RuntimeGenerator;
use Jane\Component\JsonSchema\Generator\ValidatorGenerator;
use Webpractik\Bitrixapigen\Adaptation\Generator\OperationValidatorGenerator;
use Webpractik\Bitrixapigen\Adaptation\Guesser\Validator\ChainValidatorFactory;
use Jane\Component\JsonSchema\Registry\Registry;
use Jane\Component\OpenApiCommon\Registry\Schema;
use Jane\Component\OpenApi3\SchemaParser\SchemaParser;
use Jane\Component\OpenApi3\WhitelistedSchema;
use Jane\Component\OpenApiCommon\JaneOpenApi as CommonJaneOpenApi;
use PhpParser\ParserFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class WebpractikOpenApi extends CommonJaneOpenApi
{
    protected const OBJECT_NORMALIZER_CLASS = JaneObjectNormalizer::class;
    protected const WHITELIST_FETCH_CLASS   = WhitelistedSchema::class;

    protected static function create(array $options = []): CommonJaneOpenApi
    {
        $serializer = self::buildSerializer();

        return new self(
            SchemaParser::class,
            GuesserFactory::create($serializer, $options),
            $options['strict'] ?? true
        );
    }

    protected static function generators(DenormalizerInterface $denormalizer, array $options = []): Generator
    {
        $naming = new Naming();
        $parser = (new ParserFactory())->createForHostVersion();

        yield new ModuleContextGenerator(GenerationContext::get()->getLocale());
        yield new ValidatorFactoryGenerator();
        yield new ModelGenerator($naming, $parser);
        yield GeneratorFactory::build($denormalizer, $options['endpoint-generator'] ?: UseCaseGenerator::class);
        yield new RuntimeGenerator($naming, $parser);
        if ($options['validation'] ?? false) {
            yield new ValidatorGenerator($naming);
            yield new OperationValidatorGenerator($naming);
        }
    }

    public function createContext(Registry $registry): Context
    {
        /** @var Schema[] $schemas */
        $schemas = array_values($registry->getSchemas());

        foreach ($schemas as $schema) {
            $openApiSpec = $this->schemaParser->parseSchema($schema->getOrigin());
            $this->chainGuesser->guessClass($openApiSpec, $schema->getRootName(), $schema->getOrigin() . '#', $registry);
            $schema->setParsed($openApiSpec);
        }

        $chainValidator = ChainValidatorFactory::create($this->naming, $registry, $this->serializer);

        foreach ($schemas as $schema) {
            foreach ($schema->getClasses() as $class) {
                $properties = $this->chainGuesser->guessProperties($class->getObject(), $schema->getRootName(), $class->getReference(), $registry);

                $names = [];
                foreach ($properties as $property) {
                    $deduplicatedName = $this->naming->getDeduplicatedName($property->getName(), $names);

                    $property->setAccessorName($deduplicatedName);
                    $property->setPhpName($this->naming->getPropertyName($deduplicatedName));

                    $property->setType($this->chainGuesser->guessType($property->getObject(), $property->getName(), $property->getReference(), $registry));
                }

                $class->setProperties($properties);
                $schema->addClassRelations($class);

                $extensionsTypes = [];
                foreach ($class->getExtensionsObject() as $pattern => $extensionData) {
                    $extensionsTypes[$pattern] = $this->chainGuesser->guessType($extensionData['object'], $class->getName(), $extensionData['reference'], $registry);
                }
                $class->setExtensionsType($extensionsTypes);

                $chainValidator->guess($class->getObject(), $class->getName(), $class);
            }

            $this->hydrateDiscriminatedClasses($schema, $registry);

            // when we have a whitelist, we want to have only needed models to be generated
            if (\count($registry->getWhitelistedPaths() ?? []) > 0) {
                $this->whitelistFetch($schema, $registry);
            }
        }

        return new Context($registry, $this->strict);
    }
}
