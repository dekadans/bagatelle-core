<?php

namespace tthe\Bagatelle\Auth;

/**
 * Very simple authentication, reading usernames and hashed passwords from environment variables.
 * Supports both the PHP password_hash() format and plain SHA-256 hashes.
 */
class EnvironmentAuthenticator implements AuthenticatorInterface
{
    private array $environment;

    /**
     * @param array $envVars Associative array with environment variables: ['USER_NAME_VAR' => 'USER_HASH_VAR']
     */
    public function __construct(array $envVars)
    {
        $this->environment = $this->resolve($envVars);
    }

    public function authenticate(string $identifier, string $secret): ?array
    {
        $hash = $this->environment[$identifier] ?? '';

        if (password_get_info($hash)['algo']) {
            $result = password_verify($secret, $hash);
        } else {
            $result = hash_equals($hash, hash('sha256', $secret));
        }

        return $result ? ['id' => $identifier] : null;
    }

    private function resolve(array $data): array
    {
        $resolved = [];
        foreach ($data as $userVar => $passwordVar) {
            $user = $_ENV[$userVar] ?? null;
            $hash = $_ENV[$passwordVar] ?? null;

            if ($user === null || $hash === null) {
                continue;
            }

            $resolved[$user] = $hash;
        }

        return $resolved;
    }
}
