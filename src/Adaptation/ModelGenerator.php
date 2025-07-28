<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\JsonSchema\Generator\ModelGenerator as BaseModelGenerator;
use Jane\Component\JsonSchema\Guesser\Guess\ArrayType;
use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess;
use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess as BaseClassGuess;
use Jane\Component\JsonSchema\Guesser\Guess\ObjectType;
use Jane\Component\JsonSchema\Guesser\Guess\Property;
use Jane\Component\JsonSchema\Registry\Schema;
use Jane\Component\JsonSchemaRuntime\Reference;
use Jane\Component\OpenApi3\JsonSchema\Model\Schema as ModelSchema;
use Jane\Component\OpenApiCommon\Generator\Model\ClassGenerator;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use RuntimeException;
use Webpractik\Bitrixapigen\Dto\DtoParameterSettings;
use Webpractik\Bitrixapigen\Dto\UseSettings;
use Webpractik\Bitrixapigen\Internal\AbstractCollectionBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\AbstractDtoBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\AbstractDtoCollectionBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\BetterNaming;
use Webpractik\Bitrixapigen\Internal\BitrixFileNormalizerBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\CollectionConstraintBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\UploadedFileCollectionBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\Utils\Aliases;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;

use const DIRECTORY_SEPARATOR;

class ModelGenerator extends BaseModelGenerator
{
    use ClassGenerator;
    use ModelPropertyGenerator;

    public function generate(Schema $schema, string $className, Context $context): void
    {
        $namespace  = $schema->getNamespace() . '\\Dto';
        $dtoDirPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto';
        $schema->addFile(AbstractDtoBoilerplateSchema::generate($dtoDirPath, $namespace, 'AbstractDto'));

        $collectionNamespace = $schema->getNamespace() . '\\Dto\\Collection';
        $collectionDirPath   = $dtoDirPath . DIRECTORY_SEPARATOR . 'Collection';

        $schema->addFile(AbstractCollectionBoilerplateSchema::generate($collectionDirPath, $collectionNamespace, 'AbstractCollection'));
        $schema->addFile(AbstractDtoCollectionBoilerplateSchema::generate($collectionDirPath, $collectionNamespace, 'AbstractDtoCollection'));
        $schema->addFile(UploadedFileCollectionBoilerplateSchema::generate($collectionDirPath . DIRECTORY_SEPARATOR . 'Files', $collectionNamespace . '\\Files', 'UploadedFileCollection'));
        $schema->addFile(BitrixFileNormalizerBoilerplateSchema::generate(
            $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Normalizer',
            $schema->getNamespace() . '\\Http\Normalizer',
            'BitrixFileNormalizer'
        ));

        foreach ($schema->getClasses() as $class) {
            $betterClassName      = BetterNaming::getClassName($class->getReference(), $class->getName());
            $subNamespaceParts    = BetterNaming::getSubNamespaceParts($class->getReference(), $schema->getOrigin());
            $dtoNameResolver      = DtoNameResolver::createByModelName($betterClassName, $subNamespaceParts);
            $readonlyDtoNamespace = $this->createReadonlyDtoClass($class, $schema);

            $subNamespace    = implode(DIRECTORY_SEPARATOR, $dtoNameResolver->getSubNamespaceParts());
            $readonlyDtoPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto' . ($subNamespace ? DIRECTORY_SEPARATOR . $subNamespace : '') . DIRECTORY_SEPARATOR . $dtoNameResolver->getDtoClassName() . '.php';

            $schema->addFile(new File($readonlyDtoPath, $readonlyDtoNamespace, self::FILE_TYPE_MODEL));
        }
    }

