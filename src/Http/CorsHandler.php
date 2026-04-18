<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http;

use tthe\Bagatelle\Routing\Middleware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for handling cross-origin (CORS protocol) requests.
 * See WHATWG standard at https://fetch.spec.whatwg.org/#http-cors-protocol
 * Apply to routes or controllers using the #[CORS] attribute.
 */
class CorsHandler extends Middleware
{
    /**
     * Process inbound request.
     * Override (skip controller) with an empty response for CORS-preflight requests.
     */
    public function inbound(Request $request): ?Response
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
    public function outbound(Request $request, Response $response): void
    {
        if (!$request->headers->has('Origin')) {
            return;
        }

        $options = $request->attributes->get('_cors', []);

        $origin = $this->getAllowedOrigin($request, $options);
        if (!$origin) {
            return;
        }

        $headers = [
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => $options['allow_credentials'] ? 'true' : null,
            'Vary' => 'Origin',
        ];

        if ($request->isMethod('OPTIONS')) {
            $methods = $this->getAllowedMethods($request, $options);
            if ($methods) {
                $headers['Access-Control-Allow-Methods'] = $methods;
            } else {
                return;
            }

            $headers['Access-Control-Allow-Headers'] = $this->getAllowedHeaders($request, $options);
            $headers['Access-Control-Max-Age'] = $options['max_age'];
        } elseif ($options['expose_headers']) {
            $headers['Access-Control-Expose-Headers'] = $this->asString($options['expose_headers']);
        }

        $response->headers->add(array_filter($headers));
    }

    private function getAllowedOrigin(Request $request, array $options): ?string
    {
        $origin = $request->headers->get('Origin', '');
        $allowed = $options['allow_origin'] ?? '';

        return $this->verifyHeader($origin, $allowed) ? $origin : null;
    }

    private function getAllowedMethods(Request $request, array $options): ?string
    {
        $requestMethod = $request->headers->get('Access-Control-Request-Method', '');
        if ($this->verifyHeader($requestMethod, $options['allow_methods'])) {
            return $this->asString($options['allow_methods']);
        }

        return null;
    }

    private function getAllowedHeaders(Request $request, array $options): string
    {
        $requestHeaders = explode(
            ',',
            $request->headers->get('Access-Control-Request-Headers', '')
        );
        $allowedHeaders = array_filter(
            $requestHeaders,
            fn($h) => $this->verifyHeader($h, $options['allow_headers'])
        );

        return $this->asString($allowedHeaders);
    }

    private function verifyHeader(string $value, string|array $allowed): bool
    {
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
