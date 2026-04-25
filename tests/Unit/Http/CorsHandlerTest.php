<?php

declare(strict_types=1);

use tthe\Bagatelle\Http\CorsHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Build a CorsHandler with the given container-level options.
 */
function makeHandler(array $options = []): CorsHandler
{
    return new CorsHandler($options);
}

/**
 * Build a Request, optionally attaching route-level _cors attributes.
 */
function makeRequest(
    string $method = 'GET',
    array  $headers = [],
    array  $corsAttributes = [],
): Request {
    $request = Request::create('https://example.com/api', $method);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    if ($corsAttributes) {
        $request->attributes->set('_cors', $corsAttributes);
    }

    return $request;
}

describe('inbound()', function () {

    it('returns a 204 response for OPTIONS requests with an Origin header', function () {
        $handler  = makeHandler();
        $request  = makeRequest('OPTIONS', ['Origin' => 'https://client.example.com']);

        $response = $handler->inbound($request);

        expect($response)->toBeInstanceOf(Response::class)
            ->and($response->getStatusCode())->toBe(204)
            ->and($response->getContent())->toBe('');
    });

    it('returns null for OPTIONS requests without an Origin header', function () {
        $handler  = makeHandler();
        $request  = makeRequest('OPTIONS');

        expect($handler->inbound($request))->toBeNull();
    });

    it('returns null for non-OPTIONS requests even with an Origin header', function () {
        $handler = makeHandler();

        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $request = makeRequest($method, ['Origin' => 'https://client.example.com']);
            expect($handler->inbound($request))->toBeNull();
        }
    });

});

