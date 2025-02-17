<?php declare(strict_types=1);

namespace Rector\NetteToSymfony\Rector\ClassMethod;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Type\ObjectType;
use Rector\NetteToSymfony\Annotation\SymfonyRoutePhpDocTagNode;
use Rector\NetteToSymfony\Route\RouteInfo;
use Rector\NetteToSymfony\Route\RouteInfoFactory;
use Rector\NodeContainer\ParsedNodesByType;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Rector\TypeDeclaration\TypeInferer\ReturnTypeInferer;
use Rector\Util\RectorStrings;
use ReflectionMethod;

/**
 * @see https://doc.nette.org/en/2.4/routing
 * @see https://symfony.com/doc/current/routing.html
 *
 * @see \Rector\NetteToSymfony\Tests\Rector\ClassMethod\RouterListToControllerAnnotationsRetor\RouterListToControllerAnnotationsRectorTest
 */
final class RouterListToControllerAnnotationsRector extends AbstractRector
{
    /**
     * @var string
     */
    private $routeListClass;

    /**
     * @var string
     */
    private $routerClass;

    /**
     * @var string
     */
    private $routeAnnotationClass;

    /**
     * @var ParsedNodesByType
     */
    private $parsedNodesByType;

    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    /**
     * @var RouteInfoFactory
     */
    private $routeInfoFactory;

    /**
     * @var ReturnTypeInferer
     */
    private $returnTypeInferer;

    public function __construct(
        ParsedNodesByType $parsedNodesByType,
        DocBlockManipulator $docBlockManipulator,
        RouteInfoFactory $routeInfoFactory,
        ReturnTypeInferer $returnTypeInferer,
        string $routeListClass = 'Nette\Application\Routers\RouteList',
        string $routerClass = 'Nette\Application\IRouter',
        string $routeAnnotationClass = 'Symfony\Component\Routing\Annotation\Route'
    ) {
        $this->routeListClass = $routeListClass;
        $this->routerClass = $routerClass;
        $this->parsedNodesByType = $parsedNodesByType;
        $this->docBlockManipulator = $docBlockManipulator;
        $this->routeAnnotationClass = $routeAnnotationClass;
        $this->routeInfoFactory = $routeInfoFactory;
        $this->returnTypeInferer = $returnTypeInferer;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Change new Route() from RouteFactory to @Route annotation above controller method',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
final class RouterFactory
{
    public function create(): RouteList
    {
        $routeList = new RouteList();
        $routeList[] = new Route('some-path', SomePresenter::class);

        return $routeList;
    }
}

final class SomePresenter
{
    public function run()
    {
    }
}                
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
final class RouterFactory
{
    public function create(): RouteList
    {
        $routeList = new RouteList();

        // case of single action controller, usually get() or __invoke() method
        $routeList[] = new Route('some-path', SomePresenter::class);

        return $routeList;
    }
}

final class SomePresenter
{
    /**
     * @Symfony\Component\Routing\Annotation\Route(path="some-path")
     */
    public function run()
    {
    }
}                
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * List of nodes this class checks, classes that implement @see \PhpParser\Node
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
        if (empty($node->stmts)) {
            return null;
        }

        $inferedReturnType = $this->returnTypeInferer->inferFunctionLike($node);

        $routeListObjectType = new ObjectType($this->routeListClass);

        if (! $inferedReturnType->isSuperTypeOf($routeListObjectType)->yes()) {
            return null;
        }

        $assignNodes = $this->resolveAssignRouteNodes($node);
        if ($assignNodes === []) {
            return null;
        }

        $routeInfos = $this->createRouteInfosFromAssignNodes($assignNodes);

        /** @var RouteInfo $routeInfo */
        foreach ($routeInfos as $routeInfo) {
            $classMethod = $this->resolveControllerClassMethod($routeInfo);
            if ($classMethod === null) {
                continue;
            }

            $phpDocTagNode = $this->createSymfonyRoutePhpDocTagNode($routeInfo);
            $this->docBlockManipulator->addTag($classMethod, $phpDocTagNode);
        }

        // complete all other non-explicit methods, from "<presenter>/<action>"
        $this->completeImplicitRoutes();

        // remove routes
        $this->removeNodes($assignNodes);

        return null;
    }

