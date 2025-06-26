<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\JsonSchema\Generator\ModelGenerator as BaseModelGenerator;
use Jane\Component\JsonSchema\Guesser\Guess\ClassGuess as BaseClassGuess;
use Jane\Component\JsonSchema\Guesser\Guess\Property;
use Jane\Component\JsonSchema\Registry\Schema;
use Jane\Component\OpenApiCommon\Generator\Model\ClassGenerator;
use Jane\Component\OpenApiCommon\Guesser\Guess\ClassGuess;
use Jane\Component\OpenApiCommon\Guesser\Guess\ParentClass;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\UseItem;
use Webpractik\Bitrixapigen\Internal\AbstractCollectionBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\AbstractDtoBoilerplateSchema;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Stmt\Use_;
use Webpractik\Bitrixapigen\Internal\AbstractDtoCollectionBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\BitrixFileNormalizerBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\UploadedFileCollectionBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;

use const DIRECTORY_SEPARATOR;

class ModelGenerator extends BaseModelGenerator
{
    use ClassGenerator;
    use ModelPropertyGenerator;

    protected function doCreateClassMethods(BaseClassGuess $classGuess, Property $property, string $namespace, bool $required): array
    {
        $methods = [];
        $methods[] = $this->createGetter($property, $namespace, $required);
        $methods[] = $this->createSetter($property, $namespace, $required, $classGuess instanceof ParentClass ? false : true);

        return $methods;
    }

    public function generate(Schema $schema, string $className, Context $context): void
    {
        $namespace = $schema->getNamespace() . '\\Dto';
        $dtoDirPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto';
        $schema->addFile(AbstractDtoBoilerplateSchema::generate($dtoDirPath, $namespace, 'AbstractDto'));

        $collectionNamespace = $schema->getNamespace() . '\\Dto\\Collection';
        $collectionDirPath = $dtoDirPath . DIRECTORY_SEPARATOR . 'Collection';
        $schema->addFile(AbstractCollectionBoilerplateSchema::generate($collectionDirPath, $collectionNamespace, 'AbstractCollection'));
        $schema->addFile(AbstractDtoCollectionBoilerplateSchema::generate($collectionDirPath, $collectionNamespace, 'AbstractDtoCollection'));
        $schema->addFile(UploadedFileCollectionBoilerplateSchema::generate($collectionDirPath . DIRECTORY_SEPARATOR . 'Files', $collectionNamespace . '\\Files', 'UploadedFileCollection'));
        $schema->addFile(BitrixFileNormalizerBoilerplateSchema::generate(
            $schema->getDirectory() . DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR .'Normalizer',
            $schema->getNamespace() . '\\Http\Normalizer',
            'BitrixFileNormalizer'
        ));

        foreach ($schema->getClasses() as $class) {
            $dtoNameResolver = DtoNameResolver::createByModelName($class->getName());

            $collectionClassName = $dtoNameResolver->getCollectionClassName();
            $collection = $this->createCollectionClass($class, $schema);
            $collectionPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto' . DIRECTORY_SEPARATOR . 'Collection' . DIRECTORY_SEPARATOR . $collectionClassName . '.php';

            $schema->addFile(new File($collectionPath, $collection, 'collection'));

            $readonlyDtoClassName = $class->getName();
            $readonlyDtoCollection = $this->createReadonlyDtoClass($class, $schema);
            $readonlyDtoCollectionPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto' . DIRECTORY_SEPARATOR . $readonlyDtoClassName . 'Dto' . '.php';

            $schema->addFile(new File($readonlyDtoCollectionPath, $readonlyDtoCollection, self::FILE_TYPE_MODEL));
        }
    }

    protected function doCreateModel(BaseClassGuess $class, array $properties, array $methods): Stmt\Class_
    {
        $extends = null;
        if ($class instanceof ClassGuess
            && $class->getParentClass() instanceof ParentClass) {
            $extends = $this->getNaming()->getClassName($class->getParentClass()->getName());
        }

        $modelName = $class->getName();
        $classModel = $this->createModel(
            $modelName,
            $properties,
            [],
            \count($class->getExtensionsType()) > 0,
            $class->isDeprecated(),
            $extends
        );

        return $classModel;
    }

    protected function createModel(string $name, array $properties, array $methods, bool $hasExtensions = false, bool $deprecated = false, ?string $extends = null): Stmt\Class_
    {
        $classExtends = null;
        if (null !== $extends) {
            $classExtends = new Name($extends);
        } elseif ($hasExtensions) {
            $classExtends = new Name('AbstractDto');
        }

        $attributes = [];
        if ($deprecated) {
            $attributes['comments'] = [new Doc(<<<EOD
/**
 *
 * @deprecated
 */
EOD
            )];
        }

        $dtoNameResolver = DtoNameResolver::createByModelName($name);

        return new Stmt\Class_(
            $this->getNaming()->getClassName($dtoNameResolver->getDtoClassName()),
            [
                'stmts' => array_merge($properties, $methods),
                'extends' => $classExtends,
            ],
            $attributes
        );
    }

    protected function createCollectionClass(BaseClassGuess $class, Schema $schema): Namespace_
    {
        $modelClassName = $this->getNaming()->getClassName($class->getName());
        $dtoNameResolver = DtoNameResolver::createByModelName($modelClassName);
        $collectionClassName = $dtoNameResolver->getCollectionClassName();

        $dtoFqcn = $dtoNameResolver->getDtoFullClassName();

        $useDto = new Use_([new UseItem(new Name($dtoFqcn))]);

        $getItemTypeMethod = new ClassMethod('getItemType', [
            'flags' => Modifiers::PUBLIC,
            'returnType' => new Name('string'),
            'stmts' => [
                new Stmt\Return_(new ClassConstFetch(new Name($dtoNameResolver->getDtoClassName()), 'class'))
            ]
        ]);

        $classNode = new Class_($collectionClassName, [
            'extends' => new Name('AbstractDtoCollection'),
            'stmts' => [$getItemTypeMethod]
        ]);

        return new Namespace_(
            new Name($schema->getNamespace() . '\\Dto\\Collection'),
            [$useDto, $classNode]
        );
    }

    private function createReadonlyDtoClass(BaseClassGuess $class, Schema $schema): Namespace_
    {
        $dtoClassName = $this->getNaming()->getClassName($class->getName()) . 'Dto';
        $readonlyClassName = $dtoClassName;

        $paramsObjects = [];

        foreach ($class->getLocalProperties() as $property) {
            $type = new Identifier($property->getType()->getName());
            if ($property->isNullable()) {
                $type = new NullableType($type);
            }
            $paramsObjects[] = new Param(
                var: new Variable($property->getName()),
                type: $type,
                flags: Modifiers::PUBLIC | Modifiers::READONLY);
        }

        $__construct = new ClassMethod('__construct', [
            'params' => $paramsObjects,
            'flags' => Modifiers::PUBLIC,
        ]);

        $classNode = new Class_($readonlyClassName, [
            'extends' => new Name('AbstractDto'),
            'stmts' => [$__construct]
        ]);

        return new Namespace_(
            new Name($schema->getNamespace() . '\\Dto'),
            [$classNode]
        );
    }
}