    /**
     * @inheritDoc
     * @throws RuntimeException
     * @deprecated Вызова этого метода не ожидается
     */
    protected function createModel(string $name, array $properties, array $methods, bool $hasExtensions = false, bool $deprecated = false, ?string $extends = null): Stmt\Class_
    {
        throw new RuntimeException('Deprecated method');

        $classExtends = null;
        if (null !== $extends) {
            $classExtends = new Name($extends);
        } elseif ($hasExtensions) {
            $classExtends = new Name('AbstractDto');
        }

        $attributes = [];
        if ($deprecated) {
            $attributes['comments'] = [
                new Doc(<<<EOD
/**
 *
 * @deprecated
 */
EOD
                ),
            ];
        }

        $dtoNameResolver = DtoNameResolver::createByModelName($name);

        return new Stmt\Class_(
            $this->getNaming()->getClassName($dtoNameResolver->getDtoClassName()),
            [
                'stmts'   => array_merge($properties, $methods),
                'extends' => $classExtends,
            ],
            $attributes
        );
    }

    protected function createCollectionClass(BaseClassGuess $class, Schema $schema): Namespace_
    {
        $itemNameResolver = $this->getCollectionItemNameResolver($class, $schema);

        if (null === $itemNameResolver) {
            $useDto = null;
            $return = $this->getBuiltinCollectionItemType($class);
        } else {
            $useDto = new Use_([new UseItem(new Name($itemNameResolver->getDtoFullClassName()))]);
            $return = new ClassConstFetch(new Name($itemNameResolver->getDtoClassName()), 'class');
        }

        $getItemTypeMethod = new ClassMethod('getItemType', [
            'flags'      => Modifiers::PUBLIC,
            'returnType' => new Name('string'),
            'stmts'      => [
                new Stmt\Return_($return),
            ],
        ]);

        $nameResolver        = $this->getNameResolver($class, $schema->getOrigin());
        $collectionClassName = $nameResolver->getCollectionClassName();

        $classNode = new Class_($collectionClassName, [
            'extends' => new Name('AbstractDtoCollection'),
            'stmts'   => [$getItemTypeMethod],
        ]);

        return new Namespace_(
            new Name($nameResolver->getCollectionNamespace()),
            $useDto ? [$useDto, $classNode] : [$classNode]
        );
    }

    private function createReadonlyDtoClass(BaseClassGuess $class, Schema $schema): Namespace_
    {
        $this->createDtoParameterCollections($class, $schema);

        $betterClassName   = BetterNaming::getClassName($class->getReference(), $class->getName());
        $subNamespaceParts = BetterNaming::getSubNamespaceParts($class->getReference(), $schema->getOrigin());
        $dtoNameResolver   = DtoNameResolver::createByModelName($betterClassName, $subNamespaceParts);

        $parameters     = $this->getDtoParameters($class->getLocalProperties(), $schema);
        $allUseSettings = $this->getAllDtoUseSettings($dtoNameResolver->getDtoFullClassName(), $parameters);

        $__construct = new ClassMethod('__construct', [
            'params' => $this->getDtoConstructorParameters($parameters, $allUseSettings),
            'flags'  => Modifiers::PUBLIC,
        ]);

        $classNode = new Class_($dtoNameResolver->getDtoClassName(), [
            'extends' => new Name('AbstractDto'),
            'stmts'   => [$__construct],
        ]);

        $uses = $this->getDtoUses($allUseSettings, $dtoNameResolver->getDtoFullClassName());

        return new Namespace_(
            new Name($dtoNameResolver->getDtoNamespace()),
            [...$uses, $classNode]
        );
    }

    private function createDtoParameterCollections(BaseClassGuess $class, Schema $schema): void
    {
        $validatorDirPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Validator';

        foreach ($class->getLocalProperties() as $property) {
            $propertyType = $property->getType();
            if ($propertyType instanceof ArrayType) {
                $class = $this->getArrayItemClass($property->getObject(), $schema);
                if (null !== $class) {
                    $nameResolver        = $this->getNameResolver($class, $schema->getOrigin());
                    $subNamespace        = implode(DIRECTORY_SEPARATOR, $nameResolver->getSubNamespaceParts());
                    $collectionClassName = $nameResolver->getCollectionClassName();
                    $collectionPath      = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto' . DIRECTORY_SEPARATOR . 'Collection' . ($subNamespace ? DIRECTORY_SEPARATOR . $subNamespace : '') . DIRECTORY_SEPARATOR . $collectionClassName . '.php';
                    $collection          = $this->createCollectionClass($class, $schema);

                    $schema->addFile(new File($collectionPath, $collection, 'collection'));

                    $schema->addFile(CollectionConstraintBoilerplateSchema::generate($class->getName() . 'CollectionConstraint', $class->getName() . 'Constraint', $validatorDirPath));
                }
            }
        }
    }

