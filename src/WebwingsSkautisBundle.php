<?php

declare(strict_types=1);

namespace Webwings\SkautisBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class WebwingsSkautisBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('base_url')
                    ->info('Kořen instance skautISu, např. https://test-is.skaut.cz')
                    ->defaultValue('https://is.skaut.cz')
                ->end()
                ->scalarNode('app_id')
                    ->info('AppID přidělené správcem skautISu.')
                    ->defaultValue('')
                ->end()
                ->booleanNode('mock')
                    ->info('Místo skautISu odpovídat z fixtures - pro vývoj bez AppID.')
                    ->defaultFalse()
                ->end()
                ->scalarNode('fixture_dir')
                    ->info('Adresář s JSON fixtures; výchozí jsou ukázková data v bundlu.')
                    ->defaultNull()
                ->end()
                ->integerNode('timeout')
                    ->info('Timeout SOAP volání v sekundách.')
                    ->defaultValue(30)
                ->end()
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('login_route')
                            ->info('Routa, na kterou skautIS posílá POST s tokenem.')
                            ->defaultValue('app_login')
                        ->end()
                        ->scalarNode('success_route')
                            ->info('Kam jít po úspěšném přihlášení, není-li uložená cílová cesta.')
                            ->defaultValue('app_dashboard')
                        ->end()
                        ->scalarNode('logout_route')
                            ->info('Routa odhlášení; sem míří i vypršelý token.')
                            ->defaultValue('app_logout')
                        ->end()
                        ->integerNode('refresh_interval')
                            ->info('Jak často nejvýš prodlužovat přihlášení, v sekundách.')
                            ->defaultValue(600)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{
     *     base_url: string,
     *     app_id: string,
     *     mock: bool,
     *     fixture_dir: string|null,
     *     timeout: int,
     *     security: array{login_route: string, success_route: string, logout_route: string, refresh_interval: int},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('webwings_skautis.base_url', $config['base_url']);
        $builder->setParameter('webwings_skautis.app_id', $config['app_id']);
        $builder->setParameter('webwings_skautis.mock', $config['mock']);
        $builder->setParameter('webwings_skautis.timeout', $config['timeout']);
        $builder->setParameter(
            'webwings_skautis.fixture_dir',
            $config['fixture_dir'] ?? $this->getPath().'/resources/fixtures',
        );
        $builder->setParameter('webwings_skautis.security.login_route', $config['security']['login_route']);
        $builder->setParameter('webwings_skautis.security.success_route', $config['security']['success_route']);
        $builder->setParameter('webwings_skautis.security.logout_route', $config['security']['logout_route']);
        $builder->setParameter('webwings_skautis.security.refresh_interval', $config['security']['refresh_interval']);

        $container->import('../config/services.php');
    }
}
