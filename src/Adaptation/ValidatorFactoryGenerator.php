<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\JsonSchema\Generator\GeneratorInterface;
use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Registry\Schema;
use PhpParser\ParserFactory;

class ValidatorFactoryGenerator implements GeneratorInterface
{

    public function generate(Schema $schema, string $className, Context $context): void
    {
        $this->generateFactory($schema, $className, $context);
        $this->generateTrait($schema, $className, $context);
    }

    private function generateFactory(Schema $schema, string $className, Context $context): void
    {
        $namespace = 'Webpractik\\Bitrixgen\\Validator';
        $className = 'ValidatorFactory';
        $code      = <<<PHP
<?php

namespace $namespace;

use Symfony\\Component\\Translation\\Loader\\XliffFileLoader;
use Symfony\\Component\\Translation\\Translator;
use Symfony\\Component\\Validator\\Validation;
use Symfony\\Component\\Validator\\Validator\\ValidatorInterface;
use Composer\\InstalledVersions;

class ValidatorFactory
{
    private static array \$validatorsByLocale = [];

    public static function create(string \$locale): ValidatorInterface
    {
        if (isset(self::\$validatorsByLocale[\$locale])) {
            return self::\$validatorsByLocale[\$locale];
        }

        \$translator = new Translator(\$locale);
        \$translator->addLoader('xlf', new XliffFileLoader());

        \$translationFile = self::getTranslateFile(\$locale);

        if (!file_exists(\$translationFile)) {
            \$locale = 'ru';
            \$translationFile = self::getTranslateFile(\$locale);
        }

        \$translator->addResource('xlf', \$translationFile, \$locale, 'validators');

        \$validator = Validation::createValidatorBuilder()
            ->setTranslator(\$translator)
            ->setTranslationDomain('validators')
            ->getValidator();

        self::\$validatorsByLocale[\$locale] = \$validator;
        return self::\$validatorsByLocale[\$locale];
    }

    private static function getTranslateFile(string \$locale): string
    {
        return InstalledVersions::getInstallPath('symfony/validator') . '/Resources/translations/validators.' . \$locale . '.xlf';
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast    = $parser->parse($code);

        $namespaceNode = reset($ast);

        $schema->addFile(new File($schema->getDirectory() . DIRECTORY_SEPARATOR . 'Validator' . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'factory'));
    }

    private function generateTrait(Schema $schema, string $className, Context $context): void
    {
        $namespace = 'Webpractik\\Bitrixgen\\Validator';
        $className = 'ValidatorTrait';
        $code      = <<<PHP
<?php

namespace $namespace;

use Symfony\\Component\\Validator\\Constraint;
use Webpractik\Bitrixgen\Runtime\Normalizer\ValidationException;

trait $className
{
    protected function validate(array \$data, Constraint \$constraint, string \$locale): void
    {
        \$validator = ValidatorFactory::create(\$locale);
        \$violations = \$validator->validate(\$data, \$constraint);
        if (\$violations->count() > 0) {
            throw new ValidationException(\$violations);
        }
    }
}
PHP;

        $parser = (new ParserFactory())->createForHostVersion();
        $ast    = $parser->parse($code);

        $namespaceNode = reset($ast);

        $schema->addFile(new File($schema->getDirectory() . DIRECTORY_SEPARATOR . 'Validator' . DIRECTORY_SEPARATOR . $className . '.php', $namespaceNode, 'trait'));
    }
}
