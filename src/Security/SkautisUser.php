<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Security;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Webwings\SkautisBundle\Dto\Role;

/**
 * The logged-in skautIS user, as stored in the session.
 *
 * Everything here comes from skautIS at login time. The object is immutable;
 * switching a role produces a new instance and re-authenticates.
 */
final class SkautisUser implements UserInterface, EquatableInterface
{
    /**
     * @param list<Role> $availableRoles
     */
    public function __construct(
        private readonly string $skautisToken,
        private readonly int $userId,
        private readonly int $personId,
        private readonly string $userName,
        private readonly string $displayName,
        private readonly ?Role $currentRole,
        private readonly ?int $unitId,
        private readonly array $availableRoles = [],
    ) {
    }

    public function getSkautisToken(): string
    {
        return $this->skautisToken;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getPersonId(): int
    {
        return $this->personId;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getCurrentRole(): ?Role
    {
        return $this->currentRole;
    }

    /**
     * The unit the current role acts in - the starting point for most screens.
     */
    public function getUnitId(): ?int
    {
        return $this->unitId;
    }

    public function getUnitName(): ?string
    {
        return $this->currentRole?->unitName;
    }

    /**
     * @return list<Role>
     */
    public function getAvailableRoles(): array
    {
        return $this->availableRoles;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    /**
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        return '' !== $this->userName ? $this->userName : 'skautis-user';
    }

    public function eraseCredentials(): void
    {
    }

    /**
     * Symfony compares the session user with the refreshed one on every request;
     * without this it would log the user out whenever any field differs.
     */
    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self
            && $user->skautisToken === $this->skautisToken
            && $user->currentRole?->id === $this->currentRole?->id;
    }
}
