<?php

namespace tthe\Bagatelle\Auth;

interface AuthenticatorInterface
{
    /**
     * Authenticate a user ID and secret.
     * Returns an array of attributes, or NULL on failure.
     *
     * @param string $identifier
     * @param string $secret
     * @return array|null
     */
    public function authenticate(string $identifier, string $secret): ?array;
}
