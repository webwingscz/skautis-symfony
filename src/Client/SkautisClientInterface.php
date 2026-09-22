<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Client;

/**
 * Thin transport over the skautIS web services.
 *
 * Callers name a service ("OrganizationUnit") and a method ("PersonAll"); the
 * implementation deals with wrapping, credentials and unwrapping the result.
 */
interface SkautisClientInterface
{
    /**
     * Calls a method that returns a collection and always hands back a list,
     * even when skautIS returned a single item or nothing at all.
     *
     * @param array<string, scalar|null> $args
     *
     * @return list<\stdClass>
     */
    public function callAll(string $service, string $method, array $args = []): array;

    /**
     * Calls a method that returns a single record, or null when there is none.
     *
     * @param array<string, scalar|null> $args
     */
    public function callOne(string $service, string $method, array $args = []): ?\stdClass;
}
