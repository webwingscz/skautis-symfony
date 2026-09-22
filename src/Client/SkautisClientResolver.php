<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Client;

/**
 * Picks the real SOAP client or the fixture one, depending on SKAUTIS_MOCK.
 *
 * Doing this in a factory rather than with two aliases keeps the choice an
 * environment setting instead of a build-time one, so the same image can run
 * in demo mode or against skautIS.
 */
final class SkautisClientResolver
{
    public static function create(
        bool $mock,
        FixtureSkautisClient $fixture,
        SoapSkautisClient $soap,
    ): SkautisClientInterface {
        return $mock ? $fixture : $soap;
    }
}