    /**
     * @param Property[] $properties
     * @param Schema     $schema
     *
     * @return DtoParameterSettings[]
     */
    private function getDtoParameters(array $properties, Schema $schema): array
    {
        return array_map(function (Property $property) use ($schema) {
            $propertyType = $property->getType();

            $type          = new Identifier($propertyType->getName());
            $fullClassName = null;

            if ($propertyType instanceof ObjectType) {
                $type = new Identifier($propertyType->getClassName() . 'Dto');

                $object = $property->getObject();
                if ($object instanceof Reference) {
                    $subNamespaceParts = BetterNaming::getSubNamespaceParts($object->getMergedUri(), $schema->getOrigin());
                    $nameResolver      = DtoNameResolver::createByModelName($propertyType->getClassName(), $subNamespaceParts);

                    $fullClassName = $nameResolver->getDtoFullClassName();
                }
            } elseif ($propertyType instanceof ArrayType) {
                $itemClass = $this->getArrayItemClass($property->getObject(), $schema);
                if (null !== $itemClass) {
                    $nameResolver = $this->getNameResolver($itemClass, $schema->getOrigin());

                    $type          = new Identifier($nameResolver->getCollectionClassName());
                    $fullClassName = $nameResolver->getCollectionFullClassName();
                }
            }

            if ($property->isNullable()) {
                $type = new NullableType($type);
            }

            $name = BetterNaming::camelize($property->getName());

            return new DtoParameterSettings($name, $type, $fullClassName, $property->isNullable());
        }, $properties);
    }

    /**
     * @param string                 $dtoFullClassName
     * @param DtoParameterSettings[] $parameters
     *
     * @return UseSettings[]
     */
    private function getAllDtoUseSettings(string $dtoFullClassName, array $parameters): array
    {
        $fullClassNames = array_map(static function (DtoParameterSettings $parameter) {
            return $parameter->fullClassName;
        }, $parameters);
        $fullClassNames = array_filter($fullClassNames, static function ($fullClassName) {
            return null !== $fullClassName;
        });
        // Предотвращает импорт такого же класса без псевдонима
        $fullClassNames = [$dtoFullClassName, ...$fullClassNames];
        $fullClassNames = array_unique($fullClassNames);
        $fullClassNames = array_values($fullClassNames);

        $settings = array_map(static function ($fullClassName) use ($fullClassNames) {
            $alias = Aliases::getUseAlias($fullClassName, $fullClassNames);

            return new UseSettings($fullClassName, $alias);
        }, $fullClassNames);

        return array_filter($settings, static function (UseSettings $useSettings) use ($dtoFullClassName) {
            return $useSettings->fullClassName !== $dtoFullClassName;
        });
    }

    /**
     * @param DtoParameterSettings[] $parameters
     * @param UseSettings[]          $allUseSettings
     *
     * @return Param[]
     */
    private function getDtoConstructorParameters(array $parameters, array $allUseSettings): array
    {
        return array_map(function (DtoParameterSettings $parameter) use ($allUseSettings) {
            return new Param(
                var: new Variable($parameter->name),
                type: $this->getDtoConstructorParameterType($parameter, $allUseSettings),
                flags: Modifiers::PUBLIC | Modifiers::READONLY
            );
        }, $parameters);
    }

