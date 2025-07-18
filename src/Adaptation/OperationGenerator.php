<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\JsonSchema\Generator\File;
use Jane\Component\OpenApiCommon\Generator\EndpointGeneratorInterface;
use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;
use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\UnionType;
use RuntimeException;
use Throwable;
use Webpractik\Bitrixapigen\Internal\ControllerBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\Utils\DtoNameResolver;
use Webpractik\Bitrixapigen\Internal\Wrappers\OperationWrapper;

use function count;

class OperationGenerator
{
    protected $endpointGenerator;

    public function __construct(EndpointGeneratorInterface $endpointGenerator)
    {
        $this->endpointGenerator = $endpointGenerator;
    }

    public function createOperation(string $name, OperationGuess $operation, Context $context): Stmt\ClassMethod
    {
        /** @var Param[] $methodParams */
        [$endpointName, $methodParams, $methodDoc, $returnTypes, $throwTypes] = $this->endpointGenerator->createEndpointClass($operation, $context);
        $endpointArgs = [];

        $lastMethodParam = '';
        foreach ($methodParams as $param) {
            $endpointArgs[]  = new Arg($param->var);
            $lastMethodParam = $param->var->name;
        }

        $paramsPosition = $lastMethodParam === 'accept' ? count($methodParams) - 1 : count($methodParams);
        array_splice($methodParams, $paramsPosition, 0);

        $isOctetStreamFile = (new OperationWrapper($operation))->isOctetStreamFile();

        /** методы в контроллер добавлять тут  */
        return ControllerBoilerplateSchema::getBoilerplateForProcessWithDtoBody(
            $operation,
            $name,
            $methodParams,
            $returnTypes,
            $isOctetStreamFile
        );
    }

    public function getInfoForUseCase(OperationGuess $operation, Context $context): array
    {
        [$endpointName, $methodParams, $methodDoc, $returnTypes, $throwTypes] = $this->endpointGenerator->getInfoForInterface($operation, $context);

        return [$methodParams, $returnTypes];
    }

    public function generateFileForInterfaces(string $iPath, string $iName, string $name, OperationGuess $operation, Context $context): File
    {
        [$endpointName, $methodParams, $methodDoc, $returnTypes, $throwTypes] = $this->endpointGenerator->getInfoForInterface($operation, $context);
        $returnType = [];

        if (count($returnTypes) == 1) {
            $v          = reset($returnTypes);
            $returnType = $this->getReturnType(v: $v, isSingleReturnType: true);
        } else {
            foreach ($returnTypes as $v) {
                $returnType[] = $this->getReturnType($v, isSingleReturnType: false);
            }
        }

        $params = [];
        foreach ($methodParams as $m) {
            $typeName = $m->type?->name ?? '';

            if ($m->var->name === 'accept' || $m->var->name === null || $typeName === '' || str_contains($m->var->name, 'headerParameters')) {
                continue;
            }

            if ($m->var->name === 'queryParameters') {
                $params[] = new Param(
                    new Variable('queryParameters'),
                    null,
                    new Identifier('array')
                );
            } elseif ($m->var->name !== 'requestBody') {
                $params[] = new Param(
                    var: new Variable($m->var->name),
                    type: new Identifier($typeName)
                );
            }

            if (str_contains($m->type->name, 'Webpractik\Bitrixgen')) {
                $dtoNameResolver = DtoNameResolver::createByModelFullName($typeName);
                $params[]        = new Param(
                    new Expr\Variable('requestDto'),
                    null,
                    new Name('\\' . $dtoNameResolver->getDtoFullClassName())
                );
                continue;
            }

            if (str_contains($typeName, 'array')) {
                $arElementType = (new OperationWrapper($operation))->getArrayItemType();
                if (str_contains($typeName, '\\Model\\')) {
                    $dtoNameResolver = DtoNameResolver::createByModelFullName($arElementType);
                    $collectionClass = new Name('\\' . $dtoNameResolver->getCollectionFullClassName());
                    $params[]        = new Param(
                        new Variable('requestDtoCollection'),
                        null,
                        $collectionClass
                    );
                }
                continue;
            }
        }

        $isOctetStreamFile = (new OperationWrapper($operation))->isOctetStreamFile();
        if ($isOctetStreamFile) {
            $params[] = new Param(
                new Expr\Variable('octetStreamRawContent'),
                null,
                new Identifier('string')
            );
        }

        return new File(
            $iPath,
            new Stmt\Namespace_(new Name("Webpractik\Bitrixgen\Interfaces"), [
                new Stmt\Interface_(
                    new Identifier(
                        $iName
                    ),
                    [
                        'stmts' => [
                            new Stmt\ClassMethod(
                                new Identifier(
                                    'process',
                                ),
                                [
                                    'flags'      => Modifiers::PUBLIC,
                                    'params'     => $params,
                                    'returnType' => count($returnTypes) == 1 ? $returnType : new UnionType($returnType),
                                    'stmts'      => null,
                                ]
                            ),
                        ],
                    ]
                ),
            ]),
            'client'
        );
    }

    protected function getReturnDoc(array $returnTypes, array $throwTypes): string
    {
        return implode('', array_map(function ($value) {
                return ' * @throws ' . $value . "\n";
            }, $throwTypes))
               . " *\n"
               . ' * @return ' . implode('|', $returnTypes);
    }

    /**
     * @param string $v
     * @param bool   $isSingleReturnType - у функции один тип возвращаемого значения?
     *
     * @return Identifier|FullyQualified|Name
     */
    private function getReturnType(string $v, bool $isSingleReturnType): Identifier|FullyQualified|Name
    {
        if ($v === 'null') {
            return new Identifier($isSingleReturnType ? 'void' : 'null');
        }
        if ($this->ifIsModelArray($v)) {
            try {
                $modelFullName   = str_replace('[]', '', $v);
                $dtoNameResolver = DtoNameResolver::createByModelFullName($modelFullName);

                return new Name($dtoNameResolver->getCollectionFullClassName());
            } catch (Throwable $e) {
                throw new RuntimeException(var_export([
                    'dtoFullClassName' => $modelFullName,
                ], true));
            }
        }

        if (DtoNameResolver::isModelFullName($v)) {
            $dtoNameResolver = DtoNameResolver::createByModelFullName($v);
            $v               = $dtoNameResolver->getDtoFullClassName();
        }

        return new Name\FullyQualified(trim($v, '\\'));
    }

    private function ifIsModelArray(string $v): bool
    {
        return str_contains($v, '[]') && str_contains($v, '\\Model\\');
    }
}
