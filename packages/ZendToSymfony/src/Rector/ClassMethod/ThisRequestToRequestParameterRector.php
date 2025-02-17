<?php declare(strict_types=1);

namespace Rector\ZendToSymfony\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\Symfony\ValueObject\SymfonyClass;
use Rector\ZendToSymfony\Detector\ZendDetector;

/**
 * @sponsor Thanks https://previo.cz/ for sponsoring this rule
 *
 * @see \Rector\ZendToSymfony\Tests\Rector\ClassMethod\ThisRequestToRequestParameterRector\ThisRequestToRequestParameterRectorTest
 */
final class ThisRequestToRequestParameterRector extends AbstractRector
{
    /**
     * @var ZendDetector
     */
    private $zendDetector;

    public function __construct(ZendDetector $zendDetector)
    {
        $this->zendDetector = $zendDetector;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Change $this->_request in action method to $request parameter', [new CodeSample(
            <<<'CODE_SAMPLE'
public function someAction()
{
    $isGet = $this->_request->isGet();
}
CODE_SAMPLE
            ,
            <<<'CODE_SAMPLE'
public function someAction(\Symfony\Component\HttpFoundation\Request $request)
{
    $isGet = $request->isGet();
}
CODE_SAMPLE
        )]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**
     * @param ClassMethod $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->zendDetector->isZendActionMethod($node)) {
            return null;
        }

        $hasRequest = false;
        $this->traverseNodesWithCallable((array) $node->stmts, function (Node $node) use (&$hasRequest): ?Variable {
            if (! $node instanceof PropertyFetch) {
                return null;
            }

            if (! $this->isName($node->var, 'this')) {
                return null;
            }

            if (! $this->isName($node->name, '_request')) {
                return null;
            }

            $hasRequest = true;

            // @todo rename method call based on Zend → Symfony
            // "isXmlHttpRequest()" →
            // "isPost()" →
            // "getPosts()" →

            return new Variable('request');
        });

        // add request argument
        if ($hasRequest === false) {
            return null;
        }

        $node->params[] = $this->createParamWithNameAndClassType('request', SymfonyClass::REQUEST);

        return $node;
    }

    private function createParamWithNameAndClassType(string $name, string $classType): Param
    {
        return new Param(new Variable($name), null, new FullyQualified($classType));
    }
}
