<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http\Attribute;

use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Routing\RouteDecoratorInterface;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Post extends RouteAttribute implements RouteDecoratorInterface
{
    public function decorate(Route $route): void
    {
        $route->setMethods(['POST']);
    }
}
