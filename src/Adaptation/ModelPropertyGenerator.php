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

        if ((null !== $default && \is_scalar($default)) || (Type::TYPE_ARRAY === $property->getType()->getTypeHint($namespace)?->toString() && \is_array($default))) {
            $propertyStmt->default = $this->getDefaultAsExpr($default)->expr;
        }
        $type = $this->getPropertyType($property, $namespace);
        return new Stmt\Property(Modifiers::PUBLIC, [
            $propertyStmt,
        ], [
            'comments' => [$this->createPropertyDoc($property, $namespace, $strict)],
        ],
            $type);
    }

    protected function createPropertyDoc(Property $property, $namespace, bool $strict): Doc
    {
        $docTypeHint = $property->getType()->getDocTypeHint($namespace);

        if (($property->getObject() instanceof Schema) &&
            $property->getObject()->getType() === 'array'
            && $property->getObject()->getItems() instanceof Schema
            && $property->getObject()->getItems()->getType() === 'string' && $property->getObject()->getItems()->getFormat() === 'binary') {
            $docTypeHint = '\\Webpractik\\Bitrixgen\\Dto\\Collection\\Files\\UploadedFileCollection';
        } elseif ($property->getObject() instanceof Schema && $property->getObject()->getType() === 'string' && $property->getObject()->getFormat() === 'binary') {
            $docTypeHint = str_replace('string', '\\Psr\\Http\\Message\\UploadedFileInterface', $docTypeHint);
        } elseif (str_contains($docTypeHint, '\\Model\\')) {
            $docTypeHint = str_replace('\\Model\\', '\\Dto\\', $docTypeHint);
            if (preg_match('#^list<\\\\Webpractik\\\\Bitrixgen\\\\Dto\\\\([^>]+)>$#', $docTypeHint, $matches)) {
                $className = $matches[1];
                $collectionClassName = '\\Webpractik\\Bitrixgen\\Dto\\Collection\\'.$className . 'Collection';
                $docTypeHint = preg_replace('#^list<\\\\Webpractik\\\\Bitrixgen\\\\Dto\\\\([^>]+)>$#', $collectionClassName, $docTypeHint);
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

    private function getDefaultAsExpr($value): Stmt\Expression
    {
        return $this->parser->parse('<?php ' . var_export($value, true) . ';')[0];
    }

    protected function getPropertyType(Property $property, string $namespace): null|Identifier|Name
    {
        $phpType = $property->getType()->getTypeHint($namespace)?->toString();

        if ($phpType === null) {
            return null; // Если нет типа, оставляем без него
        }

        if($property->getName() === 'photos') {
//            echo '<pre>';
//            var_export([
//                $property->getObject()->getType(),
//                $property->getObject()->getItems()->getType(),
//                $property->getObject()->getItems()->getFormat()
//            ]);
//            echo '</pre>'; die();
        }

        if(($property->getObject() instanceof Schema) &&
        $property->getObject()->getType() === 'array'
            && $property->getObject()->getItems() instanceof Schema
            && $property->getObject()->getItems()->getType() === 'string' && $property->getObject()->getItems()->getFormat() === 'binary') {
            $phpType = '\\Webpractik\\Bitrixgen\\Dto\\Collection\\Files\\UploadedFileCollection';
            return new Name($phpType);
        }

        if($property->getObject() instanceof Schema && $property->getObject()->getType() === 'string' && $property->getObject()->getFormat() === 'binary') {
            $phpType = str_replace('string', '\\Psr\\Http\\Message\\UploadedFileInterface', $phpType);
            return new Name($phpType);
//            echo '<pre>';
//            var_export($propertyName);
//            var_export([$propertyName, $property->getObject()->getType12(), $property->getObject()->getFormat()]);
//            echo '</pre>';
//            die();
        }

        $docTypeHint = $property->getType()->getDocTypeHint($namespace);
        if ($phpType === 'array' && str_contains($docTypeHint, '\\Model\\') && preg_match('#^list<\\\\Webpractik\\\\Bitrixgen\\\\Model\\\\([^>]+)>$#', $docTypeHint, $matches)) {
            $className = $matches[1];
            $collectionClass = '\\Webpractik\\Bitrixgen\\Dto\\Collection\\'.$className . 'Collection';
            return new Name($collectionClass);
        }

        // Простые скалярные типы
        $scalarTypes = ['string', 'int', 'float', 'bool', 'array'];

        if (in_array($phpType, $scalarTypes, true)) {
            return new Identifier($phpType); // ✅ Возвращаем Identifier для скаляров
        }

        // Если это объект (например, \DateTime)
        if ($phpType === '\DateTime') {
            return new Name($phpType);
        }

        $phpType = str_replace('\\Model\\', '\\Dto\\', $phpType);

        // Если это кастомный объект (например, Dto\Pet)
        return new Name($phpType);
    }
}
