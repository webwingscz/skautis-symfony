<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Webwings\SkautisBundle\Api\UserApi;
use Webwings\SkautisBundle\Client\TokenHolder;
use Webwings\SkautisBundle\Exception\SkautisException;
use Webwings\SkautisBundle\Security\SkautisUser;

/**
 * Keeps the skautIS session usable for the length of a visit.
 *
 * Two jobs: hand the current user's token to the API client, and extend the
 * skautIS login before its 30 minute window runs out. The refresh is throttled
 * so that browsing does not add a SOAP round trip to every single request.
 */
final readonly class SkautisSessionListener
{
    private const SESSION_KEY = 'skautis.refreshed_at';

    public function __construct(
        private Security $security,
        private TokenHolder $tokenHolder,
        private UserApi $userApi,
        private int $refreshInterval = 600,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user instanceof SkautisUser) {
            $this->tokenHolder->setToken(null);

            return;
        }

        $this->tokenHolder->setToken($user->getSkautisToken());

        $request = $event->getRequest();

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $last = (int) $session->get(self::SESSION_KEY, 0);

        if (time() - $last < $this->refreshInterval) {
            return;
        }

        try {
            $this->userApi->refresh();
            $session->set(self::SESSION_KEY, time());
        } catch (SkautisException $e) {
            // A dead token is handled where it matters - on the page that needs data.
            $this->logger?->info('Prodloužení skautIS přihlášení selhalo: {message}', ['message' => $e->getMessage()]);
        }
    }
}