    /**
     * @return Assign[]
     */
    private function resolveAssignRouteNodes(ClassMethod $classMethod): array
    {
        // look for <...>[] = IRoute<Type>
        return $this->betterNodeFinder->find($classMethod->stmts, function (Node $classMethod): bool {
            if (! $classMethod instanceof Assign) {
                return false;
            }

            // $routeList[] =
            if (! $classMethod->var instanceof ArrayDimFetch) {
                return false;
            }

            if ($this->isObjectType($classMethod->expr, $this->routerClass)) {
                return true;
            }

            if ($classMethod->expr instanceof StaticCall) {
                // for custom static route factories
                return $this->isRouteStaticCallMatch($classMethod->expr);
            }

            return false;
        });
    }

    /**
     * @param Assign[] $assignNodes
     * @return RouteInfo[]
     */
    private function createRouteInfosFromAssignNodes(array $assignNodes): array
    {
        $routeInfos = [];

        // collect annotations and target controllers
        foreach ($assignNodes as $assignNode) {
            $routeNameToControllerMethod = $this->routeInfoFactory->createFromNode($assignNode->expr);
            if ($routeNameToControllerMethod === null) {
                continue;
            }

            $routeInfos[] = $routeNameToControllerMethod;
        }

        return $routeInfos;
    }

    private function resolveControllerClassMethod(RouteInfo $routeInfo): ?ClassMethod
    {
        $classNode = $this->parsedNodesByType->findClass($routeInfo->getClass());
        if ($classNode === null) {
            return null;
        }

        return $classNode->getMethod($routeInfo->getMethod());
    }

    private function completeImplicitRoutes(): void
    {
        $presenterClasses = $this->parsedNodesByType->findClassesBySuffix('Presenter');

        foreach ($presenterClasses as $presenterClass) {
            foreach ($presenterClass->getMethods() as $classMethod) {
                if ($this->shouldSkipClassStmt($classMethod)) {
                    continue;
                }

                $path = $this->resolvePathFromClassAndMethodNodes($presenterClass, $classMethod);
                $phpDocTagNode = new SymfonyRoutePhpDocTagNode($this->routeAnnotationClass, $path);

                $this->docBlockManipulator->addTag($classMethod, $phpDocTagNode);
            }
        }
    }

    /**
     * @todo allow extension with custom resolvers
     */
    private function isRouteStaticCallMatch(StaticCall $staticCall): bool
    {
        $className = $this->getName($staticCall->class);
        if ($className === null) {
            return false;
        }

        $methodName = $this->getName($staticCall->name);
        if ($methodName === null) {
            return false;
        }

        // @todo decouple - resolve method return type
        if (! method_exists($className, $methodName)) {
            return false;
        }

        $methodReflection = new ReflectionMethod($className, $methodName);
        if ($methodReflection->getReturnType() !== null) {
            $staticCallReturnType = (string) $methodReflection->getReturnType();
            if (is_a($staticCallReturnType, $this->routerClass, true)) {
                return true;
            }
        }

        return false;
    }

    private function shouldSkipClassStmt(Node $node): bool
    {
        if (! $node instanceof ClassMethod) {
            return true;
        }

        // not an action method
        if (! $node->isPublic()) {
            return true;
        }

        if (! $this->isName($node, '#^(render|action)#')) {
            return true;
        }

        // already has Route tag
        return $this->docBlockManipulator->hasTag($node, $this->routeAnnotationClass);
    }

    private function resolvePathFromClassAndMethodNodes(Class_ $classNode, ClassMethod $classMethod): string
    {
        /** @var string $presenterName */
        $presenterName = $this->getName($classNode);
        /** @var string $presenterPart */
        $presenterPart = Strings::after($presenterName, '\\', -1);
        /** @var string $presenterPart */
        $presenterPart = Strings::substring($presenterPart, 0, -Strings::length('Presenter'));
        $presenterPart = RectorStrings::camelCaseToDashes($presenterPart);

        $match = Strings::match($this->getName($classMethod), '#^(action|render)(?<short_action_name>.*?$)#sm');
        $actionPart = lcfirst($match['short_action_name']);

        return $presenterPart . '/' . $actionPart;
    }

    private function createSymfonyRoutePhpDocTagNode(RouteInfo $routeInfo): SymfonyRoutePhpDocTagNode
    {
        return new SymfonyRoutePhpDocTagNode(
            $this->routeAnnotationClass,
            $routeInfo->getPath(),
            null,
            $routeInfo->getHttpMethods()
        );
    }
}
