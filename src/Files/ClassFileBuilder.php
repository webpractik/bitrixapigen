<?php

declare(strict_types=1);

namespace Webpractik\Bitrixapigen\Files;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UnionType;
use PhpParser\Node\UseItem;
use Webpractik\Bitrixapigen\Files\Settings\MethodParameterSettings;
use Webpractik\Bitrixapigen\Files\Settings\MethodSettings;
use Webpractik\Bitrixapigen\Files\Settings\UseSettings;
use Webpractik\Bitrixapigen\Internal\Utils\ClassResolver;

class ClassFileBuilder
{
    /**
     * @var string[]
     */
    private array $involvedFullClassNames = [];

    /**
     * @var UseSettings[]
     */
    private array $aliasedUses = [];

    /**
     * @var MethodSettings[]
     */
    private array $allMethodSettings = [];

    public function __construct(
        private readonly ClassResolver $classResolver,
        private readonly ?ClassResolver $extendsResolver = null,
    ) {
    }

    public function addMethod(MethodSettings $methodSettings): self
    {
        $this->allMethodSettings [] = $methodSettings;

        return $this;
    }

    public function build(): Node
    {
        $this->prepareUses();

        $uses = $this->getUses();

        $classNode = new Class_($this->classResolver->getClassName(), [
            'extends' => $this->getExtends(),
            'stmts'   => $this->getMethods(),
        ]);

        return new Namespace_(
            new Name($this->classResolver->getNamespace()),
            [...$uses, $classNode]
        );
    }

    private function prepareUses(): void
    {
        $this->collectInvolvedFullClassNames();
        $this->collectAliasedUses();
    }

    private function collectInvolvedFullClassNames(): void
    {
        $this->involvedFullClassNames [] = $this->classResolver->getFullClassName();

        if (null !== $this->extendsResolver) {
            $this->involvedFullClassNames [] = $this->extendsResolver->getFullClassName();
        }

        foreach ($this->allMethodSettings as $methodSettings) {
            foreach ($methodSettings->parameters as $methodParameterSettings) {
                $type = $methodParameterSettings->type;
                if ($type instanceof FullyQualified) {
                    $this->involvedFullClassNames [] = $type->toString();
                }
            }
        }

        $this->involvedFullClassNames = array_unique($this->involvedFullClassNames);
    }

    private function collectAliasedUses(): void
    {
        $allUses = array_map(function (string $fullClassName) {
            $alias = $this->getUseAlias($fullClassName);

            return new UseSettings($fullClassName, $alias);
        }, $this->involvedFullClassNames);

        $this->aliasedUses = array_filter($allUses, [$this, 'isUseRequired']);
    }

    private function getUseAlias(string $fullClassName): null|string
    {
        if ($fullClassName === $this->classResolver->getFullClassName()) {
            return null;
        }

        $className = preg_replace('/.+\\\\/', '', $fullClassName);

        $sameClassNames = array_filter($this->involvedFullClassNames, static function ($name) use ($className) {
            return $name === $className || str_ends_with($name, '\\' . $className);
        });

        if (count($sameClassNames) > 1) {
            $index = array_search($fullClassName, $sameClassNames);

            return $className . $index;
        }

        return null;
    }

    private function isUseRequired(UseSettings $useSettings): bool
    {
        $isSameNamespace = $this->isSameNamespace($useSettings->fullClassName);
        if ($isSameNamespace) {
            return null !== $useSettings->alias;
        }

        return true;
    }

    private function isSameNamespace(string $fullClassName): bool
    {
        if (!str_contains($fullClassName, '\\')) {
            return false;
        }

        $namespace = preg_replace('/\\\\[^\\\\]+$/', '', $fullClassName);

        return $namespace === $this->classResolver->getNamespace();
    }

    /**
     * @return Use_[]
     */
    private function getUses(): array
    {
        return array_map(static function (UseSettings $useSettings) {
            $name = new Name($useSettings->fullClassName);

            return new Use_([new UseItem($name, $useSettings->alias)]);
        }, $this->aliasedUses);
    }

    private function getExtends(): ?Name
    {
        if (null === $this->extendsResolver) {
            return null;
        }

        $alias = $this->getAlias($this->extendsResolver->getFullClassName());
        $name  = $alias ?? $this->extendsResolver->getClassName();

        return new Name($name);
    }

    /**
     * @return ClassMethod[]
     */
    private function getMethods(): array
    {
        return array_map(function (MethodSettings $methodSettings) {
            return new ClassMethod($methodSettings->name, [
                'flags'  => $methodSettings->flags,
                'params' => $this->getMethodParameters($methodSettings->parameters),
            ]);
        }, $this->allMethodSettings);
    }

    /**
     * @param MethodParameterSettings[] $allParameterSettings
     *
     * @return Param[]
     */
    private function getMethodParameters(array $allParameterSettings): array
    {
        return array_map(function (MethodParameterSettings $settings) {
            return new Param(
                var: new Variable($settings->name),
                default: $settings->getDefault(),
                type: $this->getMethodParameterType($settings),
                flags: $settings->flags
            );
        }, $allParameterSettings);
    }

    private function getMethodParameterType(MethodParameterSettings $settings): Node|Identifier|NullableType
    {
        $type = $settings->type;

        $alias = null;
        if ($type instanceof FullyQualified) {
            $alias = $this->getAlias($type->toString());
        }

        if (null !== $alias) {
            $type = new Identifier($alias);
        } elseif ($type instanceof FullyQualified) {
            $type = new Identifier($type->getLast());
        }

        if ($settings->isNullable) {
            $type = new UnionType([$type, new Identifier('null')]);
        }

        if (!$settings->isRequired && !($type instanceof UnionType)) {
            $type = new NullableType($type);
        }

        return $type;
    }

    private function getAlias(string $fullClassName): ?string
    {
        foreach ($this->aliasedUses as $aliasedUse) {
            if ($aliasedUse->fullClassName === $fullClassName) {
                return $aliasedUse->alias;
            }
        }

        return null;
    }
}
