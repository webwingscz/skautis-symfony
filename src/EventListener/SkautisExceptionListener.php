<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\EventListener;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webwings\SkautisBundle\Exception\SkautisAuthenticationException;
use Webwings\SkautisBundle\Exception\SkautisPermissionException;
use Webwings\SkautisBundle\Exception\SkautisTransportException;

/**
 * Turns skautIS failures into something a phone user can act on instead of a 500.
 */
final readonly class SkautisExceptionListener
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $logoutRoute,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if ($exception instanceof SkautisAuthenticationException) {
            // The 30 minute skautIS window closed; send them back through login.
            $request = $event->getRequest();

            $session = $request->hasSession() ? $request->getSession() : null;

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('error', 'Přihlášení do skautISu vypršelo, přihlas se prosím znovu.');
            }

            $event->setResponse(new RedirectResponse($this->urlGenerator->generate($this->logoutRoute)));

            return;
        }

        if ($exception instanceof SkautisPermissionException) {
            $event->setThrowable(new AccessDeniedHttpException(
                'Tvoje aktuální role nemá k těmto údajům ve skautISu přístup.',
                $exception,
            ));

            return;
        }

        if ($exception instanceof SkautisTransportException) {
            $event->setThrowable(new ServiceUnavailableHttpException(
                null,
                'skautIS teď neodpovídá. Zkus to prosím za chvíli.',
                $exception,
            ));
        }
    }
}
