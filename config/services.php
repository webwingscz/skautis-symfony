<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Webwings\SkautisBundle\Api\PersonApi;
use Webwings\SkautisBundle\Api\UnitApi;
use Webwings\SkautisBundle\Api\UserApi;
use Webwings\SkautisBundle\Client\FixtureSkautisClient;
use Webwings\SkautisBundle\Client\SkautisClientInterface;
use Webwings\SkautisBundle\Client\SkautisClientResolver;
use Webwings\SkautisBundle\Client\SoapClientFactory;
use Webwings\SkautisBundle\Client\SoapSkautisClient;
use Webwings\SkautisBundle\Client\TokenHolder;
use Webwings\SkautisBundle\EventListener\SkautisExceptionListener;
use Webwings\SkautisBundle\EventListener\SkautisSessionListener;
use Webwings\SkautisBundle\Security\SkautisAuthenticator;
use Webwings\SkautisBundle\Security\SkautisUserFactory;
use Webwings\SkautisBundle\Security\SkautisUserProvider;
use Webwings\SkautisBundle\SkautisConfig;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(SkautisConfig::class)
        ->args([param('webwings_skautis.base_url'), param('webwings_skautis.app_id')]);

    $services->set(TokenHolder::class);

    $services->set(SoapClientFactory::class)
        ->args([service(SkautisConfig::class), param('webwings_skautis.timeout')]);

    $services->set(SoapSkautisClient::class)
        ->args([
            service(SoapClientFactory::class),
            service(SkautisConfig::class),
            service(TokenHolder::class),
            service('logger')->nullOnInvalid(),
        ])
        ->tag('monolog.logger', ['channel' => 'skautis']);

    $services->set(FixtureSkautisClient::class)
        ->args([param('webwings_skautis.fixture_dir'), service(TokenHolder::class)]);

    // Which client answers is an environment setting, not a build-time one.
    $services->set(SkautisClientInterface::class)
        ->factory([SkautisClientResolver::class, 'create'])
        ->args([
            param('webwings_skautis.mock'),
            service(FixtureSkautisClient::class),
            service(SoapSkautisClient::class),
        ]);

    $services->set(UserApi::class)
        ->args([service(SkautisClientInterface::class), service(TokenHolder::class)]);

    $services->set(UnitApi::class)->args([service(SkautisClientInterface::class)]);
    $services->set(PersonApi::class)->args([service(SkautisClientInterface::class)]);

    $services->set(SkautisUserFactory::class)
        ->args([service(UserApi::class), service(TokenHolder::class)]);

    $services->set(SkautisUserProvider::class);

    $services->set(SkautisAuthenticator::class)
        ->args([
            service(SkautisUserFactory::class),
            service('router'),
            param('webwings_skautis.security.login_route'),
            param('webwings_skautis.security.success_route'),
        ]);

    $services->set(SkautisSessionListener::class)
        ->args([
            service('security.helper'),
            service(TokenHolder::class),
            service(UserApi::class),
            param('webwings_skautis.security.refresh_interval'),
            service('logger')->nullOnInvalid(),
        ])
        ->tag('monolog.logger', ['channel' => 'skautis'])
        // After the firewall (priority 8) so that a user is already available.
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => 0]);

    $services->set(SkautisExceptionListener::class)
        ->args([service('router'), param('webwings_skautis.security.logout_route')])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception']);
};
