<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Client;

use Webwings\SkautisBundle\Exception\SkautisAuthenticationException;

/**
 * Stand-in for the real SOAP client, used when SKAUTIS_APP_ID is not set yet.
 *
 * It answers the same service/method pairs from JSON files in fixtures/skautis/,
 * so the whole application - including the login flow - can be built and clicked
 * through before an application is registered in skautIS.
 */
final class FixtureSkautisClient implements SkautisClientInterface
{
    public const TOKEN = '00000000-0000-4000-8000-000000000001';

    /** @var array<string, list<\stdClass>> */
    private array $loaded = [];

    public function __construct(
        private readonly string $fixtureDir,
        private readonly TokenHolder $tokenHolder,
    ) {
    }

    public function callAll(string $service, string $method, array $args = []): array
    {
        $this->assertToken($service, $method);

        return match (sprintf('%s.%s', $service, $method)) {
            'UserManagement.UserRoleAll' => $this->rows('UserRoleAll'),
            'OrganizationUnit.UnitAll' => $this->filter('UnitAll', 'ID_UnitParent', $args['ID_UnitParent'] ?? null),
            'OrganizationUnit.PersonAll' => $this->personsOfUnit($args),
            'OrganizationUnit.PersonContactAll' => $this->filter('PersonContactAll', 'ID_Person', $args['ID_Person'] ?? null),
            'OrganizationUnit.PersonContactAllParent' => $this->filter('PersonContactAllParent', 'ID_Person', $args['ID_Person'] ?? null),
            default => [],
        };
    }

    public function callOne(string $service, string $method, array $args = []): ?\stdClass
    {
        $key = sprintf('%s.%s', $service, $method);

        // These two are how a session is established and kept alive, so they must
        // answer before there is anything to authenticate against.
        if ('UserManagement.LoginUpdateRefresh' === $key) {
            return (object) ['DateLogout' => (new \DateTimeImmutable('+30 minutes'))->format(\DATE_ATOM)];
        }

        $this->assertToken($service, $method);

        return match ($key) {
            'UserManagement.UserDetail' => $this->rows('UserDetail')[0] ?? null,
            'UserManagement.LoginUpdate' => $this->switchRole((int) ($args['ID_UserRole'] ?? 0)),
            'OrganizationUnit.UnitDetail' => $this->find('UnitAll', 'ID', $args['ID'] ?? null),
            'OrganizationUnit.PersonDetail' => $this->find('PersonAll', 'ID', $args['ID'] ?? null),
            default => null,
        };
    }

    /**
     * PersonAll returns members of the unit and, unless OnlyDirectMember is set,
     * of everything below it. The fixtures model that with an ID_Unit column.
     *
     * @param array<string, scalar|null> $args
     *
     * @return list<\stdClass>
     */
    private function personsOfUnit(array $args): array
    {
        $unitId = (int) ($args['ID_Unit'] ?? 0);
        $onlyDirect = (bool) ($args['OnlyDirectMember'] ?? false);

        $unitIds = [$unitId];

        if (!$onlyDirect) {
            foreach ($this->filter('UnitAll', 'ID_UnitParent', $unitId) as $child) {
                $unitIds[] = (int) $child->ID;
            }
        }

        return array_values(array_filter(
            $this->rows('PersonAll'),
            static fn (\stdClass $row): bool => \in_array((int) ($row->ID_Unit ?? 0), $unitIds, true),
        ));
    }

    private function switchRole(int $userRoleId): \stdClass
    {
        $role = $this->find('UserRoleAll', 'ID', $userRoleId);

        return (object) ['ID_Unit' => (int) ($role->ID_Unit ?? 0)];
    }

    /**
     * @return list<\stdClass>
     */
    private function filter(string $name, string $field, mixed $value): array
    {
        if (null === $value) {
            return $this->rows($name);
        }

        return array_values(array_filter(
            $this->rows($name),
            static fn (\stdClass $row): bool => (string) ($row->{$field} ?? '') === (string) $value,
        ));
    }

    private function find(string $name, string $field, mixed $value): ?\stdClass
    {
        return $this->filter($name, $field, $value)[0] ?? null;
    }

    /**
     * @return list<\stdClass>
     */
    private function rows(string $name): array
    {
        if (isset($this->loaded[$name])) {
            return $this->loaded[$name];
        }

        $file = sprintf('%s/%s.json', rtrim($this->fixtureDir, '/'), $name);
        $json = is_readable($file) ? file_get_contents($file) : false;
        $data = false !== $json ? json_decode($json, flags: \JSON_THROW_ON_ERROR) : [];

        return $this->loaded[$name] = array_values(array_filter(
            \is_array($data) ? $data : [$data],
            static fn (mixed $row): bool => $row instanceof \stdClass,
        ));
    }

    private function assertToken(string $service, string $method): void
    {
        if (self::TOKEN !== $this->tokenHolder->getToken()) {
            throw new SkautisAuthenticationException(sprintf('%s.%s: bez platného tokenu (demo režim)', $service, $method));
        }
    }
}
