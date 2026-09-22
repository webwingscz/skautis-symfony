<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SkautisUser>
 */
final class SkautisUserProvider implements UserProviderInterface
{
    /**
     * There is nothing to look a user up by: the skautIS token is the credential
     * and it only arrives with the login POST, which the authenticator handles.
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new UserNotFoundException('Uživatele skautISu nelze načíst bez tokenu.');
    }

    /**
     * The session copy is authoritative - skautIS is queried per page, not here,
     * so that browsing does not cost an extra round trip on every request.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SkautisUser) {
            throw new UnsupportedUserException(sprintf('Nepodporovaný uživatel "%s".', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return SkautisUser::class === $class || is_subclass_of($class, SkautisUser::class);
    }
}
