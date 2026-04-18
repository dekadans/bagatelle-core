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
    private string|array $origin;
    private string|array $methods;
    private string|array $headers;
    private string|array $exposeHeaders;
    private bool $credentials;
    private ?int $maxAge;

    public function __construct(
        string|array|null $origin = null,
        string|array|null $methods = null,
        string|array|null $headers = null,
        string|array|null $exposeHeaders = null,
        ?bool $credentials = null,
        ?int $maxAge = null
    ) {
        $this->origin = $origin ?? $this->env('CORS_ALLOW_ORIGIN') ?? '*';
        $this->methods = $methods
            ?? $this->env('CORS_ALLOW_METHODS')
            ?? ['GET', 'HEAD', 'POST', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'];
        $this->headers = $headers ?? $this->env('CORS_ALLOW_HEADERS') ?? '*';
        $this->exposeHeaders = $exposeHeaders ?? $this->env('CORS_EXPOSE_HEADERS') ?? '';
        $this->credentials = (bool) ($credentials ?? $_ENV['CORS_ALLOW_CREDENTIALS'] ?? false);
        $this->maxAge = $maxAge ?? (isset($_ENV['CORS_MAX_AGE']) ? intval($_ENV['CORS_MAX_AGE']) : null);
    }

    public function decorate(Route $route): void
    {
        Middleware::add($route, CorsHandler::class);

        $route->setDefault('_cors', [
            'allow_origin' => $this->origin,
            'allow_methods' => $this->methods,
            'allow_headers' => $this->headers,
            'expose_headers' => $this->exposeHeaders,
            'allow_credentials' => $this->credentials,
            'max_age' => $this->maxAge,
        ]);

        $methods = $route->getMethods();
        if (!empty($methods) && !in_array('OPTIONS', $methods)) {
            $route->setMethods(['OPTIONS', ...$methods]);
        }
    }

    private function env(string $key): ?array
    {
        if (!empty($_ENV[$key])) {
            return array_map(trim(...), explode(',', $_ENV[$key]));
        }

        return null;
    }
}
