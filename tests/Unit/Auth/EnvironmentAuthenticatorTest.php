<?php

use tthe\Bagatelle\Auth\EnvironmentAuthenticator;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Seed $_ENV and return a configured authenticator.
 *
 * @param array<string, string> $users  ['username' => 'plaintext-password']
 * @param 'bcrypt'|'sha256'     $format Hash format to store passwords in
 */
function makeAuthenticator(array $users, string $format = 'bcrypt'): EnvironmentAuthenticator
{
    $envVarMap = [];

    foreach ($users as $username => $password) {
        $userVar = 'AUTH_USER_' . strtoupper($username);
        $hashVar = 'AUTH_HASH_' . strtoupper($username);

        $_ENV[$userVar] = $username;
        $_ENV[$hashVar] = $format === 'bcrypt'
            ? password_hash($password, PASSWORD_BCRYPT)
            : hash('sha256', $password);

        $envVarMap[$userVar] = $hashVar;
    }

    return new EnvironmentAuthenticator($envVarMap);
}

// ---------------------------------------------------------------------------
// Successful authentication
// ---------------------------------------------------------------------------

describe('successful authentication', function () {

    it('authenticates a valid user with a bcrypt hash', function () {
        $auth = makeAuthenticator(['alice' => 's3cr3t'], 'bcrypt');

        expect($auth->authenticate('alice', 's3cr3t'))->toBe(['id' => 'alice']);
    });

    it('authenticates a valid user with a sha256 hash', function () {
        $auth = makeAuthenticator(['alice' => 's3cr3t'], 'sha256');

        expect($auth->authenticate('alice', 's3cr3t'))->toBe(['id' => 'alice']);
    });

    it('authenticates multiple independent users', function () {
        $auth = makeAuthenticator([
            'alice' => 'pass-a',
            'bob'   => 'pass-b',
        ]);

        expect($auth->authenticate('alice', 'pass-a'))->toBe(['id' => 'alice']);
        expect($auth->authenticate('bob', 'pass-b'))->toBe(['id' => 'bob']);
    });

    it('returns an array with the identifier as id', function () {
        $auth = makeAuthenticator(['alice' => 'pw']);

        $result = $auth->authenticate('alice', 'pw');

        expect($result)
            ->toBeArray()
            ->toHaveKey('id', 'alice');
    });

});

// ---------------------------------------------------------------------------
// Failed authentication
// ---------------------------------------------------------------------------

describe('failed authentication', function () {

    it('returns null for a wrong password (bcrypt)', function () {
        $auth = makeAuthenticator(['alice' => 'correct'], 'bcrypt');

        expect($auth->authenticate('alice', 'wrong'))->toBeNull();
    });

    it('returns null for a wrong password (sha256)', function () {
        $auth = makeAuthenticator(['alice' => 'correct'], 'sha256');

        expect($auth->authenticate('alice', 'wrong'))->toBeNull();
    });

    it('returns null for an unknown user', function () {
        $auth = makeAuthenticator(['alice' => 'pw']);

        expect($auth->authenticate('charlie', 'pw'))->toBeNull();
    });

    it('returns null for an empty identifier', function () {
        $auth = makeAuthenticator(['alice' => 'pw']);

        expect($auth->authenticate('', 'pw'))->toBeNull();
    });

    it('returns null for an empty password', function () {
        $auth = makeAuthenticator(['alice' => 'pw']);

        expect($auth->authenticate('alice', ''))->toBeNull();
    });

    it('returns null for both empty identifier and password', function () {
        $auth = makeAuthenticator(['alice' => 'pw']);

        expect($auth->authenticate('', ''))->toBeNull();
    });

    it('does not authenticate one user with another user\'s password', function () {
        $auth = makeAuthenticator([
            'alice' => 'pass-a',
            'bob'   => 'pass-b',
        ]);

        expect($auth->authenticate('alice', 'pass-b'))->toBeNull();
        expect($auth->authenticate('bob', 'pass-a'))->toBeNull();
    });

    it('is case-sensitive for identifiers', function () {
        $auth = makeAuthenticator(['alice' => 'pw']);

        expect($auth->authenticate('Alice', 'pw'))->toBeNull();
        expect($auth->authenticate('ALICE', 'pw'))->toBeNull();
    });

    it('is case-sensitive for passwords', function () {
        $auth = makeAuthenticator(['alice' => 'Secret']);

        expect($auth->authenticate('alice', 'secret'))->toBeNull();
        expect($auth->authenticate('alice', 'SECRET'))->toBeNull();
    });

});

