<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\JsonSchema\Generator\GeneratorInterface;
use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Registry\Schema;
use PhpParser\ParserFactory;

class ModuleContextGenerator implements GeneratorInterface
{
    public function __construct(private readonly string $locale)
    {
    }

    public function generate(Schema $schema, string $className, Context $context): void
    {
        $namespace = 'Webpractik\\Bitrixgen';
        $className = 'ModuleContext';
        $locale    = $this->locale;
        $code      = <<<PHP
<?php

namespace $namespace;

class $className
{
    private static ?self \$instance = null;

    private function __construct(
        private readonly string \$locale,
    )
    {
    }

    public static function get(): self
    {
        if (!self::\$instance) {
            self::\$instance = new self(
            '$locale'
        );
        }

        return self::\$instance;
    }

    public function getLocale(): string
    {
        return \$this->locale;
    }
}

PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast    = $parser->parse($code);

        $namespaceNode = reset($ast);

        $schema->addFile(new File($schema->getDirectory() . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'context'));
    }
}
