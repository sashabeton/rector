<?php declare(strict_types=1);

namespace Rector\CodeQuality\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\NodeTypeResolver\PHPStan\Type\TypeFactory;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see https://3v4l.org/GL6II
 * @see https://3v4l.org/eTrhZ
 * @see https://3v4l.org/C554W
 *
 * @see \Rector\CodeQuality\Tests\Rector\Class_\CompleteDynamicPropertiesRector\CompleteDynamicPropertiesRectorTest
 */
final class CompleteDynamicPropertiesRector extends AbstractRector
{
    /**
     * @var string
     */
    private const LARAVEL_COLLECTION_CLASS = 'Illuminate\Support\Collection';

    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    /**
     * @var TypeFactory
     */
    private $typeFactory;

    public function __construct(DocBlockManipulator $docBlockManipulator, TypeFactory $typeFactory)
    {
        $this->docBlockManipulator = $docBlockManipulator;
        $this->typeFactory = $typeFactory;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Add missing dynamic properties', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function set()
    {
        $this->value = 5;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var int
     */
    public $value;
    public function set()
    {
        $this->value = 5;
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    /**
     * @param Class_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isNonAnonymousClass($node)) {
            return null;
        }

        /** @var string $class */
        $class = $this->getName($node);
        // properties are accessed via magic, nothing we can do
        if (method_exists($class, '__set') || method_exists($class, '__get')) {
            return null;
        }

        // special case for Laravel Collection macro magic
        $fetchedLocalPropertyNameToTypes = $this->resolveFetchedLocalPropertyNameToType($node);

        $propertyNames = $this->getClassPropertyNames($node);

        $fetchedLocalPropertyNames = array_keys($fetchedLocalPropertyNameToTypes);
        $propertiesToComplete = array_diff($fetchedLocalPropertyNames, $propertyNames);

        // remove other properties that are accessible from this scope
        /** @var string $class */
        $class = $this->getName($node);
        foreach ($propertiesToComplete as $key => $propertyToComplete) {
            if (! property_exists($class, $propertyToComplete)) {
                continue;
            }

            unset($propertiesToComplete[$key]);
        }

        $newProperties = $this->createNewProperties($fetchedLocalPropertyNameToTypes, $propertiesToComplete);

        $node->stmts = array_merge($newProperties, $node->stmts);

        return $node;
    }

    /**
     * @param Type[] $fetchedLocalPropertyNameToTypes
     * @param string[] $propertiesToComplete
     * @return Property[]
     */
    private function createNewProperties(array $fetchedLocalPropertyNameToTypes, array $propertiesToComplete): array
    {
        $newProperties = [];
        foreach ($fetchedLocalPropertyNameToTypes as $propertyName => $propertyType) {
            if (! in_array($propertyName, $propertiesToComplete, true)) {
                continue;
            }

            $propertyBuilder = $this->builderFactory->property($propertyName);
            $propertyBuilder->makePublic();
            $property = $propertyBuilder->getNode();

            if ($this->isAtLeastPhpVersion('7.4')) {
                $phpStanNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($propertyType);
                if ($phpStanNode) {
                    $property->type = $phpStanNode;
                } else {
                    // fallback to doc type in PHP 7.4
                    $this->docBlockManipulator->changeVarTag($property, $propertyType);
                }
            } else {
                $this->docBlockManipulator->changeVarTag($property, $propertyType);
            }

            $newProperties[] = $property;
        }

        return $newProperties;
    }

    /**
     * @return Type[]
     */
    private function resolveFetchedLocalPropertyNameToType(Class_ $class): array
    {
        $fetchedLocalPropertyNameToTypes = [];

        $this->traverseNodesWithCallable($class->stmts, function (Node $node) use (
            &$fetchedLocalPropertyNameToTypes
        ) {
            if (! $node instanceof PropertyFetch) {
                return null;
            }

            if (! $this->isName($node->var, 'this')) {
                return null;
            }

            // special Laravel collection scope
            if ($this->shouldSkipForLaravelCollection($node)) {
                return null;
            }

            if ($node->name instanceof Variable) {
                return null;
            }

            $propertyName = $this->getName($node->name);
            if ($propertyName === null) {
                return null;
            }

            $propertyFetchType = $this->resolvePropertyFetchType($node);

            $fetchedLocalPropertyNameToTypes[$propertyName][] = $propertyFetchType;
        });

        // normalize types to union
        $fetchedLocalPropertyNameToType = [];
        foreach ($fetchedLocalPropertyNameToTypes as $name => $types) {
            $fetchedLocalPropertyNameToType[$name] = $this->typeFactory->createMixedPassedOrUnionType($types);
        }

        return $fetchedLocalPropertyNameToType;
    }

    /**
     * @return string[]
     */
    private function getClassPropertyNames(Class_ $class): array
    {
        $propertyNames = [];

        foreach ($class->getProperties() as $property) {
            $propertyNames[] = $this->getName($property);
        }

        return $propertyNames;
    }

    private function resolvePropertyFetchType(Node $node): Type
    {
        $parentNode = $node->getAttribute(AttributeKey::PARENT_NODE);

        // possible get type
        if ($parentNode instanceof Assign) {
            return $this->getStaticType($parentNode->expr);
        }

        return new MixedType();
    }

    private function shouldSkipForLaravelCollection(Node $node): bool
    {
        $staticCallOrClassMethod = $this->betterNodeFinder->findFirstAncestorInstancesOf(
            $node,
            [ClassMethod::class, StaticCall::class]
        );

        if (! $staticCallOrClassMethod instanceof StaticCall) {
            return false;
        }

        return $this->isName($staticCallOrClassMethod->class, self::LARAVEL_COLLECTION_CLASS);
    }
}
