<?php declare(strict_types=1);

namespace Rector\Symfony\Rector\FrameworkBundle;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\Symfony\ValueObject\SymfonyClass;

/**
 * @see \Rector\Symfony\Tests\Rector\FrameworkBundle\GetToConstructorInjectionRector\GetToConstructorInjectionRectorTest
 */
final class GetToConstructorInjectionRector extends AbstractToConstructorInjectionRector
{
    /**
     * @var string[]
     */
    private $getMethodAwareTypes = [];

    /**
     * @param string[] $getMethodAwareTypes
     */
    public function __construct(
        array $getMethodAwareTypes = [SymfonyClass::CONTROLLER, SymfonyClass::CONTROLLER_TRAIT]
    ) {
        $this->getMethodAwareTypes = $getMethodAwareTypes;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns fetching of dependencies via `$this->get()` to constructor injection in Command and Controller in Symfony',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
class MyCommand extends ContainerAwareCommand
{
    public function someMethod()
    {
        // ...
        $this->get('some_service');
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
class MyCommand extends Command
{
    public function __construct(SomeService $someService)
    {
        $this->someService = $someService;
    }

    public function someMethod()
    {
        $this->someService;
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isObjectTypes($node->var, $this->getMethodAwareTypes)) {
            return null;
        }

        if (! $this->isName($node, 'get')) {
            return null;
        }

        return $this->processMethodCallNode($node);
    }
}
