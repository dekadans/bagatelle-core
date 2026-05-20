<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Middleware\MiddlewareConfiguratorInterface;
use tthe\Bagatelle\Middleware\MiddlewareInterface;

/**
 * Middleware for handling cross-origin (CORS protocol) requests.
 * See WHATWG standard at https://fetch.spec.whatwg.org/#http-cors-protocol
 *
 * Apply to routes or controllers using the #[Middleware] attribute.
 * Global configuration is set in the container, but can be overridden per route, e.g.:
 *   `#[Middleware(CORS::class, ['max_age' => 1000)]`
 */
class CORS implements MiddlewareInterface, MiddlewareConfiguratorInterface
{
    public function __construct(private array $options) {}

    /**
     * Configures HTTP methods allowed on the route and through CORS.
     * Called at route compile time.
     */
    public static function configure(Route $route, array &$options): void
    {
        $routeMethods = $route->getMethods();

        if ($routeMethods && !in_array('OPTIONS', $routeMethods)) {
            $route->setMethods([...$routeMethods, 'OPTIONS']);
        }

        if (!isset($options['allow_methods'])) {
            $options['allow_methods'] = $routeMethods ?: '*';
        }
    }

    /**
     * Process inbound request.
     * Override (skip controller) with an empty response for CORS-preflight requests.
     */
    public function inbound(Request $request, array $options): ?Response
    {
        if ($request->isMethod('OPTIONS') && $request->headers->has('Origin')) {
            return new Response('', 204);
        }

        return null;
    }

    /**
     * Process outbound response.
     * Adds relevant CORS headers to the response before it's sent to the client.
     */
    public function outbound(Request $request, Response $response, array $options): void
    {
        if (!$request->headers->has('Origin')) {
            return;
        }

        $response->headers->set('Vary', 'Origin');

        $this->setOptions($options);

        $origin = $this->getAllowedOrigin($request);
        if (!$origin) {
            return;
        }

        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => $this->options['allow_credentials'] ? 'true' : null,
        ];

        if ($request->isMethod('OPTIONS')) {
            $allowedMethods = $this->getAllowedMethods($request);
            $allowedHeaders = $this->getAllowedHeaders($request);

            if ($allowedMethods === null || $allowedHeaders === null) {
                return;
            }

            $headers['Access-Control-Allow-Methods'] = $allowedMethods;
            $headers['Access-Control-Allow-Headers'] = $allowedHeaders;
            $headers['Access-Control-Max-Age'] = $this->options['max_age'];
        } elseif ($this->options['expose_headers']) {
            $headers['Access-Control-Expose-Headers'] = $this->asString($this->options['expose_headers']);
        }

        $response->headers->add(array_filter($headers));
    }

    private function setOptions(array $routeOptions): void
    {
        $defaults = [
            'allow_origin' => [],
            'allow_methods' => [],
            'allow_headers' => [],
            'expose_headers' => [],
            'allow_credentials' => false,
            'max_age' => null,
        ];

        $filteredContainerOptions = array_filter($this->options, fn($v) => $v !== null);
        $filteredRouteOptions = array_filter($routeOptions, fn($v) => $v !== null);

        $this->options = array_merge($defaults, $filteredContainerOptions, $filteredRouteOptions);
    }

    private function getAllowedOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin', '');
        $allowed = $this->options['allow_origin'] ?? [];

        return $this->verifyHeader($origin, $allowed) ? $origin : null;
    }

    private function getAllowedMethods(Request $request): ?string
    {
        $requestMethod = $request->headers->get('Access-Control-Request-Method', '');
        if ($this->verifyHeader($requestMethod, $this->options['allow_methods'])) {
            return $this->asString($this->options['allow_methods']);
        }

        return null;
    }

    private function getAllowedHeaders(Request $request): ?string
    {
        if (!$request->headers->has('Access-Control-Request-Headers')) {
            return '';
        }

        $requestHeaders = array_map(
            trim(...),
            explode(',', $request->headers->get('Access-Control-Request-Headers'))
        );
        $allowedHeaders = $this->options['allow_headers'];
        $result = array_all($requestHeaders, fn($h) => $this->verifyHeader($h, $allowedHeaders));

        return $result ? $this->asString($allowedHeaders) : null;
    }

    private function verifyHeader(string $value, string|array $allowed): bool
    {
        if ($value === '') {
            return false;
        }

        $value = strtolower($value);

        if ($allowed === '*') {
            return true;
        } elseif (is_array($allowed)) {
            $allowed = array_map(strtolower(...), $allowed);
            return in_array($value, $allowed);
        } else {
            return ($value === strtolower($allowed));
        }
    }

    private function asString(string|array $values): string
    {
        return is_array($values) ? implode(', ', $values) : $values;
    }
}
