<?php

declare(strict_types=1);

use tthe\Bagatelle\Http\CorsHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Build a Request with the given headers and optional method / CORS attributes.
 */
function makeRequest(
    string $method = 'GET',
    array  $headers = [],
    array  $corsOptions = [],
): Request {
    $request = Request::create('/test', $method);
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    if ($corsOptions) {
        $request->attributes->set('_cors', $corsOptions);
    }
    return $request;
}

/**
 * Default, permissive CORS options – mirrors the CORS attribute defaults.
 */
function defaultOptions(array $overrides = []): array
{
    return array_merge([
        'allow_origin'      => '*',
        'allow_methods'     => ['GET', 'HEAD', 'POST', 'OPTIONS', 'PUT', 'PATCH', 'DELETE'],
        'allow_headers'     => '*',
        'expose_headers'    => '',
        'allow_credentials' => false,
        'max_age'           => null,
    ], $overrides);
}

function handler(): CorsHandler
{
    return new CorsHandler();
}

// ---------------------------------------------------------------------------
// inbound()
// ---------------------------------------------------------------------------

describe('CorsHandler::inbound()', function () {

    it('returns a 204 empty response for OPTIONS preflight with Origin header', function () {
        $request  = makeRequest('OPTIONS', ['Origin' => 'https://example.com']);
        $response = handler()->inbound($request);

        expect($response)->toBeInstanceOf(Response::class);
        expect($response->getStatusCode())->toBe(204);
        expect($response->getContent())->toBe('');
    });

    it('returns null for OPTIONS without an Origin header', function () {
        $request = makeRequest('OPTIONS');
        expect(handler()->inbound($request))->toBeNull();
    });

    it('returns null for a GET request with an Origin header', function () {
        $request = makeRequest('GET', ['Origin' => 'https://example.com']);
        expect(handler()->inbound($request))->toBeNull();
    });

    it('returns null for a POST request with an Origin header', function () {
        $request = makeRequest('POST', ['Origin' => 'https://example.com']);
        expect(handler()->inbound($request))->toBeNull();
    });

    it('returns null for a request with no Origin and no OPTIONS', function () {
        $request = makeRequest('GET');
        expect(handler()->inbound($request))->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// outbound() – requests without Origin
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – no Origin header', function () {

    it('adds no CORS headers when Origin is absent', function () {
        $request  = makeRequest('GET', [], defaultOptions());
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// outbound() – origin validation
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – origin validation', function () {

    it('sets Access-Control-Allow-Origin to the request origin when allow_origin is wildcard', function () {
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], defaultOptions());
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://example.com');
    });

    it('sets Access-Control-Allow-Origin when origin is in the allowed array', function () {
        $options  = defaultOptions(['allow_origin' => ['https://a.com', 'https://b.com']]);
        $request  = makeRequest('GET', ['Origin' => 'https://a.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://a.com');
    });

    it('adds no CORS headers when origin is not in the allowed array', function () {
        $options  = defaultOptions(['allow_origin' => ['https://allowed.com']]);
        $request  = makeRequest('GET', ['Origin' => 'https://other.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });

    it('performs case-insensitive origin comparison', function () {
        $options  = defaultOptions(['allow_origin' => ['https://Example.COM']]);
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://example.com');
    });

    it('sets Access-Control-Allow-Origin when allow_origin is an exact matching string', function () {
        $options  = defaultOptions(['allow_origin' => 'https://exact.com']);
        $request  = makeRequest('GET', ['Origin' => 'https://exact.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))->toBe('https://exact.com');
    });

    it('rejects origin when allow_origin is a non-matching string', function () {
        $options  = defaultOptions(['allow_origin' => 'https://exact.com']);
        $request  = makeRequest('GET', ['Origin' => 'https://other.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// outbound() – Vary header
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – Vary header', function () {

    it('sets the Vary: Origin header for simple requests', function () {
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], defaultOptions());
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Vary'))->toBe('Origin');
    });
});

// ---------------------------------------------------------------------------
// outbound() – credentials
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – credentials', function () {

    it('omits Access-Control-Allow-Credentials when credentials is false', function () {
        $options  = defaultOptions(['allow_credentials' => false]);
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
    });

    it('sets Access-Control-Allow-Credentials: true when credentials is true', function () {
        $options  = defaultOptions(['allow_credentials' => true]);
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
    });
});

// ---------------------------------------------------------------------------
// outbound() – expose_headers (non-preflight)
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – expose_headers', function () {

    it('sets Access-Control-Expose-Headers for simple requests when configured', function () {
        $options  = defaultOptions(['expose_headers' => ['X-Rate-Limit', 'X-Request-Id']]);
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Expose-Headers'))
            ->toBe('X-Rate-Limit, X-Request-Id');
    });

    it('omits Access-Control-Expose-Headers when expose_headers is empty', function () {
        $options  = defaultOptions(['expose_headers' => '']);
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Expose-Headers'))->toBeFalse();
    });

    it('omits Access-Control-Expose-Headers for OPTIONS preflight requests', function () {
        $options = defaultOptions([
            'expose_headers' => ['X-Custom'],
            'allow_methods'  => ['GET', 'POST'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                         => 'https://example.com',
            'Access-Control-Request-Method'  => 'GET',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Expose-Headers'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// outbound() – preflight (OPTIONS) method validation
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – preflight method validation', function () {

    it('sets Access-Control-Allow-Methods for a valid preflight request', function () {
        $options = defaultOptions(['allow_methods' => ['GET', 'POST']]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'POST',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Methods'))->toBe('GET, POST');
    });

    it('adds no CORS headers when the requested method is not allowed', function () {
        $options = defaultOptions(['allow_methods' => ['GET']]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'DELETE',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Methods'))->toBeFalse();
        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });

    it('allows any method when allow_methods is wildcard', function () {
        $options = defaultOptions(['allow_methods' => '*']);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'DELETE',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Methods'))->toBe('*');
    });

    it('performs case-insensitive method comparison', function () {
        $options = defaultOptions(['allow_methods' => ['GET', 'POST']]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'post',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Methods'))->toBe('GET, POST');
    });
});

// ---------------------------------------------------------------------------
// outbound() – preflight (OPTIONS) header validation
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – preflight header validation', function () {

    it('returns all requested headers when allow_headers is wildcard', function () {
        $options = defaultOptions(['allow_headers' => '*']);
        $request = makeRequest('OPTIONS', [
            'Origin'                          => 'https://example.com',
            'Access-Control-Request-Method'   => 'POST',
            'Access-Control-Request-Headers'  => 'Content-Type,Authorization',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Headers'))
            ->toBe('Content-Type, Authorization');
    });

    it('filters to only the allowed subset of requested headers', function () {
        $options = defaultOptions(['allow_headers' => ['content-type']]);
        $request = makeRequest('OPTIONS', [
            'Origin'                          => 'https://example.com',
            'Access-Control-Request-Method'   => 'POST',
            'Access-Control-Request-Headers'  => 'Content-Type,Authorization',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Headers'))->toBe('Content-Type');
        expect($response->headers->get('Access-Control-Allow-Headers'))->not->toContain('Authorization');
    });

    it('sets Access-Control-Allow-Headers to empty string when no requested headers match', function () {
        $options = defaultOptions(['allow_headers' => ['x-custom']]);
        $request = makeRequest('OPTIONS', [
            'Origin'                          => 'https://example.com',
            'Access-Control-Request-Method'   => 'GET',
            'Access-Control-Request-Headers'  => 'Authorization',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        // Empty string is filtered by array_filter, so the header is absent.
        expect($response->headers->get('Access-Control-Allow-Headers', ''))->toBe('');
    });

    it('performs case-insensitive header comparison', function () {
        $options = defaultOptions(['allow_headers' => ['Content-Type']]);
        $request = makeRequest('OPTIONS', [
            'Origin'                          => 'https://example.com',
            'Access-Control-Request-Method'   => 'POST',
            'Access-Control-Request-Headers'  => 'content-type',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Headers'))->not->toBeEmpty();
    });
});

// ---------------------------------------------------------------------------
// outbound() – max_age
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – max_age', function () {

    it('sets Access-Control-Max-Age on a preflight response when configured', function () {
        $options = defaultOptions(['max_age' => 3600]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'GET',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->get('Access-Control-Max-Age'))->toBe('3600');
    });

    it('omits Access-Control-Max-Age when max_age is null', function () {
        $options = defaultOptions(['max_age' => null]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://example.com',
            'Access-Control-Request-Method' => 'GET',
        ], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Max-Age'))->toBeFalse();
    });

    it('does not set Access-Control-Max-Age on non-preflight responses', function () {
        $options  = defaultOptions(['max_age' => 3600]);
        $request  = makeRequest('GET', ['Origin' => 'https://example.com'], $options);
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Max-Age'))->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// outbound() – missing _cors attributes
// ---------------------------------------------------------------------------

describe('CorsHandler::outbound() – missing _cors route attribute', function () {

    it('adds no CORS headers when _cors route attribute is absent', function () {
        $request = makeRequest('GET', ['Origin' => 'https://example.com']);
        // No _cors set on request attributes – defaults to empty array.
        $response = new Response();
        handler()->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });
});
