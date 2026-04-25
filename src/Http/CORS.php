<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http;

use tthe\Bagatelle\Routing\Middleware;
use tthe\Bagatelle\Routing\RouteDecoratorInterface;
use Symfony\Component\Routing\Route;

/**
 * Attribute for configuring Cross-origin resource sharing (CORS) for a route or controller.
 * Configure CORS access control using arguments to the attribute, or globally in the .env file.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class CORS implements RouteDecoratorInterface
{
    public function __construct(
        private string|array|null $origin = null,
        private string|array|null $methods = null,
        private string|array|null $headers = null,
        private string|array|null $exposeHeaders = null,
        private ?bool $credentials = null,
        private ?int $maxAge = null
    ) {}

    public function decorate(Route $route): void
    {
        $routeMethods = $route->getMethods();

        if (!empty($routeMethods)) {
            if (!$this->methods) {
                $this->methods = $routeMethods;
            }

            if (!in_array('OPTIONS', $routeMethods)) {
                $route->setMethods(['OPTIONS', ...$routeMethods]);
            }
        }

        $route->setDefault('_cors', [
            'allow_origin' => $this->origin,
            'allow_methods' => $this->methods,
            'allow_headers' => $this->headers,
            'expose_headers' => $this->exposeHeaders,
            'allow_credentials' => $this->credentials,
            'max_age' => $this->maxAge,
        ]);

        Middleware::add($route, CorsHandler::class);
    }
}
