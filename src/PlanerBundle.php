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
                    ->isRequired()
                    ->info('FQCN klasy User implementującej PlanerUserInterface')
                ->end()
                ->scalarNode('base_template')
                    ->defaultValue('base.html.twig')
                    ->info('Bazowy szablon Twig, który rozszerzają szablony bundla')
                ->end()
                ->scalarNode('firma_nazwa')
                    ->defaultValue('')
                    ->info('Nazwa firmy (do podań urlopowych)')
                ->end()
                ->scalarNode('firma_adres')
                    ->defaultValue('')
                    ->info('Adres firmy (do podań urlopowych)')
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

        $container->parameters()
            ->set('planer.base_template', $config['base_template'])
            ->set('planer.user_class', $config['user_class'])
            ->set('planer.firma_nazwa', $config['firma_nazwa'])
            ->set('planer.firma_adres', $config['firma_adres']);
    }
}
