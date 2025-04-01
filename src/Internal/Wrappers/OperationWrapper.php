<?php

namespace Webpractik\Bitrixapigen\Internal\Wrappers;

use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;

class OperationWrapper
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
}