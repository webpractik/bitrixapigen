<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;

use const DIRECTORY_SEPARATOR;

class JsonResponseBoilerplateSchema
{
    public static function generate(string $dirPath): File
    {
        $className = 'JsonResponse';
        $namespace = 'Webpractik\\Bitrixgen\\Response';
        $code      = <<<PHP
<?php

namespace $namespace;

use Throwable;
use Bitrix\\Main\\Engine\\Response\\Json;

class $className extends Json
{
    public function __construct(mixed \$data = null, int \$status = 200)
    {
        parent::__construct(\$data);
        \$this->setStatus(\$status);
    }

    public static function success(mixed \$data = null, int \$status = 200): self
    {
        return new self(\$data, \$status);
    }

    public static function error(string \$message, int \$status): self
    {
        return new self(['message' => \$message], \$status);
    }

    public static function errorValidation(string \$message, array \$errors = []): self
    {
        return new self(['message' => \$message, 'errors' => \$errors], 422);
    }

    public static function fromException(Throwable \$e): self
    {
        \$status = (\$e->getCode() >= 100 && \$e->getCode() <= 599)
            ? \$e->getCode()
            : 400;

        return self::error(\$e->getMessage(), \$status);
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast    = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($dirPath . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'abstract');
    }
}
