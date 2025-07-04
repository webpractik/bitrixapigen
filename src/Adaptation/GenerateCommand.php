<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Console\Command\GenerateCommand as BaseGenerateCommand;
use Jane\Component\JsonSchema\Console\Loader\ConfigLoaderInterface;
use Jane\Component\JsonSchema\Console\Loader\SchemaLoaderInterface;
use Jane\Component\JsonSchema\Printer;
use Jane\Component\JsonSchema\Registry\RegistryInterface;
use Jane\Component\OpenApiCommon\Console\Loader\OpenApiMatcher;
use Jane\Component\OpenApiCommon\Registry\Registry;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function is_bool;

class GenerateCommand extends BaseGenerateCommand
{
    /** @var OpenApiMatcher */
    private $matcher;

    public function __construct(ConfigLoaderInterface $configLoader, SchemaLoaderInterface $schemaLoader, OpenApiMatcher $matcher)
    {
        parent::__construct($configLoader, $schemaLoader);
        $this->matcher = $matcher;
    }

    public function configure(): void
    {
        $this->setName('generate');
        $this->setDescription('Generate an api client: class, normalizers and resources given a specific Json OpenApi file');
        $this->addOption('openapi-file', 'o', InputOption::VALUE_REQUIRED, 'File with OpenAPI contract');
        $this->addOption('locale', 'l', InputOption::VALUE_OPTIONAL,  'Locale used for validation error messages, following the BCP 47 standard (e.g., "ru", "en"). Defaults to "ru" if not specified or unsupported.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $openApiFile = $input->getOption('openapi-file');
        if (empty($openApiFile)) {
            $output->writeln('<error>Параметр --openapi-file обязателен.</error>');

            return self::FAILURE;
        }
        $locale = trim($input->getOption('locale') ?? '');

        $configFile              = dirname(__DIR__, 2) . '/.jane-openapi';
        $options                 = $this->configLoader->load($configFile);

        GenerationContext::init($locale !== '' ? $locale : 'ru');
        $options['openapi-file'] = $openApiFile;
        $registries              = $this->registries($options);

        /** @var Registry $registry */
        foreach ($registries as $registry) {
            $openApiClass    = '\Webpractik\Bitrixapigen\Adaptation\WebpractikOpenApi';
            $janeOpenApi     = $openApiClass::build($options);
            $fixerConfigFile = '';

            if (array_key_exists('fixer-config-file', $options) && null !== $options['fixer-config-file']) {
                $fixerConfigFile = $options['fixer-config-file'];
            }

            $printer = new Printer(new Standard(['shortArraySyntax' => true]), $fixerConfigFile);

            if (array_key_exists('use-fixer', $options) && is_bool($options['use-fixer'])) {
                $printer->setUseFixer($options['use-fixer']);
            }
            if (array_key_exists('clean-generated', $options) && is_bool($options['clean-generated'])) {
                $printer->setCleanGenerated($options['clean-generated']);
            }

            $janeOpenApi->generate($registry);
            $printer->output($registry);
        }

        return 0;
    }

    protected function newRegistry(string $schemaFile, array $options): RegistryInterface
    {
        $registry = new Registry();
        $registry->setOpenApiClass($this->matcher->match($schemaFile));
        $registry->setWhitelistedPaths($options['whitelisted-paths'] ?? []);
        $registry->setThrowUnexpectedStatusCode($options['throw-unexpected-status-code'] ?? false);

        $customQueryResolver = [];
        foreach ($options['custom-query-resolver'] ?? [] as $path => $methods) {
            if (!array_key_exists($path, $customQueryResolver)) {
                $customQueryResolver[$path] = [];
            }

            foreach ($methods as $method => $parameters) {
                $method = mb_strtolower($method);
                if (!array_key_exists($method, $customQueryResolver[$path])) {
                    $customQueryResolver[$path][$method] = [];
                }

                if ('__type' === $path) {
                    // here, variables has a different meaning:
                    // - path => '__type', meta-key to handle all types of ...
                    // - method => will contains the type of the query parameter where to apply this normalizer
                    // - parameters => will contains the class to apply
                    $customQueryResolver['__type'][$method] = $this->formatClassName($parameters);
                    continue;
                }

                foreach ($parameters as $name => $class) {
                    if (!array_key_exists($name, $customQueryResolver[$path][$method])) {
                        $customQueryResolver[$path][$method][$name] = [];
                    }

                    $customQueryResolver[$path][$method][$name] = $this->formatClassName($class);
                }
            }
        }
        $registry->setCustomQueryResolver($customQueryResolver);

        return $registry;
    }

    private function formatClassName(string $class): string
    {
        if ('\\' === $class[0]) {
            return $class;
        }

        return '\\' . $class;
    }
}
