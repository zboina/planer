<?php

namespace Planer\PlanerBundle;

use Planer\PlanerBundle\Model\PlanerUserInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class PlanerBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('user_class')
                    ->defaultValue('App\\Entity\\User')
                    ->info('FQCN klasy User (np. App\\Entity\\User)')
                ->end()
                ->scalarNode('base_template')
                    ->defaultValue('@Planer/base_planer.html.twig')
                    ->info('Bazowy szablon Twig, który rozszerzają szablony bundla (domyślnie wbudowany z Tablerem)')
                ->end()
                ->arrayNode('user_full_name_fields')
                    ->defaultValue(['firstName', 'lastName'])
                    ->scalarPrototype()->end()
                    ->info('Nazwy pól User, z których złożyć pełne imię i nazwisko (np. [firstName, lastName] lub [imie, nazwisko])')
                ->end()
                ->scalarNode('firma_nazwa')
                    ->defaultValue('')
                    ->info('Nazwa firmy (do podań urlopowych)')
                ->end()
                ->scalarNode('firma_adres')
                    ->defaultValue('')
                    ->info('Adres firmy (do podań urlopowych)')
                ->end()
                ->scalarNode('logout_route')
                    ->defaultValue('app_logout')
                    ->info('Nazwa route do wylogowania (np. app_logout)')
                ->end()
            ->end();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $configs = $builder->getExtensionConfig('planer');
        $userClass = $configs[0]['user_class'] ?? 'App\\Entity\\User';

        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'resolve_target_entities' => [
                    PlanerUserInterface::class => $userClass,
                ],
                'mappings' => [
                    'PlanerBundle' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => dirname(__DIR__) . '/src/Entity',
                        'prefix' => 'Planer\\PlanerBundle\\Entity',
                        'alias' => 'Planer',
                    ],
                ],
            ],
        ]);
    }

    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->import('../config/services.yaml');

        $projectDir = $builder->getParameter('kernel.project_dir');

        // Auto-copy Stimulus controllers to host project
        $bundleControllersDir = \dirname(__DIR__) . '/assets/controllers';
        $hostControllersDir = $projectDir . '/assets/controllers';
        if (is_dir($bundleControllersDir) && is_dir($hostControllersDir)) {
            foreach (glob($bundleControllersDir . '/*_controller.js') as $src) {
                $dest = $hostControllersDir . '/' . basename($src);
                if (!file_exists($dest)) {
                    @copy($src, $dest);
                }
            }
        }

        // Auto-create routes config in host project if missing
        $routesFile = $projectDir . '/config/routes/planer.yaml';
        if (!file_exists($routesFile)) {
            $dir = \dirname($routesFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
            @file_put_contents($routesFile, <<<'YAML'
planer_routes:
    resource: '@PlanerBundle/config/routes.yaml'
YAML
            );
        }

        $container->parameters()
            ->set('planer.base_template', $config['base_template'])
            ->set('planer.user_class', $config['user_class'])
            ->set('planer.user_full_name_fields', $config['user_full_name_fields'])
            ->set('planer.firma_nazwa', $config['firma_nazwa'])
            ->set('planer.firma_adres', $config['firma_adres'])
            ->set('planer.logout_route', $config['logout_route']);
    }
}
