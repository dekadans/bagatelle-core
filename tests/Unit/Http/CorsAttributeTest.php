<?php

declare(strict_types=1);

use tthe\Bagatelle\Http\CORS;
use Symfony\Component\Routing\Route;
use tthe\Bagatelle\Http\CorsHandler;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// Constructor – default values (no env, no arguments)
// ---------------------------------------------------------------------------

describe('CORS defaults (no env vars, no arguments)', function () {

    beforeEach(function () {
        // Ensure a clean environment for every test.
        foreach ([
            'CORS_ALLOW_ORIGIN',
            'CORS_ALLOW_METHODS',
            'CORS_ALLOW_HEADERS',
            'CORS_EXPOSE_HEADERS',
            'CORS_ALLOW_CREDENTIALS',
            'CORS_MAX_AGE',
        ] as $key) {
            unset($_ENV[$key]);
        }
    });

    it('defaults origin to wildcard', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_origin'])->toBe('*');
    });

    it('defaults methods to all common HTTP verbs', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_methods'])
            ->toBe(['GET', 'HEAD', 'POST', 'OPTIONS', 'PUT', 'PATCH', 'DELETE']);
    });

    it('defaults headers to wildcard', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_headers'])->toBe('*');
    });

    it('defaults expose_headers to empty string', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['expose_headers'])->toBe('');
    });

    it('defaults credentials to false', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_credentials'])->toBeFalse();
    });

    it('defaults max_age to null', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['max_age'])->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Constructor – explicit argument values
// ---------------------------------------------------------------------------

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
        $cors = new CORS(headers: 'Content-Type, Authorization');
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_headers'])->toBe('Content-Type, Authorization');
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

// ---------------------------------------------------------------------------
// Constructor – env var fallbacks
// ---------------------------------------------------------------------------

describe('CORS constructor falls back to env vars', function () {

    afterEach(function () {
        foreach ([
            'CORS_ALLOW_ORIGIN',
            'CORS_ALLOW_METHODS',
            'CORS_ALLOW_HEADERS',
            'CORS_EXPOSE_HEADERS',
            'CORS_ALLOW_CREDENTIALS',
            'CORS_MAX_AGE',
        ] as $key) {
            unset($_ENV[$key]);
        }
    });

    it('reads CORS_ALLOW_ORIGIN from env and splits on comma', function () {
        $_ENV['CORS_ALLOW_ORIGIN'] = 'https://a.com, https://b.com';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_origin'])
            ->toBe(['https://a.com', 'https://b.com']);
    });

    it('trims whitespace from env-parsed origins', function () {
        $_ENV['CORS_ALLOW_ORIGIN'] = '  https://a.com  ,  https://b.com  ';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_origin'])
            ->toBe(['https://a.com', 'https://b.com']);
    });

    it('reads CORS_ALLOW_METHODS from env', function () {
        $_ENV['CORS_ALLOW_METHODS'] = 'GET, POST';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_methods'])->toBe(['GET', 'POST']);
    });

    it('reads CORS_ALLOW_HEADERS from env', function () {
        $_ENV['CORS_ALLOW_HEADERS'] = 'Content-Type, X-Auth';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_headers'])->toBe(['Content-Type', 'X-Auth']);
    });

    it('reads CORS_EXPOSE_HEADERS from env', function () {
        $_ENV['CORS_EXPOSE_HEADERS'] = 'X-Rate-Limit';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['expose_headers'])->toBe(['X-Rate-Limit']);
    });

    it('reads CORS_ALLOW_CREDENTIALS as bool', function () {
        $_ENV['CORS_ALLOW_CREDENTIALS'] = '1';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['allow_credentials'])->toBeTrue();
    });

    it('reads CORS_MAX_AGE as int', function () {
        $_ENV['CORS_MAX_AGE'] = '86400';
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_cors')['max_age'])->toBe(86400);
    });

    it('explicit argument overrides env var for origin', function () {
        $_ENV['CORS_ALLOW_ORIGIN'] = 'https://env.com';
        $cors = new CORS(origin: 'https://explicit.com');
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_origin'])->toBe('https://explicit.com');
    });

    it('explicit credentials arg overrides env var', function () {
        $_ENV['CORS_ALLOW_CREDENTIALS'] = '0';
        $cors = new CORS(credentials: true);
        $route = decoratedRoute($cors);
        expect($route->getDefault('_cors')['allow_credentials'])->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// decorate() – route defaults
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// decorate() – OPTIONS method injection
// ---------------------------------------------------------------------------

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

// ---------------------------------------------------------------------------
// decorate() – CorsHandler middleware registration
// ---------------------------------------------------------------------------

describe('CORS::decorate() registers CorsHandler middleware', function () {

    it('adds CorsHandler middleware to the route', function () {
        $route = decoratedRoute(new CORS());
        expect($route->getDefault('_middleware'))->toBe([CorsHandler::class]);
    });

    it('keeps previous middleware registered', function () {
        $route = new Route('/test');
        $route->setDefault('_middleware', ['TestMiddleware']);
        new CORS()->decorate($route);
        expect($route->getDefault('_middleware'))->toBe(['TestMiddleware', CorsHandler::class]);
    });
});

// ---------------------------------------------------------------------------
// PHP Attribute metadata
// ---------------------------------------------------------------------------

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
