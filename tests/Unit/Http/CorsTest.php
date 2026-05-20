<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Http\CORS;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeCors(array $options = []): CORS
{
    return new CORS(array_merge([
        'allow_origin'      => ['https://example.com'],
        'allow_methods'     => ['GET', 'POST'],
        'allow_headers'     => ['Content-Type', 'Authorization'],
        'expose_headers'    => [],
        'allow_credentials' => false,
        'max_age'           => null,
    ], $options));
}

function makeRequest(
    string $method = 'GET',
    string $origin = 'https://example.com',
    array  $headers = []
): Request {
    $request = Request::create('/', $method);
    if ($origin !== '') {
        $request->headers->set('Origin', $origin);
    }
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    return $request;
}

// ---------------------------------------------------------------------------
// configure()
// ---------------------------------------------------------------------------

describe('configure()', function () {
    it('adds OPTIONS to existing route methods', function () {
        $route = new Route('/', [], [], [], '', [], ['GET', 'POST']);
        $options = [];

        CORS::configure($route, $options);

        expect($route->getMethods())->toContain('OPTIONS');
    });

    it('does not duplicate OPTIONS when it is already present', function () {
        $route = new Route('/', [], [], [], '', [], ['GET', 'OPTIONS']);
        $options = [];

        CORS::configure($route, $options);

        expect(array_count_values($route->getMethods())['OPTIONS'])->toBe(1);
    });

    it('sets allow_methods from route methods when not already in options', function () {
        $route = new Route('/', [], [], [], '', [], ['GET', 'POST']);
        $options = [];

        CORS::configure($route, $options);

        expect($options['allow_methods'])->toBe(['GET', 'POST']);
    });

    it('does not override allow_methods when already set in options', function () {
        $route = new Route('/', [], [], [], '', [], ['GET', 'POST']);
        $options = ['allow_methods' => ['DELETE']];

        CORS::configure($route, $options);

        expect($options['allow_methods'])->toBe(['DELETE']);
    });

    it('sets allow_methods to wildcard when route has no methods', function () {
        $route = new Route('/');
        $options = [];

        CORS::configure($route, $options);

        expect($options['allow_methods'])->toBe('*');
    });
});

// ---------------------------------------------------------------------------
// inbound()
// ---------------------------------------------------------------------------

describe('inbound()', function () {
    it('returns a 204 response for OPTIONS preflight with Origin header', function () {
        $cors     = makeCors();
        $request  = makeRequest('OPTIONS');

        $response = $cors->inbound($request, []);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(204);
    });

    it('returns null for OPTIONS without Origin header', function () {
        $cors    = makeCors();
        $request = Request::create('/', 'OPTIONS');   // no Origin

        expect($cors->inbound($request, []))->toBeNull();
    });

    it('returns null for non-OPTIONS requests with Origin header', function () {
        $cors    = makeCors();
        $request = makeRequest('GET');

        expect($cors->inbound($request, []))->toBeNull();
    });

    it('returns null for plain GET requests without Origin header', function () {
        $cors    = makeCors();
        $request = Request::create('/', 'GET');

        expect($cors->inbound($request, []))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// outbound() – no Origin
// ---------------------------------------------------------------------------

describe('outbound() without Origin', function () {
    it('does not add any CORS headers when Origin is absent', function () {
        $cors     = makeCors();
        $request  = Request::create('/', 'GET');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// outbound() – simple (non-preflight) requests
// ---------------------------------------------------------------------------

describe('outbound() for simple requests', function () {
    it('sets Access-Control-Allow-Origin for an allowed origin', function () {
        $cors     = makeCors();
        $request  = makeRequest('GET');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->toBe('https://example.com');
    });

    it('always sets the Vary: Origin header when Origin is present', function () {
        $cors     = makeCors();
        $request  = makeRequest('GET');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Vary'))->toBe('Origin');
    });

    it('does not set CORS headers for a disallowed origin', function () {
        $cors     = makeCors(['allow_origin' => ['https://allowed.com']]);
        $request  = makeRequest('GET', 'https://evil.com');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });

    it('sets Access-Control-Allow-Credentials to true when configured', function () {
        $cors     = makeCors(['allow_credentials' => true]);
        $request  = makeRequest('GET');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
    });

    it('omits Access-Control-Allow-Credentials when false', function () {
        $cors     = makeCors(['allow_credentials' => false]);
        $request  = makeRequest('GET');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
    });

    it('sets Access-Control-Expose-Headers for non-preflight requests', function () {
        $cors     = makeCors(['expose_headers' => ['X-Custom-Header', 'X-Another']]);
        $request  = makeRequest('GET');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Expose-Headers'))
            ->toBe('X-Custom-Header, X-Another');
    });

    it('accepts wildcard origin configuration', function () {
        $cors     = makeCors(['allow_origin' => '*']);
        $request  = makeRequest('GET', 'https://anywhere.io');
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->toBe('https://anywhere.io');
    });
});

// ---------------------------------------------------------------------------
// outbound() – preflight (OPTIONS) requests
// ---------------------------------------------------------------------------

describe('outbound() for preflight requests', function () {
    it('sets Allow-Methods and Allow-Headers for a valid preflight', function () {
        $cors    = makeCors();
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Allow-Methods'))
            ->toBe('GET, POST')
            ->and($response->headers->get('Access-Control-Allow-Headers'))
            ->toBe('Content-Type, Authorization');
    });

    it('sets Access-Control-Max-Age when configured', function () {
        $cors    = makeCors(['max_age' => 3600]);
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Max-Age'))->toBe('3600');
    });

    it('returns no CORS headers for a disallowed request method', function () {
        $cors    = makeCors(['allow_methods' => ['GET']]);
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'DELETE',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->has('Access-Control-Allow-Methods'))->toBeFalse();
    });

    it('returns no CORS headers for a disallowed request header', function () {
        $cors    = makeCors(['allow_headers' => ['Content-Type']]);
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'X-Evil-Header',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->has('Access-Control-Allow-Headers'))->toBeFalse();
    });

    it('returns empty Allow-Headers when Access-Control-Request-Headers is absent', function () {
        $cors    = makeCors();
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method' => 'GET',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->get('Access-Control-Allow-Headers'))->toBeNull();
    });

    it('does not set Expose-Headers on a preflight response', function () {
        $cors    = makeCors(['expose_headers' => ['X-Custom-Header']]);
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, []);

        expect($response->headers->has('Access-Control-Expose-Headers'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// outbound() – route-level option overrides
// ---------------------------------------------------------------------------

describe('outbound() route-level option overrides', function () {
    it('route options override container options', function () {
        $cors    = makeCors(['max_age' => 600]);
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response();

        $cors->outbound($request, $response, ['max_age' => 9999]);

        expect($response->headers->get('Access-Control-Max-Age'))->toBe('9999');
    });

    it('null route option values do not override container options', function () {
        $cors    = makeCors(['max_age' => 600]);
        $request = makeRequest('OPTIONS', 'https://example.com', [
            'Access-Control-Request-Method'  => 'GET',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response();

        // null is filtered out, so container's 600 should survive
        $cors->outbound($request, $response, ['max_age' => null]);

        expect($response->headers->get('Access-Control-Max-Age'))->toBe('600');
    });
});