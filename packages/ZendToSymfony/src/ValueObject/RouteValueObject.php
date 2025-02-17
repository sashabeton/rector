<?php declare(strict_types=1);

namespace Rector\ZendToSymfony\ValueObject;

use Nette\Utils\Strings;
use Rector\NetteToSymfony\Annotation\SymfonyRoutePhpDocTagNode;
use Rector\Symfony\ValueObject\SymfonyClass;
use Rector\Util\RectorStrings;

final class RouteValueObject
{
    /**
     * @var string
     */
    private $controllerClass;

    /**
     * @var string
     */
    private $methodName;

    /**
     * @var mixed[]
     */
    private $params = [];

    /**
     * @param mixed[] $params
     */
    public function __construct(string $controllerClass, string $methodName, array $params = [])
    {
        $this->controllerClass = $controllerClass;
        $this->methodName = $methodName;
        $this->params = $params;
    }

    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    /**
     * @return mixed[]
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getSymfonyRoutePhpDocTagNode(): SymfonyRoutePhpDocTagNode
    {
        return new SymfonyRoutePhpDocTagNode(SymfonyClass::ROUTE_ANNOTATION, $this->getPath());
    }

    private function getPath(): string
    {
        $controllerPath = $this->resolveControllerPath();
        $controllerPath = RectorStrings::camelCaseToDashes($controllerPath);

        $methodPath = RectorStrings::camelCaseToDashes($this->methodName);

        $path = '/' . $controllerPath . '/' . $methodPath;
        $path = strtolower($path);

        // @todo solve required/optional/type of params
        foreach ($this->getParams() as $param) {
            $path .= '/{' . $param . '}';
        }

        return $path;
    }

    private function resolveControllerPath(): string
    {
        if (Strings::endsWith($this->controllerClass, 'Controller')) {
            return (string) Strings::before($this->controllerClass, 'Controller');
        }

        return $this->controllerClass;
    }
}
