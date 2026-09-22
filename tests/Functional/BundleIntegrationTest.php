<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Webwings\SkautisBundle\Api\PersonApi;
use Webwings\SkautisBundle\Client\FixtureSkautisClient;
use Webwings\SkautisBundle\Client\SkautisClientInterface;
use Webwings\SkautisBundle\Client\TokenHolder;
use Webwings\SkautisBundle\Security\SkautisAuthenticator;
use Webwings\SkautisBundle\SkautisConfig;
use Webwings\SkautisBundle\WebwingsSkautisBundle;

/**
 * Boots a real kernel with the bundle registered.
 *
 * The unit tests cover the logic; this one covers the part that actually breaks
 * across Symfony versions - the configuration tree, the service wiring and the
 * security integration.
 */
final class BundleIntegrationTest extends TestCase
{
    public function testContainerCompilesAndWiresTheServices(): void
    {
        $container = $this->boot()->getContainer();

        self::assertInstanceOf(PersonApi::class, $container->get(PersonApi::class));
        self::assertInstanceOf(SkautisAuthenticator::class, $container->get(SkautisAuthenticator::class));
    }

    public function testMockModeSelectsTheFixtureClient(): void
    {
        $container = $this->boot()->getContainer();

        self::assertInstanceOf(FixtureSkautisClient::class, $container->get(SkautisClientInterface::class));
    }

    public function testConfigurationReachesTheClient(): void
    {
        $config = $this->boot()->getContainer()->get(SkautisConfig::class);
        self::assertInstanceOf(SkautisConfig::class, $config);

        self::assertSame('https://test-is.skaut.cz/Login/?appid=test-app-id', $config->loginUrl());
        self::assertSame(
            'https://test-is.skaut.cz/JunakWebservice/OrganizationUnit.asmx?WSDL',
            $config->wsdlUrl('OrganizationUnit'),
        );
    }

    public function testTheApiLayerAnswersFromTheBundledFixtures(): void
    {
        $container = $this->boot()->getContainer();

        $tokenHolder = $container->get(TokenHolder::class);
        self::assertInstanceOf(TokenHolder::class, $tokenHolder);
        $tokenHolder->setToken(FixtureSkautisClient::TOKEN);

        $people = $container->get(PersonApi::class);
        self::assertInstanceOf(PersonApi::class, $people);

        $members = $people->listByUnit(1002);

        self::assertNotEmpty($members);
        self::assertNotEmpty($members[0]->displayName);
    }

    private function boot(): Kernel
    {
        $kernel = new SkautisTestKernel('test', true);
        $kernel->boot();

        return $kernel;
    }
}

final class SkautisTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new SecurityBundle(),
            new WebwingsSkautisBundle(),
        ];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/skautis-symfony-test/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/skautis-symfony-test/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => false,
            'http_method_override' => false,
            'router' => ['utf8' => true],
            'php_errors' => ['log' => true],
        ]);

        $container->extension('security', [
            'providers' => [
                'skautis' => ['id' => 'Webwings\SkautisBundle\Security\SkautisUserProvider'],
            ],
            'firewalls' => [
                'main' => [
                    'lazy' => true,
                    'provider' => 'skautis',
                    'custom_authenticators' => [SkautisAuthenticator::class],
                    'entry_point' => SkautisAuthenticator::class,
                ],
            ],
        ]);

        $container->extension('webwings_skautis', [
            'base_url' => 'https://test-is.skaut.cz',
            'app_id' => 'test-app-id',
            'mock' => true,
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }

    protected function build(ContainerBuilder $container): void
    {
        // The bundle's services are private, as they should be; the test needs a
        // handle on a few of them.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach ([PersonApi::class, SkautisClientInterface::class, SkautisConfig::class, TokenHolder::class, SkautisAuthenticator::class] as $id) {
                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        });
    }
}
