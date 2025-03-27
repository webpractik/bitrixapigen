<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;
use const DIRECTORY_SEPARATOR;

/**
 * Generate AbstractCollection file
 */
class AbstractCollectionBoilerplateSchema
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

abstract class $className implements JsonSerializable, IteratorAggregate, Countable
{
    protected array \$items = [];

    public function add(AbstractDto \$item): void
    {
        \$itemType = \$this->getItemType();
        if (!(\$item instanceof \$itemType)) {
            throw new InvalidArgumentException('Неверный тип объекта. Ожидался: ' . \$this->getItemType());
        }
        \$this->items[] = \$item;
    }

    abstract public function getItemType(): string;

    public function getItems(): array
    {
        return \$this->items;
    }

    public function isEmpty(): bool
    {
        return empty(\$this->items);
    }

    public function count(): int
    {
        return count(\$this->items);
    }

    public function get(int \$index): ?AbstractDto
    {
        return \$this->items[\$index] ?? null;
    }

    public function remove(int \$index): void
    {
        if (isset(\$this->items[\$index])) {
            unset(\$this->items[\$index]);
        }
    }

    public function jsonSerialize(): array
    {
        return \$this->items;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator(\$this->items);
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($path . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'collection');
    }
}
