<?php declare(strict_types=1);

namespace Rector\TypeDeclaration\Rector\FunctionLike;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Php\ValueObject\PhpVersionFeature;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\TypeDeclaration\TypeInferer\ReturnTypeInferer;
use Rector\TypeDeclaration\TypeInferer\ReturnTypeInferer\ReturnTypeDeclarationReturnTypeInferer;

/**
 * @sponsor Thanks https://spaceflow.io/ for sponsoring this rule - visit them on https://github.com/SpaceFlow-app
 *
 * @see \Rector\TypeDeclaration\Tests\Rector\FunctionLike\ReturnTypeDeclarationRector\ReturnTypeDeclarationRectorTest
 */
final class ReturnTypeDeclarationRector extends AbstractTypeDeclarationRector
{
    /**
     * @var string[]
     */
    private const EXCLUDED_METHOD_NAMES = ['__construct', '__destruct', '__clone'];

    /**
     * @var string
     */
    private const DO_NOT_CHANGE = 'do_not_change';

    /**
     * @var ReturnTypeInferer
     */
    private $returnTypeInferer;

    /**
     * @var bool
     */
    private $overrideExistingReturnTypes = true;

    public function __construct(ReturnTypeInferer $returnTypeInferer, bool $overrideExistingReturnTypes = true)
    {
        $this->returnTypeInferer = $returnTypeInferer;
        $this->overrideExistingReturnTypes = $overrideExistingReturnTypes;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Change @return types and type from static analysis to type declarations if not a BC-break',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
<?php

class SomeClass
{
    /**
     * @return int
     */
    public function getCount()
    {
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
<?php

class SomeClass
{
    /**
     * @return int
     */
    public function getCount(): int
    {
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @param ClassMethod|Function_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isAtLeastPhpVersion(PhpVersionFeature::SCALAR_TYPES)) {
            return null;
        }

        if ($this->shouldSkip($node)) {
            return null;
        }

        $inferedType = $this->returnTypeInferer->inferFunctionLikeWithExcludedInferers(
            $node,
            [ReturnTypeDeclarationReturnTypeInferer::class]
        );
        if ($inferedType instanceof MixedType) {
            return null;
        }

        if ($this->isReturnTypeAlreadyAdded($node, $inferedType)) {
            return null;
        }

        $inferredReturnNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($inferedType);

        // nothing to change in PHP code - @todo add @var annotation fallback?
        if ($inferredReturnNode === null) {
            return null;
        }

        // already overridden by previous populateChild() method run
        if ($node->returnType && $node->returnType->getAttribute(self::DO_NOT_CHANGE)) {
            return null;
        }

        // should be previous overridden?
        if ($node->returnType !== null) {
            $isSubtype = $this->isSubtypeOf($inferredReturnNode, $node->returnType);

            // @see https://wiki.php.net/rfc/covariant-returns-and-contravariant-parameters
            if ($this->isAtLeastPhpVersion('7.4') && $isSubtype) {
                $node->returnType = $inferredReturnNode;
            } elseif ($isSubtype === false) { // type override
                $node->returnType = $inferredReturnNode;
            }
        } else {
            $node->returnType = $inferredReturnNode;
        }

        if ($node instanceof ClassMethod) {
            $this->populateChildren($node, $inferedType);
        }

        return $node;
    }

    /**
     * Add typehint to all children class methods
     */
    private function populateChildren(ClassMethod $classMethod, Type $returnType): void
    {
        $methodName = $this->getName($classMethod);
        if ($methodName === null) {
            throw new ShouldNotHappenException(__METHOD__ . '() on line ' . __LINE__);
        }

        $className = $classMethod->getAttribute(AttributeKey::CLASS_NAME);
        if (! is_string($className)) {
            throw new ShouldNotHappenException(__METHOD__ . '() on line ' . __LINE__);
        }

        $childrenClassLikes = $this->parsedNodesByType->findChildrenOfClass($className);
        if ($childrenClassLikes === []) {
            return;
        }

        // update their methods as well
        foreach ($childrenClassLikes as $childClassLike) {
            $usedTraits = $this->parsedNodesByType->findUsedTraitsInClass($childClassLike);
            foreach ($usedTraits as $trait) {
                $this->addReturnTypeToChildMethod($trait, $classMethod, $returnType);
            }

            $this->addReturnTypeToChildMethod($childClassLike, $classMethod, $returnType);
        }
    }

    private function addReturnTypeToChildMethod(
        ClassLike $classLike,
        ClassMethod $classMethod,
        Type $returnType
    ): void {
        $methodName = $this->getName($classMethod);

        $currentClassMethod = $classLike->getMethod($methodName);
        if ($currentClassMethod === null) {
            return;
        }

        $resolvedChildTypeNode = $this->resolveChildTypeNode($returnType);
        if ($resolvedChildTypeNode === null) {
            return;
        }

        $currentClassMethod->returnType = $resolvedChildTypeNode;

        // make sure the type is not overridden
        $currentClassMethod->returnType->setAttribute(self::DO_NOT_CHANGE, true);

        $this->notifyNodeChangeFileInfo($currentClassMethod);
    }

    /**
     * @param ClassMethod|Function_ $node
     */
    private function shouldSkip(Node $node): bool
    {
        if ($this->overrideExistingReturnTypes === false) {
            if ($node->returnType) {
                return true;
            }
        }

        if (! $node instanceof ClassMethod) {
            return false;
        }

        return $this->isNames($node, self::EXCLUDED_METHOD_NAMES);
    }

    /**
     * @param ClassMethod|Function_ $node
     */
    private function isReturnTypeAlreadyAdded(Node $node, Type $returnType): bool
    {
        $returnNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($returnType);

        if ($node->returnType === null) {
            return false;
        }

        if ($this->print($node->returnType) === $this->print($returnNode)) {
            return true;
        }

        // prevent overriding self with itself
        if ($this->print($node->returnType) === 'self') {
            $className = $node->getAttribute(AttributeKey::CLASS_NAME);
            if (ltrim($this->print($returnNode), '\\') === $className) {
                return true;
            }
        }

        return false;
    }
}
