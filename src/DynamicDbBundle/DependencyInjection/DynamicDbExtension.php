<?php

namespace Feroz\DynamicDbBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DynamicDbExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $mappings = [];
        foreach ($config['managers'] ?? [] as $name => $manager) {
            $mappings[$name] = $manager['mappings'];
        }
        $container->setParameter('dynamic_db.mappings', $mappings);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }
}