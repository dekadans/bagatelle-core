<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Routing;

use Symfony\Component\Routing\Loader\AttributeClassLoader;
use Symfony\Component\Routing\Route;

class DecoratedControllerLoader extends AttributeClassLoader
{
    protected function configureRoute(
        Route $route,
        \ReflectionClass $class,
        \ReflectionMethod $method,
        object $attr
    ): void {
        $route->setDefault('_controller', $this->getControllerName($class, $method));
        $this->runDecorators($route, $class, $method);
    }

    private function getControllerName(\ReflectionClass $class, \ReflectionMethod $method): string
    {
        if ($method->getName() === '__invoke') {
            return $class->getName();
        } else {
            return $class->getName() . '::' . $method->getName();
        }
    }

    private function runDecorators(Route $route, \ReflectionClass $class, \ReflectionMethod $method): void
    {
        $attributes = array_merge(
            ...array_map(
                fn($r) => $r->getAttributes(RouteDecoratorInterface::class, \ReflectionAttribute::IS_INSTANCEOF),
                [$class, $method]
            )
        );

        foreach ($attributes as $attribute) {
            $attribute->newInstance()->decorate($route);
        }
    }
}
