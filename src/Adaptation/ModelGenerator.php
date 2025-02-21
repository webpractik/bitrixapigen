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
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;

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
            $classExtends = new Name('\ArrayObject');
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
}
