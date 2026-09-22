<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Webwings\SkautisBundle\Api\UserApi;
use Webwings\SkautisBundle\Client\TokenHolder;
use Webwings\SkautisBundle\Dto\Role;
use Webwings\SkautisBundle\Exception\SkautisException;

/**
 * Turns a raw skautIS token into a SkautisUser by asking skautIS who it belongs to.
 */
final readonly class SkautisUserFactory
{
    public function __construct(
        private UserApi $userApi,
        private TokenHolder $tokenHolder,
    ) {
    }

    /**
     * @param int|null $roleId skautIS_IDRole from the login POST, if it sent one
     * @param int|null $unitId skautIS_IDUnit from the login POST, if it sent one
     */
    public function create(string $token, ?int $roleId = null, ?int $unitId = null): SkautisUser
    {
        // The token is not on a security token yet, so hand it to the client directly.
        return $this->tokenHolder->withToken($token, function () use ($token, $roleId, $unitId): SkautisUser {
            try {
                $detail = $this->userApi->detail();
            } catch (SkautisException $e) {
                throw new CustomUserMessageAuthenticationException('Přihlášení do skautISu se nepodařilo ověřit.', previous: $e);
            }

            if (null === $detail || !isset($detail->ID)) {
                throw new CustomUserMessageAuthenticationException('skautIS token není platný.');
            }

            $userId = (int) $detail->ID;
            $roles = $this->userApi->roles($userId);
            $current = $this->pickRole($roles, $roleId);

            // The role's own unit wins; the one skautIS posted is the fallback.
            $resolvedUnitId = $current?->unitId;
            $resolvedUnitId ??= $unitId ?: null;

            return new SkautisUser(
                skautisToken: $token,
                userId: $userId,
                personId: (int) ($detail->ID_Person ?? 0),
                userName: (string) ($detail->UserName ?? $token),
                displayName: (string) ($detail->Person ?? $detail->UserName ?? 'Uživatel'),
                currentRole: $current,
                unitId: $resolvedUnitId,
                availableRoles: $roles,
            );
        });
    }

    /**
     * @param list<Role> $roles
     */
    private function pickRole(array $roles, ?int $roleId): ?Role
    {
        if (null !== $roleId) {
            foreach ($roles as $role) {
                if ($role->id === $roleId) {
                    return $role;
                }
            }
        }

        return $roles[0] ?? null;
    }
}
