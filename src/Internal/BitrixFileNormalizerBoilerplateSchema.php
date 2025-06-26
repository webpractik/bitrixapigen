<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\File;
use PhpParser\ParserFactory;

use const DIRECTORY_SEPARATOR;

/**
 * Generate BitrixFileNormalizer file
 */
class BitrixFileNormalizerBoilerplateSchema
{
    public static function generate(string $path, string $namespace, string $className): File
    {
        $code = <<<PHP
<?php

namespace $namespace;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;
use Webpractik\Bitrixgen\Dto\Collection\Files\UploadedFileCollection;

class $className
{
    public function normalize(array \$files): array
    {
        \$normalizedFiles = [];

        foreach (\$files as \$field => \$value) {
            if (\$this->isSingleFile(\$value)) {
                \$normalizedFiles[\$field] = \$this->createPsr7UploadedFile(\$value);
                continue;
            }

            \$collection = new UploadedFileCollection();

            \$restructedFiles = \$this->normalizeFileInputStructure(\$value);

            foreach (\$restructedFiles as \$file) {
                if (\$this->isSingleFile(\$file)) {
                    \$uploaded = \$this->createPsr7UploadedFile(\$file);
                    if (\$uploaded !== null) {
                        \$collection->add(\$uploaded);
                    }
                }
            }

            \$normalizedFiles[\$field] = \$collection;
        }

        return \$normalizedFiles;
    }

    private function isSingleFile(array \$file): bool
    {
        return isset(\$file['tmp_name'], \$file['error'], \$file['size']) && is_string(\$file['tmp_name']);
    }

    private function createPsr7UploadedFile(array \$file): ?UploadedFileInterface
    {
        if (
            \$file['error'] !== \UPLOAD_ERR_OK ||
            !is_uploaded_file(\$file['tmp_name']) ||
            !is_readable(\$file['tmp_name'])
        ) {
            return null;
        }

        \$factory = new Psr17Factory();
        \$stream = \$factory->createStreamFromFile(\$file['tmp_name'], 'r');

        return new UploadedFile(
            \$stream,
            (int) \$file['size'],
            (int) \$file['error'],
            \$file['name'] ?? null,
            \$file['type'] ?? null
        );
    }

    private function normalizeFileInputStructure(array \$fileInput): array
    {
        \$result = [];

        if (!isset(\$fileInput['name']) || !is_array(\$fileInput['name'])) {
            // Это одиночный файл
            return [\$fileInput];
        }

        foreach (\$fileInput['name'] as \$index => \$name) {
            \$result[] = [
                'name' => \$name,
                'full_path' => \$fileInput['full_path'][\$index] ?? null,
                'type' => \$fileInput['type'][\$index] ?? null,
                'tmp_name' => \$fileInput['tmp_name'][\$index] ?? null,
                'error' => \$fileInput['error'][\$index] ?? null,
                'size' => \$fileInput['size'][\$index] ?? null,
            ];
        }

        return \$result;
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast    = $parser->parse($code);

        $namespaceNode = reset($ast);

        return new File($path . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'abstract');
    }
}
