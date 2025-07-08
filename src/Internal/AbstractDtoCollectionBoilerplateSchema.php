<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;

use const DIRECTORY_SEPARATOR;

/**
 * Generate AbstractDtoCollection file
 */
class AbstractDtoCollectionBoilerplateSchema
{
    public static function generate(string $path, string $namespace, string $className): File
    {
        $code = <<<PHP
<?php

namespace $namespace;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Webpractik\\Bitrixgen\\Dto\\AbstractDto;
use InvalidArgumentException;

abstract class $className extends AbstractCollection
{
    abstract public function getItemType(): string;

    public function add(mixed \$item): void
    {
        \$itemType = \$this->getItemType();
        if (class_exists(\$itemType)) {
            if (!(\$item instanceof \$itemType)) {
                throw new InvalidArgumentException('Неверный тип объекта. Ожидался: ' . \$itemType);
            }
        } else {
            \$checkFuncion = 'is_' . \$itemType;
            if (!\$checkFuncion(\$item)) {
                throw new InvalidArgumentException('Неверный тип объекта. Ожидался: ' . \$itemType);
            }
        }
        \$this->items[] = \$item;
    }

    public function get(int \$index): mixed
    {
        return \$this->items[\$index] ?? null;
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast    = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($path . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'abstract');
    }
}
