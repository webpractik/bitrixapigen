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
use PhpParser\Node\Name;
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

        foreach ($schema->getClasses() as $class) {
            $properties = [];
            $methods = [];

            /** @var Property $property */
            foreach ($class->getLocalProperties() as $property) {
                $properties[] = $this->createProperty($property, $namespace, null, $context->isStrict());
                $methods = array_merge($methods, $this->doCreateClassMethods($class, $property, $namespace, $context->isStrict()));
            }

            $model = $this->doCreateModel($class, $properties, $methods);

            $namespaceStmt = new Stmt\Namespace_(new Name($namespace), [$model]);
            $schema->addFile(new File($schema->getDirectory() . '/Dto/' . $class->getName() . '.php', $namespaceStmt, self::FILE_TYPE_MODEL));

            $collectionClassName = $class->getName() . 'Collection';
            $collection = $this->createCollectionClass($class, $schema);
            $collectionPath = $schema->getDirectory() . DIRECTORY_SEPARATOR . 'Dto' . DIRECTORY_SEPARATOR . 'Collection' . DIRECTORY_SEPARATOR . $collectionClassName . '.php';

            $schema->addFile(new File($collectionPath, $collection, 'collection'));
        }
    }

    protected function doCreateModel(BaseClassGuess $class, array $properties, array $methods): Stmt\Class_
    {
        $extends = null;
        if ($class instanceof ClassGuess
            && $class->getParentClass() instanceof ParentClass) {
            $extends = $this->getNaming()->getClassName($class->getParentClass()->getName());
        }


        $classModel = $this->createModel(
            $class->getName(),
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

        return new Stmt\Class_(
            $this->getNaming()->getClassName($name),
            [
                'stmts' => array_merge($properties, $methods),
                'extends' => $classExtends,
            ],
            $attributes
        );
    }

    protected function createCollectionClass(BaseClassGuess $class, Schema $schema): Namespace_
    {
        $dtoClassName = $this->getNaming()->getClassName($class->getName());
        $collectionClassName = $dtoClassName . 'Collection';

        $dtoFqcn = $schema->getNamespace() . '\\Dto\\' . $dtoClassName;

        $useDto = new Use_([new UseItem(new Name($dtoFqcn))]);

        $getItemTypeMethod = new ClassMethod('getItemType', [
            'flags' => Modifiers::PUBLIC,
            'returnType' => new Name('string'),
            'stmts' => [
                new Stmt\Return_(new ClassConstFetch(new Name($dtoClassName), 'class'))
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
}
