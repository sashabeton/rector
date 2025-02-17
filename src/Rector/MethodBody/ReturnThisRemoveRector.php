<?php declare(strict_types=1);

namespace Rector\Rector\MethodBody;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Return_;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\ConfiguredCodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see \Rector\Tests\Rector\MethodBody\ReturnThisRemoveRector\ReturnThisRemoveRectorTest
 */
final class ReturnThisRemoveRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private $classesToDefluent = [];

    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    /**
     * @param string[] $classesToDefluent
     */
    public function __construct(DocBlockManipulator $docBlockManipulator, array $classesToDefluent = [])
    {
        $this->docBlockManipulator = $docBlockManipulator;
        $this->classesToDefluent = $classesToDefluent;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Removes "return $this;" from *fluent interfaces* for specified classes.', [
            new ConfiguredCodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function someFunction()
    {
        return $this;
    }

    public function otherFunction()
    {
        return $this;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function someFunction()
    {
    }

    public function otherFunction()
    {
    }
}
CODE_SAMPLE
                ,
                [['SomeExampleClass']]
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Return_::class];
    }

    /**
     * @param Return_ $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $node->expr instanceof Variable) {
            return null;
        }

        if (! $this->isName($node->expr, 'this')) {
            return null;
        }

        if (! $this->isObjectTypes($node->expr, $this->classesToDefluent)) {
            return null;
        }

        $this->removeNode($node);

        $methodNode = $node->getAttribute(AttributeKey::METHOD_NODE);
        if ($methodNode === null) {
            throw new ShouldNotHappenException(__METHOD__ . '() on line ' . __LINE__);
        }

        $this->docBlockManipulator->removeTagFromNode($methodNode, 'return');

        return null;
    }
}
