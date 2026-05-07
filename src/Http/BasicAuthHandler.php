<?php

namespace tthe\Bagatelle\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use tthe\Bagatelle\Auth\AuthenticatorInterface;
use tthe\Bagatelle\Routing\Middleware;

/**
 * Middleware for Basic Authentication.
 * Bind your authenticator implementation to container entry `bagatelle.http.middleware.basic-auth.authenticator`.
 */
class BasicAuthHandler extends Middleware
{
    public function __construct(private string $realm, private AuthenticatorInterface $auth) {}

    public function inbound(Request $request): ?Response
    {
        $user = $request->getUser();
        $password = $request->getPassword();

        if ($user === null || empty($password)) {
            $this->fail('This resource requires authentication.');
        }

        $userAttributes = $this->auth->authenticate($user, $password);

        if ($userAttributes === null) {
            $this->fail('Invalid credentials.');
        }

        $request->attributes->add([
            'auth.scheme' => 'Basic',
            'auth.user' => $userAttributes,
        ]);

        return null;
    }

    private function fail(string $reason): never
    {
        throw new UnauthorizedHttpException("Basic realm=\"$this->realm\"", $reason);
    }
}
