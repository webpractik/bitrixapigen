<?php

namespace Webpractik\Bitrixapigen\Internal;

use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\JsonSchema\Generator\GeneratorInterface;
use Jane\Component\JsonSchema\Registry\Schema;
use Jane\Component\OpenApi3\Generator\Client\ServerPluginGenerator;
use Jane\Component\OpenApiCommon\Generator\Client\HttpClientCreateGenerator;
use Jane\Component\OpenApiCommon\Naming\OperationNamingInterface;
use PhpParser\Error;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Expr;
use PhpParser\NodeDumper;
use PhpParser\PrettyPrinter;
use PhpParser\ParserFactory;
use Webpractik\Bitrixapigen\Adaptation\OperationGenerator;

class BitrixControllersGenerator implements GeneratorInterface
{
    use ControllersGenerator;
    use HttpClientCreateGenerator;
    use ServerPluginGenerator;

    /** @var OperationGenerator */
    private $operationGenerator;

    /** @var OperationNamingInterface */
    private $operationNaming;

    public function __construct(OperationGenerator $operationGenerator, OperationNamingInterface $operationNaming)
    {
        $this->operationGenerator = $operationGenerator;
        $this->operationNaming = $operationNaming;
    }

    public function generate(Schema $schema, string $className, Context $context): void
    {
        $sortedByTags = [];
        $controllersNamespace = $schema->getNamespace() . '\\Controllers';

        foreach ($schema->getOperations() as $operation) {
            if (!array_key_exists($operation->getOperation()->getTags()[0], $sortedByTags)) {
                $sortedByTags[$operation->getOperation()->getTags()[0]] = [];
            }
            $sortedByTags[$operation->getOperation()->getTags()[0]][] = $operation;
        }

        $routerContent = [];

        $routerContent[] = BoilerplateSchema::getUse('Bitrix\\Main\\Routing\\RoutingConfigurator');
        foreach ($sortedByTags as $key => $value) {
            $controllerClassName = ucfirst($key) . $this->getSuffix();
            $routerContent[] = BoilerplateSchema::getUse($controllersNamespace . '\\' . $controllerClassName);
        }
        $routerContent[] = new Nop();

        $routerMethods = BoilerplateSchema::getRouterBody();

        $settingsGlobalFile = [
            new Stmt\Use_([new Node\UseItem(new Name("Bitrix\Main\DI\ServiceLocator"))]),
            new Stmt\Expression(
                new Expr\Assign(
                    new Expr\Variable("serviceLocator"),
                    new Expr\StaticCall(
                        new Name("ServiceLocator"), new Identifier("getInstance"))
                )
            ),
            new Stmt\Expression(
                new Expr\Assign(
                    new Expr\Variable("serviceValue"),
                    new Expr\Array_([])
                )
            ),
            /** start repeatable */

            /** end repeatable */

        ];
        foreach ($sortedByTags as $key => $value) {
            $controllerClassName = ucfirst($key) . $this->getSuffix();
            $controllerFullPath = $schema->getDirectory() . \DIRECTORY_SEPARATOR . 'Controllers' . \DIRECTORY_SEPARATOR . ucfirst($key) . $this->getSuffix() . '.php';
            $client = $this->createResourceClass($schema, $controllerClassName);
            $useStmts = [
                new Stmt\Use_([new Stmt\UseUse(new Name('Bitrix\Main\Engine\Controller'))]),
            ];
            $useStmts[] = new Nop();
            $useStmts[] = $client;

            $existClassAst = null;
            if (file_exists($controllerFullPath)) {
                $parser = (new ParserFactory())->createForNewestSupportedVersion();
                try {
                    $existClassAst = $parser->parse(file_get_contents($controllerFullPath));
                } catch (Error $error) {
                    echo "Parse error: {$error->getMessage()}\n";
                    return;
                }
            }


            /** TODO здесь лежат операции */
            foreach ($value as $operation) {
                $settingsGlobalFile[] = BoilerplateSchema::getFirstOpIfForSettings($this->operationNaming->getFunctionName($operation));

                $settingsGlobalFile[] = BoilerplateSchema::getSecondOpIfForSettings($this->operationNaming->getFunctionName($operation));


                $operationName = $this->operationNaming->getFunctionName($operation);
                $routerMethods->expr->stmts[] = BoilerplateSchema::getMethodForRouter(
                    $operation->getMethod(), ltrim($operation->getPath(), '/'), ucfirst($key) . $this->getSuffix(), $operationName
                );
                $client->stmts[] = $this->operationGenerator->createOperation($operationName . 'Action', $operation, $context);

                /** Генерируем интерфейсы для usecase'ов */
                $iName = "I" . ucfirst($this->operationNaming->getFunctionName($operation));
                $iPath = $schema->getDirectory() . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . "lib" . \DIRECTORY_SEPARATOR . "Interfaces" . \DIRECTORY_SEPARATOR . $iName . '.php';;
                $schema->addFile($this->operationGenerator->generateFileForInterfaces($iPath, $iName, $operationName, $operation, $context));
                /** Генерируем usecase'ы */

                [$methodParams, $returnTypes] = $this->operationGenerator->getInfoForUseCase($operation, $context);
                $dName = ucfirst($this->operationNaming->getFunctionName($operation));
                $dPath = $schema->getDirectory() . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . "lib" . \DIRECTORY_SEPARATOR . "UseCase" . \DIRECTORY_SEPARATOR . ucfirst($this->operationNaming->getFunctionName($operation)) . '.php';;
                $schema->addFile(UseCaseBoilerplateSchema::getUseCaseBoilerplate($dPath, $dName, $operationName, $methodParams, $returnTypes));
            }
            if ($existClassAst !== null) {
                Treasurer::analyze($existClassAst, $useStmts);
            }
            $node = new Stmt\Namespace_(new Name($controllersNamespace), $useStmts);
            $schema->addFile(new File(
                $controllerFullPath,
                $node,
                'client'
            ));
        }

        $schema->addFile(new File(
            $schema->getDirectory() . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . ".include" . '.php',
            new Stmt\Nop([]),
            'client'
        ));

        $settingsGlobalFile[] = new Stmt\Return_(
            new Expr\Array_(
                [
                    new Node\ArrayItem(
                        new Expr\Array_(
                            [
                                new Node\ArrayItem(
                                    new Expr\Variable("serviceValue"),
                                    new Scalar\String_("value"),
                                )
                            ]
                        ),
                        new Scalar\String_("services")
                    )
                ]
            )
        );
        $schema->addFile(new File(
            $schema->getDirectory() . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . ".settings" . '.php',
            new Stmt\Namespace_(new Name($schema->getNamespace()), $settingsGlobalFile),
            'client'
        ));


        $routerContent[] = $routerMethods;
        $nodes = new Stmt\Namespace_(new Name($schema->getNamespace()), $routerContent);
        $schema->addFile(new File(
            $schema->getDirectory() . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . "routes" . '.php',
            $nodes,
            'client'
        ));

        $installNodes = BoilerplateSchema::getInstallFileBody();
        $schema->addFile(new File(
            $schema->getDirectory() . \DIRECTORY_SEPARATOR . '..' . \DIRECTORY_SEPARATOR . 'install' . \DIRECTORY_SEPARATOR . "index" . '.php',
            $installNodes,
            'client'
        ));
    }
}

