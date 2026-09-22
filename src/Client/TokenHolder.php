<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Client;

/**
 * Holds the skautIS token for the current request.
 *
 * The token arrives with the login POST, before a security token exists, and is
 * then carried by the authenticated user. Keeping it in one small mutable
 * service lets the client stay unaware of where in the lifecycle we are.
 */
final class TokenHolder
{
    private ?string $token = null;

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    /**
     * Runs a callback with a different token, then restores the previous one.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    public function withToken(string $token, callable $callback): mixed
    {
        $previous = $this->token;
        $this->token = $token;

        try {
            return $callback();
        } finally {
            $this->token = $previous;
        }
    }
}
