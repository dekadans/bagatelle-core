<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http;

use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Routing\Middleware;
use tthe\Bagatelle\Routing\RouteDecoratorInterface;

/**
 * Attribute for protecting a route or controller using standard HTTP Basic Authentication.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class BasicAuth implements RouteDecoratorInterface
{
    public function decorate(Route $route): void
    {
        Middleware::add($route, BasicAuthHandler::class);
    }
}
