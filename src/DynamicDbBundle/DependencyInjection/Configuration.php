<?php

namespace Feroz\DynamicDbBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dynamic_db');

        // dynamic_db:
        //     managers:
        //         analytics:
        //             mappings:
        //                 - { dir: '%kernel.project_dir%/src/Entity/Analytics', prefix: 'App\Entity\Analytics' }
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('managers')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->arrayNode('mappings')
                                ->arrayPrototype()
                                    ->children()
                                        ->scalarNode('dir')->isRequired()->cannotBeEmpty()->end()
                                        ->scalarNode('prefix')->isRequired()->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
