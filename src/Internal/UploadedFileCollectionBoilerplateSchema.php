<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;

use const DIRECTORY_SEPARATOR;

/**
 * Generate UploadedFileCollection file
 */
class UploadedFileCollectionBoilerplateSchema
{
    public static function generate(string $path, string $namespace, string $className): File
    {
        $code = <<<PHP
<?php

namespace $namespace;

use Psr\Http\Message\UploadedFileInterface;
use Webpractik\Bitrixgen\Dto\Collection\AbstractCollection;

class $className extends AbstractCollection
{
    public function getItemType(): string
    {
        return UploadedFileInterface::class;
    }

    public function add(UploadedFileInterface \$item): void
    {
        \$this->items[] = \$item;
    }

    public function get(int \$index): ?UploadedFileInterface
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
