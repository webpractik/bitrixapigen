<?php

namespace Webpractik\Bitrixapigen\Internal\Wrappers;

use Jane\Component\JsonSchemaRuntime\Reference;
use Jane\Component\OpenApi3\JsonSchema\Model\Schema;
use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;

readonly class OperationWrapper
{
    public function __construct(private OperationGuess $operation)
    {
    }

    /**
     * Операция загружает файл application/octet-stream?
     * @return bool
     */
    public function isOctetStreamFile(): bool
    {
        $requestBody = $this->operation->getOperation()->getRequestBody();
        if (!$requestBody || !$requestBody->getContent()) {
            return false;
        }
        foreach ($requestBody->getContent() as $contentType => $mediaType) {
            $schema = $mediaType->getSchema();
            if (
                $contentType === 'application/octet-stream' &&
                $schema &&
                $schema->getType() === 'string' &&
                $schema->getFormat() === 'binary'
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Получаем тип элементов массива, если в requestBody тип параметра - массив
     * @return string|null
     */
    public function getArrayItemType(): ?string
    {
        $operationData = $this->operation->getOperation();
        $content = $operationData->getRequestBody()?->getContent();
        if ($content === null) {
            return null;
        }
        if (isset($content['application/json'])) {
            $schema = $content['application/json']->getSchema();
        } elseif (isset($content['application/xml'])) {
            $schema = $content['application/xml']->getSchema();
        } else {
            return null;
        }

        if (!$schema || !str_contains(($schema->getType() ?? ''), 'array')) {
            return null;
        }

        /** \Jane\Component\JsonSchemaRuntime\Reference $items */
        $items = $schema->getItems() ?? null;
        if (!$items) {
            return null; // неизвестный тип массива
        }

        if (($items instanceof Schema)) {
            return match ($items->getType()) {
                'string' => 'string',
                'integer' => 'int',
                'boolean' => 'bool',
                'number' => 'float',
                default => null
            };
        } elseif (($items instanceof Reference)) {
            $mergedUri = (string)$items->getMergedUri();
            if (preg_match('#/components/schemas/(.+)$#', $mergedUri, $matches)) {
                return '\\Webpractik\\Bitrixgen\\Dto\\' . $matches[1];
            }
        }

        return null;
    }
}