describe('outbound() — early exits', function () {

    it('does nothing when there is no Origin header', function () {
        $handler  = makeHandler(['allow_origin' => '*']);
        $request  = makeRequest('GET');          // no Origin
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse()
            ->and($response->headers->has('Vary'))->toBeFalse();
    });

    it('sets Vary: Origin even when the origin is not allowed', function () {
        $handler  = makeHandler(['allow_origin' => 'https://allowed.example.com']);
        $request  = makeRequest('GET', ['Origin' => 'https://other.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Vary'))->toBe('Origin')
            ->and($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });

});

describe('outbound() — simple requests', function () {

    it('reflects the request origin when allow_origin is a wildcard', function () {
        $handler  = makeHandler(['allow_origin' => '*']);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->toBe('https://client.example.com');
    });

    it('reflects the request origin when it matches an explicit allowed origin', function () {
        $handler  = makeHandler(['allow_origin' => ['https://client.example.com', 'https://other.example.com']]);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->toBe('https://client.example.com');
    });

    it('does not set CORS headers when origin is not in the allow list', function () {
        $handler  = makeHandler(['allow_origin' => ['https://allowed.example.com']]);
        $request  = makeRequest('GET', ['Origin' => 'https://evil.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
    });

    it('sets Access-Control-Allow-Credentials: true when allow_credentials is true', function () {
        $handler  = makeHandler(['allow_origin' => '*', 'allow_credentials' => true]);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
    });

    it('omits Access-Control-Allow-Credentials when allow_credentials is false', function () {
        $handler  = makeHandler(['allow_origin' => '*', 'allow_credentials' => false]);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
    });

    it('sets Access-Control-Expose-Headers on non-preflight responses', function () {
        $handler  = makeHandler([
            'allow_origin'   => '*',
            'expose_headers' => ['X-Custom-Header', 'X-Another-Header'],
        ]);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Expose-Headers'))
            ->toBe('X-Custom-Header, X-Another-Header');
    });

    it('omits Access-Control-Expose-Headers when expose_headers is empty', function () {
        $handler  = makeHandler(['allow_origin' => '*', 'expose_headers' => []]);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Expose-Headers'))->toBeFalse();
    });

    it('always sets Vary: Origin on allowed requests', function () {
        $handler  = makeHandler(['allow_origin' => '*']);
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Vary'))->toBe('Origin');
    });

});

describe('outbound() — preflight requests', function () {

    it('adds preflight headers when method and all headers are allowed', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['GET', 'POST'],
            'allow_headers' => ['Content-Type', 'Authorization'],
            'max_age'       => 3600,
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                         => 'https://client.example.com',
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, Authorization',
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Methods'))
            ->toBe('GET, POST')
            ->and($response->headers->get('Access-Control-Allow-Headers'))
            ->toBe('Content-Type, Authorization')
            ->and($response->headers->get('Access-Control-Max-Age'))
            ->toBe('3600');
    });

    it('returns without CORS headers when the requested method is not allowed', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['GET'],
            'allow_headers' => ['Content-Type'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://client.example.com',
            'Access-Control-Request-Method' => 'DELETE',
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Methods'))->toBeFalse();
    });

    it('returns without CORS headers when a requested header is not allowed', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['POST'],
            'allow_headers' => ['Content-Type'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                         => 'https://client.example.com',
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type, X-Not-Allowed',
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Headers'))->toBeFalse();
    });

    it('allows a preflight with no Access-Control-Request-Headers header', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['GET'],
            'allow_headers' => ['Content-Type'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://client.example.com',
            'Access-Control-Request-Method' => 'GET',
            // No Access-Control-Request-Headers
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Origin'))->toBeTrue();
        expect($response->headers->has('Access-Control-Allow-Headers'))->toBeFalse();
    });

    it('handles header names case-insensitively', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['POST'],
            'allow_headers' => ['content-type'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                         => 'https://client.example.com',
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Headers'))
            ->toBe('content-type');
    });

    it('handles whitespace in Access-Control-Request-Headers values', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['POST'],
            'allow_headers' => ['content-type', 'authorization'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                         => 'https://client.example.com',
            'Access-Control-Request-Method'  => 'POST',
            'Access-Control-Request-Headers' => 'content-type,  authorization', // extra space
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Headers'))
            ->toBe('content-type, authorization');
    });

    it('does not set Access-Control-Expose-Headers on preflight responses', function () {
        $handler = makeHandler([
            'allow_origin'   => '*',
            'allow_methods'  => ['GET'],
            'allow_headers'  => ['Content-Type'],
            'expose_headers' => ['X-Custom'],
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://client.example.com',
            'Access-Control-Request-Method' => 'GET',
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Expose-Headers'))->toBeFalse();
    });

    it('omits Access-Control-Max-Age when max_age is null', function () {
        $handler = makeHandler([
            'allow_origin'  => '*',
            'allow_methods' => ['GET'],
            'allow_headers' => [],
            'max_age'       => null,
        ]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://client.example.com',
            'Access-Control-Request-Method' => 'GET',
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Max-Age'))->toBeFalse();
    });

});

describe('option merging', function () {

    it('route-level options override container-level options', function () {
        $handler = makeHandler([
            'allow_origin'  => ['https://container.example.com'],
            'allow_methods' => ['GET'],
        ]);
        $request = makeRequest('GET', ['Origin' => 'https://route.example.com'], [
            'allow_origin' => ['https://route.example.com'],
        ]);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->toBe('https://route.example.com');
    });

    it('preserves container-level options not overridden by the route', function () {
        $handler = makeHandler([
            'allow_origin'      => '*',
            'allow_credentials' => true,
        ]);
        // Route provides no _cors attributes — container options should hold
        $request  = makeRequest('GET', ['Origin' => 'https://client.example.com']);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Allow-Credentials'))->toBe('true');
    });

    it('null route options do not override non-null container options', function () {
        $handler = makeHandler(['allow_origin' => '*', 'allow_methods' => ['GET'], 'max_age' => 600]);
        $request = makeRequest('OPTIONS', [
            'Origin'                        => 'https://client.example.com',
            'Access-Control-Request-Method' => 'GET',
        ], [
            'max_age' => null, // explicitly null at route level — should not override
        ]);
        $response = new Response('', 204);

        $handler->outbound($request, $response);

        expect($response->headers->get('Access-Control-Max-Age'))->toBe('600');
    });

    it('false allow_credentials at route level overrides true at container level', function () {
        $handler = makeHandler(['allow_origin' => '*', 'allow_credentials' => true]);
        $request = makeRequest('GET', ['Origin' => 'https://client.example.com'], [
            'allow_credentials' => false,
        ]);
        $response = new Response();

        $handler->outbound($request, $response);

        expect($response->headers->has('Access-Control-Allow-Credentials'))->toBeFalse();
    });

});
