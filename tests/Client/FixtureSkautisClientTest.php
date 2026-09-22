<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Tests\Client;

use PHPUnit\Framework\TestCase;
use Webwings\SkautisBundle\Client\FixtureSkautisClient;
use Webwings\SkautisBundle\Client\TokenHolder;
use Webwings\SkautisBundle\Exception\SkautisAuthenticationException;

final class FixtureSkautisClientTest extends TestCase
{
    private function client(?string $token = FixtureSkautisClient::TOKEN): FixtureSkautisClient
    {
        $holder = new TokenHolder();
        $holder->setToken($token);

        return new FixtureSkautisClient(__DIR__.'/../../resources/fixtures', $holder);
    }

    public function testRejectsCallsWithoutTheDemoToken(): void
    {
        $this->expectException(SkautisAuthenticationException::class);

        $this->client(token: null)->callAll('OrganizationUnit', 'UnitAll');
    }

    public function testChildUnitsAreFilteredByParent(): void
    {
        $children = $this->client()->callAll('OrganizationUnit', 'UnitAll', ['ID_UnitParent' => 1001]);

        self::assertCount(2, $children);
        self::assertSame([1002, 1003], array_map(static fn (object $u): int => (int) $u->ID, $children));
    }

    public function testPersonAllIncludesChildUnitsUnlessOnlyDirectIsSet(): void
    {
        $client = $this->client();

        $all = $client->callAll('OrganizationUnit', 'PersonAll', ['ID_Unit' => 1001]);
        $direct = $client->callAll('OrganizationUnit', 'PersonAll', ['ID_Unit' => 1001, 'OnlyDirectMember' => true]);

        self::assertGreaterThan(\count($direct), \count($all));
    }

    public function testRefreshAnswersEvenWithoutAToken(): void
    {
        // The session listener calls this before anything else is established.
        $result = $this->client(token: null)->callOne('UserManagement', 'LoginUpdateRefresh');

        self::assertNotNull($result);
        self::assertNotEmpty($result->DateLogout);
    }
}
