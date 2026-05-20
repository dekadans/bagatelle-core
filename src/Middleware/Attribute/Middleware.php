<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Middleware\Attribute;

use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Middleware\MiddlewareConfiguratorInterface;
use tthe\Bagatelle\Middleware\MiddlewareHandler;
use tthe\Bagatelle\Middleware\MiddlewareInterface;
use tthe\Bagatelle\Routing\RouteDecoratorInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Middleware implements RouteDecoratorInterface
{
    /**
     * @param class-string<\tthe\Bagatelle\Middleware\MiddlewareInterface> $class
     */
    public function __construct(
        private string $class,
        private array $options = []
    ) {}

    public function decorate(Route $route): void
    {
        if (!is_subclass_of($this->class, MiddlewareInterface::class)) {
            throw new \InvalidArgumentException("'$this->class' is not a valid middleware.");
        }

        if (is_subclass_of($this->class, MiddlewareConfiguratorInterface::class)) {
            $configurator = \Closure::fromCallable([$this->class, 'configure']);
            $configurator($route, $this->options);
        }

        $current = $route->getDefault(MiddlewareHandler::ATTRIBUTE_KEY) ?? [];

        $route->addDefaults([
            MiddlewareHandler::ATTRIBUTE_KEY => [...$current, $this->class],
            $this->class => $this->options,
        ]);
    }
}
