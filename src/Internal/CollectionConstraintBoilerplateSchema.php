<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;
use const DIRECTORY_SEPARATOR;

class CollectionConstraintBoilerplateSchema
{
    public static function generate(string $className, string $dtoConstraintClassName, string $dirPath): File
    {
        $namespace = 'Webpractik\\Bitrixgen\\Validator';
        $code = <<<PHP
<?php

namespace $namespace;

class $className extends \Symfony\Component\Validator\Constraints\Compound
{
    protected function getConstraints(\$options): array
    {
        return [
            new \Symfony\Component\Validator\Constraints\Type(['type' => 'array']),
            new \Symfony\Component\Validator\Constraints\All([
                'constraints' => [
                    new $dtoConstraintClassName(),
                ],
            ]),
        ];
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($dirPath . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'constraint');
    }
}
