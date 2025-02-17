<?php declare(strict_types=1);

namespace Rector\CodingStyle\Rector\ClassConst;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Type\MixedType;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see \Rector\CodingStyle\Tests\Rector\ClassConst\VarConstantCommentRector\VarConstantCommentRectorTest
 */
final class VarConstantCommentRector extends AbstractRector
{
    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    public function __construct(DocBlockManipulator $docBlockManipulator)
    {
        $this->docBlockManipulator = $docBlockManipulator;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Constant should have a @var comment with type', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    const HI = 'hi';
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    /**
     * @var string
     */
    const HI = 'hi';
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
        return [ClassConst::class];
    }

    /**
     * @param ClassConst $node
     */
    public function refactor(Node $node): ?Node
    {
        if (count($node->consts) > 1) {
            return null;
        }

        $constStaticType = $this->getStaticType($node->consts[0]->value);
        if ($constStaticType instanceof MixedType) {
            return null;
        }

        $this->docBlockManipulator->changeVarTag($node, $constStaticType);

        return $node;
    }
}
