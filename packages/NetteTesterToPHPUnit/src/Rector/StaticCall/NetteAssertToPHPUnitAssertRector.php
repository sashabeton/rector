<?php declare(strict_types=1);

namespace Rector\NetteTesterToPHPUnit\Rector\StaticCall;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use Rector\NetteTesterToPHPUnit\AssertManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class NetteAssertToPHPUnitAssertRector extends AbstractRector
{
    /**
     * @var AssertManipulator
     */
    private $assertManipulator;

    public function __construct(AssertManipulator $assertManipulator)
    {
        $this->assertManipulator = $assertManipulator;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Migrate Nette/Assert calls to PHPUnit', [
            new CodeSample(
                <<<'CODE_SAMPLE'
use Tester\Assert;

function someStaticFunctions()
{
    Assert::true(10 == 5);
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use Tester\Assert;

function someStaticFunctions()
{
    \PHPUnit\Framework\Assert::assertTrue(10 == 5);
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
        return [StaticCall::class];
    }

    /**
     * @param StaticCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isObjectType($node, 'Tester\Assert')) {
            return null;
        }

        return $this->assertManipulator->processStaticCall($node);
    }
}
