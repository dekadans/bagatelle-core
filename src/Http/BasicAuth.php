<?php

declare(strict_types=1);

namespace tthe\Bagatelle\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use tthe\Bagatelle\Auth\AuthenticatorInterface;
use tthe\Bagatelle\Middleware\MiddlewareInterface;

/**
 * Middleware for Basic Authentication.
 */
class BasicAuth implements MiddlewareInterface
{
    private string $realm = '';

    public function __construct(private AuthenticatorInterface $auth) {}

    /**
     * Checks authentication using the provided Authenticator service and returns 401 on failure.
     */
    public function inbound(Request $request, array $options): ?Response
    {
        $this->realm = $options['realm'] ?? 'Protected content';
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

    // No outbound processing.
    public function outbound(Request $request, Response $response, array $options): void {}
}
