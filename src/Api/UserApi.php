<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Api;

use Webwings\SkautisBundle\Client\SkautisClientInterface;
use Webwings\SkautisBundle\Client\TokenHolder;
use Webwings\SkautisBundle\Dto\Role;

/**
 * Login session and roles (UserManagement.asmx).
 */
final readonly class UserApi
{
    private const SERVICE = 'UserManagement';

    public function __construct(
        private SkautisClientInterface $client,
        private TokenHolder $tokenHolder,
    ) {
    }

    /**
     * The account behind the current token, including its ID_Person.
     */
    public function detail(): ?\stdClass
    {
        return $this->client->callOne(self::SERVICE, 'UserDetail');
    }

    /**
     * Roles the user may switch into. Only active ones are worth offering.
     *
     * @return list<Role>
     */
    public function roles(int $userId, bool $onlyActive = true): array
    {
        $rows = $this->client->callAll(self::SERVICE, 'UserRoleAll', [
            'ID_User' => $userId,
            'IsActive' => $onlyActive ? true : null,
        ]);

        return array_map(Role::fromApi(...), $rows);
    }

    /**
     * Switches the active role of the current login and returns the unit that
     * role belongs to. Unlike most methods, LoginUpdate takes the token as `ID`.
     */
    public function switchRole(int $userRoleId): ?int
    {
        $token = $this->tokenHolder->getToken();

        if (null === $token) {
            return null;
        }

        $result = $this->client->callOne(self::SERVICE, 'LoginUpdate', [
            'ID' => $token,
            'ID_UserRole' => $userRoleId,
        ]);

        return isset($result->ID_Unit) ? (int) $result->ID_Unit : null;
    }

    /**
     * Extends the login by another 30 minutes and reports the new expiry.
     * Also takes the token as `ID`.
     */
    public function refresh(): ?\DateTimeImmutable
    {
        $token = $this->tokenHolder->getToken();

        if (null === $token) {
            return null;
        }

        $result = $this->client->callOne(self::SERVICE, 'LoginUpdateRefresh', ['ID' => $token]);
        $dateLogout = $result->DateLogout ?? null;

        if (!\is_string($dateLogout) || '' === $dateLogout) {
            return null;
        }

        try {
            return new \DateTimeImmutable($dateLogout);
        } catch (\Exception) {
            return null;
        }
    }
}
