<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use tthe\Bagatelle\Auth\AuthenticatorInterface;
use tthe\Bagatelle\Http\BasicAuthHandler;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeAuthRequest(?string $user = null, ?string $password = null): Request
{
    $server = [];

    if ($user !== null || $password !== null) {
        $server['PHP_AUTH_USER'] = $user ?? '';
        $server['PHP_AUTH_PW']   = $password ?? '';
    }

    return Request::create('/protected', 'GET', [], [], [], $server);
}

function mockAuthenticator(?array $returnValue): AuthenticatorInterface
{
    $mock = Mockery::mock(AuthenticatorInterface::class);
    $mock->allows('authenticate')->andReturn($returnValue);
    return $mock;
}

// ---------------------------------------------------------------------------
// Missing credentials
// ---------------------------------------------------------------------------

describe('missing credentials', function () {
    it('throws UnauthorizedHttpException when no credentials are provided', function () {
        $handler = new BasicAuthHandler('Test Realm', mockAuthenticator(['id' => 1]));
        $request = makeAuthRequest(); // no PHP_AUTH_USER header

        expect(fn() => $handler->inbound($request))
            ->toThrow(UnauthorizedHttpException::class);
    });

    it('includes WWW-Authenticate header when credentials are absent', function () {
        $handler = new BasicAuthHandler('Secured Content', mockAuthenticator(null));

        try {
            $handler->inbound(makeAuthRequest());
        } catch (UnauthorizedHttpException $e) {
            expect($e->getHeaders()['WWW-Authenticate'])->toBe('Basic realm="Secured Content"');
        }
    });
});

// ---------------------------------------------------------------------------
// Invalid credentials
// ---------------------------------------------------------------------------

describe('invalid credentials', function () {
    it('throws UnauthorizedHttpException when authenticator returns null', function () {
        $handler = new BasicAuthHandler('Test Realm', mockAuthenticator(null));
        $request = makeAuthRequest('alice', 'wrong-password');

        expect(fn() => $handler->inbound($request))
            ->toThrow(UnauthorizedHttpException::class);
    });

    it('passes the provided username and password to the authenticator', function () {
        $mock = Mockery::mock(AuthenticatorInterface::class);
        $mock->expects('authenticate')
            ->with('alice', 'secret')
            ->once()
            ->andReturn(null);

        $handler = new BasicAuthHandler('Test Realm', $mock);

        expect(fn() => $handler->inbound(makeAuthRequest('alice', 'secret')))
            ->toThrow(UnauthorizedHttpException::class);
    });
});

// ---------------------------------------------------------------------------
// Successful authentication
// ---------------------------------------------------------------------------

describe('successful authentication', function () {
    it('returns null (passes to next middleware) on success', function () {
        $attributes = ['id' => 42, 'name' => 'Alice'];
        $handler    = new BasicAuthHandler('Test Realm', mockAuthenticator($attributes));
        $request    = makeAuthRequest('alice', 'correct-password');

        expect($handler->inbound($request))->toBeNull();
    });

    it('stores user attributes on the request under auth.user', function () {
        $attributes = ['id' => 42, 'name' => 'Alice'];
        $handler    = new BasicAuthHandler('Test Realm', mockAuthenticator($attributes));
        $request    = makeAuthRequest('alice', 'correct-password');

        $handler->inbound($request);

        expect($request->attributes->get('auth.user'))->toBe($attributes);
    });

    it('stores authentication scheme on the request under auth.scheme', function () {
        $handler    = new BasicAuthHandler('Test Realm', mockAuthenticator([]));
        $request    = makeAuthRequest('alice', 'correct-password');

        $handler->inbound($request);

        expect($request->attributes->get('auth.scheme'))->toBe('Basic');
    });
});
