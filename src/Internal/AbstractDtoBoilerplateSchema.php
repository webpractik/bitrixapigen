<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;

/**
 * Generate AbstractCollection file
 */
class AbstractDtoBoilerplateSchema
{
    public static function generate(string $path, string $namespace, string $className): File
    {
        $code = <<<PHP
<?php

namespace $namespace;

use ArrayObject;
use JsonSerializable;

abstract class $className extends ArrayObject implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return get_object_vars(\$this);
    }
}

PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($path . '/' . $className . '.php', $namespaceNode, 'abstract');
    }
}
