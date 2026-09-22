<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Consumes the POST that skautIS sends to the registered login URL.
 *
 * skautIS authenticates the user on its own pages and then posts the resulting
 * token here; there is no password for us to check, so the token itself is the
 * credential and we validate it by using it.
 */
final class SkautisAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    use TargetPathTrait;

    public const TOKEN_FIELD = 'skautIS_Token';
    public const ROLE_FIELD = 'skautIS_IDRole';
    public const UNIT_FIELD = 'skautIS_IDUnit';

    public function __construct(
        private readonly SkautisUserFactory $userFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $loginRoute,
        private readonly string $successRoute,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $this->loginRoute === $request->attributes->get('_route')
            && '' !== (string) $request->request->get(self::TOKEN_FIELD, '');
    }

    public function authenticate(Request $request): Passport
    {
        $token = trim((string) $request->request->get(self::TOKEN_FIELD, ''));

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('skautIS nepředal přihlašovací token.');
        }

        $roleId = $request->request->getInt(self::ROLE_FIELD) ?: null;
        $unitId = $request->request->getInt(self::UNIT_FIELD) ?: null;

        return new SelfValidatingPassport(
            new UserBadge(
                $token,
                fn (string $skautisToken): SkautisUser => $this->userFactory->create($skautisToken, $roleId, $unitId),
            ),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        $target = $this->getTargetPath($request->getSession(), $firewallName)
            ?? $this->urlGenerator->generate($this->successRoute);

        $this->removeTargetPath($request->getSession(), $firewallName);

        return new RedirectResponse($target);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', $exception->getMessageKey());
        }

        return new RedirectResponse($this->urlGenerator->generate($this->loginRoute));
    }

    /**
     * Where anonymous visitors are sent. The firewall has already remembered
     * the page they wanted, so login can return them to it.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate($this->loginRoute));
    }
}
