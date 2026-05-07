<?php

declare(strict_types=1);

use tthe\Bagatelle\Http\CORS;
use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Http\CorsHandler;

/**
 * Return a Route that has had CORS::decorate() applied to it.
 */
function decoratedRoute(CORS $cors, array $existingMethods = []): Route
{
    $route = new Route('/test');
    if ($existingMethods) {
        $route->setMethods($existingMethods);
    }
    $cors->decorate($route);
    return $route;
}

describe('CORS default route options', function () {

    it('uses default values', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_origin'])->toBeNull();
        expect($route->getDefault('_cors')['allow_methods'])->toBe('*');
        expect($route->getDefault('_cors')['allow_headers'])->toBeNull();
        expect($route->getDefault('_cors')['expose_headers'])->toBeNull();
        expect($route->getDefault('_cors')['allow_credentials'])->toBeNull();
        expect($route->getDefault('_cors')['max_age'])->toBeNull();
    });
});

describe('CORS constructor with explicit arguments', function () {

    it('accepts a single origin string', function () {
        $cors = new CORS(origin: 'https://example.com');
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_origin'])->toBe('https://example.com');
    });

    it('accepts an array of origins', function () {
        $origins = ['https://a.com', 'https://b.com'];
        $cors = new CORS(origin: $origins);
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_origin'])->toBe($origins);
    });

    it('accepts a custom methods array', function () {
        $cors = new CORS(methods: ['GET', 'POST']);
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_methods'])->toBe(['GET', 'POST']);
    });

    it('accepts a custom headers string', function () {
        $cors = new CORS(headers: '*');
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_headers'])->toBe('*');
    });

    it('accepts an exposeHeaders array', function () {
        $cors = new CORS(exposeHeaders: ['X-Custom-Header']);
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['expose_headers'])->toBe(['X-Custom-Header']);
    });

    it('accepts credentials true', function () {
        $cors = new CORS(credentials: true);
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_credentials'])->toBeTrue();
    });

    it('accepts a maxAge integer', function () {
        $cors = new CORS(maxAge: 3600);
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['max_age'])->toBe(3600);
    });
});

describe('CORS::decorate() sets _cors route defaults', function () {

    it('sets _cors default on the route', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors'))->toBeArray();
    });

    it('_cors contains all six expected keys', function () {
        $route = decoratedRoute(new CORS());
        $cors  = $route->getDefault('_cors');

        expect($cors)->toHaveKeys([
            'allow_origin',
            'allow_methods',
            'allow_headers',
            'expose_headers',
            'allow_credentials',
            'max_age',
        ]);
    });
});

describe('CORS::decorate() injects OPTIONS method', function () {

    it('adds OPTIONS when route has explicit methods without OPTIONS', function () {
        $route = decoratedRoute(new CORS(), ['GET', 'POST']);
        expect($route->getMethods())->toContain('OPTIONS');
    });

    it('preserves original methods alongside OPTIONS', function () {
        $route = decoratedRoute(new CORS(), ['GET', 'POST']);
        expect($route->getMethods())->toContain('GET')->toContain('POST');
    });

    it('does not duplicate OPTIONS when it is already present', function () {
        $route = decoratedRoute(new CORS(), ['GET', 'OPTIONS']);
        $count = count(array_filter($route->getMethods(), fn($m) => $m === 'OPTIONS'));
        expect($count)->toBe(1);
    });

    it('does not alter methods when the route has no method constraint', function () {
        // A route with no methods set allows all – decorate() should not add OPTIONS.
        $route = decoratedRoute(new CORS(), []);
        expect($route->getMethods())->toBeEmpty();
    });
});

describe('CORS::decorate() registers CorsHandler middleware', function () {

    it('adds CorsHandler middleware to the route', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('bagatelle.middleware'))->toBe([CorsHandler::class]);
    });

    it('keeps previous middleware registered', function () {
        $route = new Route('/test');
        $route->setDefault('bagatelle.middleware', ['TestMiddleware']);
        new CORS()->decorate($route);
        expect($route->getDefault('bagatelle.middleware'))->toBe(['TestMiddleware', CorsHandler::class]);
    });
});

describe('CORS PHP attribute declaration', function () {

    it('is declared as a PHP attribute', function () {
        $reflection = new \ReflectionClass(CORS::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        expect($attributes)->not->toBeEmpty();
    });

    it('targets both CLASS and METHOD', function () {
        $reflection = new \ReflectionClass(CORS::class);
        $attribute  = $reflection->getAttributes(\Attribute::class)[0];
        $flags      = $attribute->getArguments()[0];

        expect($flags & \Attribute::TARGET_CLASS)->not->toBe(0);
        expect($flags & \Attribute::TARGET_METHOD)->not->toBe(0);
    });
});
