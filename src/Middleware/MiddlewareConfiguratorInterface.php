<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Middleware;

use Symfony\Component\Routing\Route;

interface MiddlewareConfiguratorInterface
{
    /**
     * For advanced use cases, this allows a middleware to modify the route definition
     * and options sent during route compilation.
     *
     * @param Route $route
     * @param array $options
     * @return void
     */
    public static function configure(Route $route, array &$options): void;
}