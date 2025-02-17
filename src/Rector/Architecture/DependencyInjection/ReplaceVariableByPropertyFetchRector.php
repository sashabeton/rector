<?php declare(strict_types=1);

namespace Rector\Rector\Architecture\DependencyInjection;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\ObjectType;
use Rector\Configuration\Rector\Architecture\DependencyInjection\VariablesToPropertyFetchCollection;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class ReplaceVariableByPropertyFetchRector extends AbstractRector
{
    /**
     * @var VariablesToPropertyFetchCollection
     */
    private $variablesToPropertyFetchCollection;

    public function __construct(VariablesToPropertyFetchCollection $variablesToPropertyFetchCollection)
    {
        $this->variablesToPropertyFetchCollection = $variablesToPropertyFetchCollection;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Turns variable in controller action to property fetch, as follow up to action injection variable to property change.',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
final class SomeController
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function default()
    {
        $products = $productRepository->fetchAll();
    }
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
final class SomeController
{
    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function default()
    {
        $products = $this->productRepository->fetchAll();
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
        return [Variable::class];
    }

    /**
     * @param Variable $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isInControllerActionMethod($node)) {
            return null;
        }

        foreach ($this->variablesToPropertyFetchCollection->getVariableNamesAndTypes() as $name => $type) {
            if (! $this->isName($node, $name)) {
                continue;
            }

            /** @var ObjectType $type */
            if (! $this->isObjectType($node, $type)) {
                continue;
            }

            return $this->createPropertyFetch('this', $name);
        }

        return null;
    }

    private function isInControllerActionMethod(Variable $variable): bool
    {
        /** @var string|null $className */
        $className = $variable->getAttribute(AttributeKey::CLASS_NAME);
        if ($className === null) {
            return false;
        }

        if (! Strings::endsWith($className, 'Controller')) {
            return false;
        }

        /** @var ClassMethod|null $methodNode */
        $methodNode = $variable->getAttribute(AttributeKey::METHOD_NODE);
        if ($methodNode === null) {
            return false;
        }

        // is probably in controller action
        return $methodNode->isPublic();
    }
}