// ---------------------------------------------------------------------------
// Environment variable resolution
// ---------------------------------------------------------------------------

describe('environment variable resolution', function () {

    it('skips entries where the user env var is missing', function () {
        $_ENV['AUTH_HASH_GHOST'] = password_hash('pw', PASSWORD_BCRYPT);
        // AUTH_USER_GHOST is intentionally not set

        $auth = new EnvironmentAuthenticator(['AUTH_USER_GHOST' => 'AUTH_HASH_GHOST']);

        // With no resolved users, any call must return null
        expect($auth->authenticate('ghost', 'pw'))->toBeNull();
    });

    it('skips entries where the hash env var is missing', function () {
        $_ENV['AUTH_USER_NOHASH'] = 'nohash';
        // AUTH_HASH_NOHASH is intentionally not set

        $auth = new EnvironmentAuthenticator(['AUTH_USER_NOHASH' => 'AUTH_HASH_NOHASH']);

        expect($auth->authenticate('nohash', 'anything'))->toBeNull();
    });

    it('still resolves other users when one env entry is incomplete', function () {
        // Alice is complete, bob is missing his hash var
        $_ENV['AUTH_USER_ALICE2'] = 'alice2';
        $_ENV['AUTH_HASH_ALICE2'] = password_hash('pw-a', PASSWORD_BCRYPT);
        $_ENV['AUTH_USER_BOB2']   = 'bob2';
        // AUTH_HASH_BOB2 intentionally absent

        $auth = new EnvironmentAuthenticator([
            'AUTH_USER_ALICE2' => 'AUTH_HASH_ALICE2',
            'AUTH_USER_BOB2'   => 'AUTH_HASH_BOB2',
        ]);

        expect($auth->authenticate('alice2', 'pw-a'))->toBe(['id' => 'alice2']);
        expect($auth->authenticate('bob2', 'pw-b'))->toBeNull();
    });

    it('creates an authenticator with no users when all env vars are missing', function () {
        $auth = new EnvironmentAuthenticator([
            'NONEXISTENT_USER_VAR' => 'NONEXISTENT_HASH_VAR',
        ]);

        expect($auth->authenticate('anyone', 'anything'))->toBeNull();
    });

    it('creates an authenticator with no users from an empty config', function () {
        $auth = new EnvironmentAuthenticator([]);

        expect($auth->authenticate('anyone', 'anything'))->toBeNull();
    });

});

// ---------------------------------------------------------------------------
// Hash format detection
// ---------------------------------------------------------------------------

describe('hash format detection', function () {

    it('treats a non-bcrypt string as a sha256 hash', function () {
        $_ENV['AUTH_USER_SHA'] = 'sha-user';
        $_ENV['AUTH_HASH_SHA'] = hash('sha256', 'my-password');

        $auth = new EnvironmentAuthenticator(['AUTH_USER_SHA' => 'AUTH_HASH_SHA']);

        expect($auth->authenticate('sha-user', 'my-password'))->toBe(['id' => 'sha-user']);
        expect($auth->authenticate('sha-user', 'wrong'))->toBeNull();
    });

    it('handles a bcrypt hash correctly via password_verify', function () {
        $_ENV['AUTH_USER_BC'] = 'bc-user';
        $_ENV['AUTH_HASH_BC'] = password_hash('bcrypt-pass', PASSWORD_BCRYPT);

        $auth = new EnvironmentAuthenticator(['AUTH_USER_BC' => 'AUTH_HASH_BC']);

        expect($auth->authenticate('bc-user', 'bcrypt-pass'))->toBe(['id' => 'bc-user']);
        expect($auth->authenticate('bc-user', 'wrong'))->toBeNull();
    });

});
