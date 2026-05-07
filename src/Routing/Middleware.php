<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Routing;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;

abstract class Middleware
{
    private const string ATTRIBUTE_KEY = 'bagatelle.middleware';

    /**
     * Process the request before it reaches the controller.
     * If a Response is generated and returned by this function then the controller will not be called.
     * Return NULL to send the request along to the controller.
     *
     * @param Request $request
     * @return Response|null
     */
    public function inbound(Request $request): ?Response
    {
        return null;
    }

    /**
     * Process the generated response before it is sent to the client.
     *
     * @param Request $request
     * @param Response $response
     * @return void
     */
    public function outbound(Request $request, Response $response): void {}

    /**
     * @param Route $route
     * @param class-string<Middleware> $middleware
     * @return void
     */
    public static function add(Route $route, string $middleware): void
    {
        $current = $route->getDefault(static::ATTRIBUTE_KEY) ?? [];
        $route->setDefault(static::ATTRIBUTE_KEY, [...$current, $middleware]);
    }

    /**
     * @param Request $request
     * @param callable $resolver
     * @return Middleware[]
     */
    public static function resolve(Request $request, callable $resolver): array
    {
        $routeMiddleware = $request->attributes->get(static::ATTRIBUTE_KEY, []);
        return array_map($resolver, $routeMiddleware);
    }
}