    /**
     * @param DtoParameterSettings $parameter
     * @param UseSettings[]        $allUseSettings
     *
     * @return Node|Identifier|NullableType
     */
    private function getDtoConstructorParameterType(DtoParameterSettings $parameter, array $allUseSettings): Node|Identifier|NullableType
    {
        $type                 = $parameter->type;
        $parameterUseSettings = $this->getParameterUseSettings($parameter, $allUseSettings);
        $alias                = $parameterUseSettings?->alias;
        if (null !== $alias) {
            $type = new Identifier($alias);
            if ($parameter->isNullable) {
                $type = new NullableType($type);
            }
        }

        return $type;
    }

    /**
     * @param DtoParameterSettings $parameter
     * @param UseSettings[]        $allUseSettings
     *
     * @return UseSettings|null
     */
    private function getParameterUseSettings(DtoParameterSettings $parameter, array $allUseSettings): UseSettings|null
    {
        if (null === $parameter->fullClassName) {
            return null;
        }

        return array_reduce($allUseSettings, static function ($carry, UseSettings $useSettings) use ($parameter) {
            return $carry ?? ($useSettings->fullClassName === $parameter->fullClassName ? $useSettings : null);
        });
    }

    /**
     * @param UseSettings[] $allUseSettings
     * @param string        $dtoFullClassName
     *
     * @return Use_[]
     */
    private function getDtoUses(array $allUseSettings, string $dtoFullClassName): array
    {
        $allUseSettingsWithoutSelfRecursion = array_filter($allUseSettings, static function (UseSettings $useSettings) use ($dtoFullClassName) {
            return $useSettings->fullClassName !== $dtoFullClassName;
        });

        return array_map(static function (UseSettings $useSettings) {
            $name = new Name($useSettings->fullClassName);

            return new Use_([new UseItem($name, $useSettings->alias)]);
        }, $allUseSettingsWithoutSelfRecursion);
    }

    private function getNameResolver(ClassGuess $class, string $schemaOrigin): DtoNameResolver
    {
        $baseName  = BetterNaming::getClassName($class->getReference(), $class->getName());
        $modelName = $this->getNaming()->getClassName($baseName);

        $subNamespaceParts = BetterNaming::getSubNamespaceParts($class->getReference(), $schemaOrigin);

        return DtoNameResolver::createByModelName($modelName, $subNamespaceParts);
    }

    private function getArrayItemClass(object $object, Schema $schema): ClassGuess|null
    {
        if ($object instanceof Reference) {
            return $schema->getClass($object->getMergedUri());
        }

        if ($object instanceof ModelSchema) {
            $items = $object->getItems();
            if ($items instanceof Reference) {
                return $schema->getClass($items->getMergedUri());
            }
        }

        return null;
    }

    private function getCollectionItemNameResolver(BaseClassGuess $class, Schema $schema): ?DtoNameResolver
    {
        $object = $class->getObject();
        if (!($object instanceof ModelSchema)) {
            throw new RuntimeException('Objects ' . get_class($object) . ' not supported in collections');
        }

        $items = $object->getItems();
        if ($items === null) {
            return $this->getNameResolver($class, $schema->getOrigin());
        }

        if ($items instanceof Reference) {
            $itemSchemaName    = BetterNaming::getSchemaName($items->getMergedUri());
            $betterClassName   = BetterNaming::getClassName($items->getMergedUri(), $itemSchemaName);
            $subNamespaceParts = BetterNaming::getSubNamespaceParts($items->getMergedUri(), $schema->getOrigin());

            return DtoNameResolver::createByModelName($betterClassName, $subNamespaceParts);
        }

        return null;
    }

    private function getBuiltinCollectionItemType(BaseClassGuess $class): Node\Expr
    {
        $object = $class->getObject();
        if (!($object instanceof ModelSchema)) {
            throw new RuntimeException('Objects ' . get_class($object) . ' not supported in collections');
        }

        $items = $object->getItems();
        if (!($items instanceof ModelSchema)) {
            throw new RuntimeException('Items ' . ($items === null ? 'null' : get_class($items)) . ' not supported in collections');
        }

        return new Node\Scalar\String_($items->getType());
    }
}
