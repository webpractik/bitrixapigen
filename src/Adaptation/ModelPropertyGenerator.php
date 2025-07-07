<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\Naming;
use Jane\Component\JsonSchema\Guesser\Guess\Property;
use Jane\Component\JsonSchema\Guesser\Guess\Type;
use Jane\Component\OpenApi3\JsonSchema\Model\Schema;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\Parser;
use RuntimeException;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;

use function is_array;
use function is_scalar;

trait ModelPropertyGenerator
{
    /**
     * The naming service.
     */
    abstract protected function getNaming(): Naming;

    /**
     * The PHP Parser.
     */
    abstract protected function getParser(): Parser;

    protected function createProperty(Property $property, string $namespace, $default = null, bool $strict = true): Stmt
    {
        $propertyName = $property->getName();
        $propertyStmt = new Stmt\PropertyProperty($propertyName);

        if (null === $default) {
            $default = $property->getDefault();
        }

        if ((null !== $default && is_scalar($default)) || (Type::TYPE_ARRAY === $property->getType()->getTypeHint($namespace)?->toString() && is_array($default))) {
            $propertyStmt->default = $this->getDefaultAsExpr($default)->expr;
        }
        $type = $this->getPropertyType($property, $namespace, $strict);

        return new Stmt\Property(Modifiers::PUBLIC, [
            $propertyStmt,
        ], [
            'comments' => [$this->createPropertyDoc($property, $namespace, $strict)],
        ],
            $type);
    }

    /**
     * @throws RuntimeException
     * @deprecated Вызова этого метода не ожидается
     */
    protected function createPropertyDoc(Property $property, $namespace, bool $strict): Doc
    {
        throw new RuntimeException('Deprecated method');

        $docTypeHint = $property->getType()->getDocTypeHint($namespace);

        if (($property->getObject() instanceof Schema) &&
            $property->getObject()->getType() === 'array'
            && $property->getObject()->getItems() instanceof Schema
            && $property->getObject()->getItems()->getType() === 'string' && $property->getObject()->getItems()->getFormat() === 'binary') {
            $docTypeHint = '\\' . DtoNameResolver::getCollectionNamespace() . '\\Files\\UploadedFileCollection';
        } elseif ($property->getObject() instanceof Schema && $property->getObject()->getType() === 'string' && $property->getObject()->getFormat() === 'binary') {
            $docTypeHint = str_replace('string', '\\Psr\\Http\\Message\\UploadedFileInterface', $docTypeHint);
        } elseif (str_contains($docTypeHint, '\\Model\\')) {
            if (preg_match('#^list<\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)>$#', $docTypeHint, $matches)) {
                $modelName               = $matches[1];
                $dtoNameResolver         = DtoNameResolver::createByModelName($modelName);
                $collectionFullClassName = $dtoNameResolver->getCollectionFullClassName();
                $docTypeHint             = preg_replace('#^list<\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)>$#', '\\' . $collectionFullClassName, $docTypeHint);
            } elseif (preg_match('#^\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)$#', $docTypeHint, $matches)) {
                $modelName        = $matches[1];
                $dtoNameResolver  = DtoNameResolver::createByModelName($modelName);
                $dtoFullClassName = $dtoNameResolver->getDtoFullClassName();
                $docTypeHint      = preg_replace('#^\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)$#', '\\' . $dtoFullClassName, $docTypeHint);
            }
        }
        if ((!$strict || $property->isNullable()) && strpos($docTypeHint, 'null') === false) {
            $docTypeHint .= '|null';
        }

        $description = sprintf(<<<EOD
/**
 * %s
 *

EOD
            , $property->getDescription());

        if ($property->isDeprecated()) {
            $description .= <<<EOD
 * @deprecated
 *

EOD;
        }

        $description .= sprintf(<<<EOD
 * @var %s
 */
EOD
            , $docTypeHint);

        return new Doc($description);
    }

    /**
     * @throws RuntimeException
     * @deprecated Вызова этого метода не ожидается
     */
    protected function getPropertyType(Property $property, string $namespace, bool $strict): null|Identifier|Name
    {
        throw new RuntimeException('Deprecated method');

        $phpType = $property->getType()->getTypeHint($namespace)?->toString();

        if ($phpType === null) {
            return null; // Если нет типа, оставляем без него
        }

        if (($property->getObject() instanceof Schema) &&
            $property->getObject()->getType() === 'array'
            && $property->getObject()->getItems() instanceof Schema
            && $property->getObject()->getItems()->getType() === 'string' && $property->getObject()->getItems()->getFormat() === 'binary') {
            $phpType = '\\' . DtoNameResolver::getCollectionNamespace() . '\\Files\\UploadedFileCollection';

            return new Name($phpType);
        }

        if ($property->getObject() instanceof Schema && $property->getObject()->getType() === 'string' && $property->getObject()->getFormat() === 'binary') {
            $phpType = str_replace('string', '\\Psr\\Http\\Message\\UploadedFileInterface', $phpType);

            return new Name($phpType);
        }

        $docTypeHint = $property->getType()->getDocTypeHint($namespace);

        $canBeNull = (!$strict || $property->isNullable()) && (strpos($docTypeHint, 'null') === false);

        // Простые скалярные типы
        $scalarTypes = ['string', 'int', 'float', 'bool', 'array'];

        if (in_array($phpType, $scalarTypes, true)) {
            return new Identifier(($canBeNull ? '?' : '') . $phpType); // ✅ Возвращаем Identifier для скаляров
        }

        // Если это объект (например, \DateTime)
        if ($phpType === '\DateTime') {
            return new Name(($canBeNull ? '?' : '') . $phpType);
        }

        if ($phpType === 'array' && str_contains($docTypeHint, '\\Model\\') && preg_match('#^list<\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)>$#', $docTypeHint, $matches)) {
            $className       = $matches[1];
            $collectionClass = ($canBeNull ? '?' : '') . '\\Webpractik\\Bitrixgen\\Dto\\Collection\\' . $className . 'Collection';

            return new Name($collectionClass);
        }

        if (preg_match('#^\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)$#', $phpType, $matches)) {
            $modelName       = $matches[1];
            $dtoNameResolver = DtoNameResolver::createByModelName($modelName);
            $phpType         = '\\' . $dtoNameResolver->getDtoFullClassName();
        }

        // Если это кастомный объект (например, Dto\Pet)
        return new Name(($canBeNull ? '?' : '') . $phpType);
    }

    private function getDefaultAsExpr($value): Stmt\Expression
    {
        return $this->parser->parse('<?php ' . var_export($value, true) . ';')[0];
    }
}
