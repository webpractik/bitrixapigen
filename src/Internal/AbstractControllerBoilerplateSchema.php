<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;
use const DIRECTORY_SEPARATOR;

/**
 * Generate AbstractController file
 */
class AbstractControllerBoilerplateSchema
{
    public static function generate(string $dirPath, string $namespace, string $className): File
    {
        $code = <<<PHP
<?php

namespace $namespace;

use Bitrix\Main\Engine\Controller;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use Webpractik\Bitrixgen\Dto\AbstractDto;
use Webpractik\Bitrixgen\Dto\Collection\AbstractCollection;

class $className extends Controller
{
    protected const int RECURSION_DEPTH_LIMIT = 20;
    
    protected function getDefaultPreFilters(): array
    {
        return [];
    }

    protected function initializeDtoCollection(AbstractCollection \$collection, array \$data, int \$depth = 0): void
    {
        \$this->checkRecursionLimit(\$depth);

        foreach (\$data as \$item) {
            \$dtoObject = new (\$collection->getItemType())();
            \$this->initializeDto(\$dtoObject, \$item);
            \$collection->add(\$dtoObject);
        }
    }

    // Рекурсивная функция для обработки вложенных DTO и массивов DTO
    protected function initializeDto(AbstractDto \$dto, array \$data, int \$depth = 0): void
    {
        \$this->checkRecursionLimit(\$depth);

        \$reflectionClass = new ReflectionClass(get_class(\$dto));
        \$properties = \$reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach (\$properties as \$property) {
            \$propertyName = \$property->getName();

            if (array_key_exists(\$propertyName, \$data)) {
                \$value = \$data[\$propertyName];

                if (\$this->isCollection(\$property->getType()->getName())) {
                    \$collection = new (\$property->getType()->getName())();
                    \$this->initializeDtoCollection(\$collection, \$value);
                    \$dto->{\$propertyName} = \$collection;
                } elseif (\$this->isDto(\$property->getType()->getName())) {
                    \$dtoObject = new (\$property->getType()->getName())();
                    \$this->initializeDto(\$dtoObject, \$value); // Рекурсивно инициализируем DTO
                    \$dto->{\$propertyName} = \$dtoObject;
                } else {
                    \$dto->{\$propertyName} = \$value;
                }
            }
        }
    }

    protected function isDto(string \$typeName): bool
    {
        return is_subclass_of(\$typeName, AbstractDto::class);
    }

    protected function isCollection(string \$typeName): bool
    {
        return is_subclass_of(\$typeName, AbstractCollection::class);
    }

    private function checkRecursionLimit(int \$depth): void
    {
        if (\$depth > self::RECURSION_DEPTH_LIMIT) {
            throw new RuntimeException('Инициализация DTO достигла лимита на глубину рекурсии (' . self::RECURSION_DEPTH_LIMIT . ').');
        }
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($dirPath . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'abstract');
    }
}
