<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Application as JsonSchemaApplication;
use Jane\Component\OpenApiCommon\Console\Command\DumpConfigCommand;
use Webpractik\Bitrixapigen\Adaptation\GenerateCommand;
use Jane\Component\OpenApiCommon\Console\Loader\ConfigLoader;
use Jane\Component\OpenApiCommon\Console\Loader\OpenApiMatcher;
use Jane\Component\OpenApiCommon\Console\Loader\SchemaLoader;

class Application extends JsonSchemaApplication
{
    protected function boot(): void
    {
        $configLoader = new ConfigLoader();

        $this->add(new GenerateCommand($configLoader, new SchemaLoader(), new OpenApiMatcher()));
        $this->add(new DumpConfigCommand($configLoader));
    }
}

