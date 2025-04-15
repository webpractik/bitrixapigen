<?php

namespace Webpractik\Bitrixapigen\Adaptation;

use Jane\Component\JsonSchema\Generator\Context\Context;
use Jane\Component\OpenApiCommon\Generator\EndpointGeneratorInterface;
use Jane\Component\OpenApiCommon\Guesser\Guess\OperationGuess;
use PhpParser\Comment;
use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\UnionType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use Psr\Http\Message\ResponseInterface;
use Jane\Component\JsonSchema\Generator\File;
use Webpractik\Bitrixapigen\Internal\ControllerBoilerplateSchema;
use Webpractik\Bitrixapigen\Internal\Wrappers\OperationWrapper;

class OperationGenerator
{
    protected $endpointGenerator;

    public function __construct(EndpointGeneratorInterface $endpointGenerator)
    {
        $this->endpointGenerator = $endpointGenerator;
    }

    protected function getReturnDoc(array $returnTypes, array $throwTypes): string
    {
        return implode('', array_map(function ($value) {
                return ' * @throws ' . $value . "\n";
            }, $throwTypes))
            . " *\n"
            . ' * @return ' . implode('|', $returnTypes);
    }

    public function createOperation(string $name, OperationGuess $operation, Context $context): Stmt\ClassMethod
    {
        /** @var Param[] $methodParams */
        [$endpointName, $methodParams, $methodDoc, $returnTypes, $throwTypes] = $this->endpointGenerator->createEndpointClass($operation, $context);
        $endpointArgs = [];

        $lastMethodParam = '';
        foreach ($methodParams as $param) {
            $endpointArgs[] = new Arg($param->var);
            $lastMethodParam = $param->var->name;
        }

        $paramsPosition = $lastMethodParam === 'accept' ? \count($methodParams) - 1 : \count($methodParams);
        array_splice($methodParams, $paramsPosition, 0,);

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
            $v = reset($returnTypes);
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
                if (str_contains($m->type->name, 'Webpractik\Bitrixgen')) {
                    $params[] = new Param(
                        new Expr\Variable($m->var->name),
                        null,
                        new Name(str_replace("Model", "Dto", $typeName))
                    );
                    continue;
                }

            if (str_contains($typeName, 'array')) {
                $arElementType = (new OperationWrapper($operation))->getArrayItemType();
                if (str_contains($arElementType, '\\Dto\\')) {
                    $collectionClass = new Name(self::makeCollectionClassName($arElementType));
                    $params[] = new Param(
                        new Variable($m->var->name),
                        null,
                        $collectionClass
                    );
                }
                continue;
            }

                    $params[] = new Param(
                        new Expr\Variable($m->var->name),
                        null,
                        new Identifier($m->type?->name)
                    );
        }

        $isOctetStreamFile = (new OperationWrapper($operation))->isOctetStreamFile();
        if ($isOctetStreamFile) {
            $params[] = new Param(
                new Expr\Variable('octetStreamRawContent'),
                null,
                new Identifier('string')
            );
        }
//        var_dump($params);

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
                                    'flags' => Modifiers::PUBLIC,
                                    'params' => $params,
                                    'returnType' => count($returnTypes) == 1 ? $returnType : new UnionType($returnType),
                                    'stmts' => null,
                                ]
                            )
                        ]
                    ]
                )]),
            'client'
        );
    }

    /**
     * @param string $v
     * @param bool $isSingleReturnType - у функции один тип возвращаемого значения?
     * @return Identifier|FullyQualified|Name
     */
    private function getReturnType(string $v, bool $isSingleReturnType): Identifier|FullyQualified|Name
    {
        if ($v === 'null') {
            return new Identifier($isSingleReturnType ? 'void' : 'null');
        }
        if ($this->ifIsDtoArray($v)) {
            $dtoClassName = str_replace('[]', '', $v);
            return new Name($this->makeCollectionClassName($dtoClassName));
        }

        return new Name\FullyQualified(mb_substr($v, 1));
    }

    private function makeCollectionClassName(string $dtoClassName): string
    {
        return str_replace('\\Dto\\', '\\Dto\\Collection\\', $dtoClassName) . 'Collection';
    }

    private function ifIsDtoArray(string $v): bool
    {
        return str_contains($v, '[]') && str_contains($v, '\\Dto\\');
    }
}
