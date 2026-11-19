<?php

namespace Feroz\DynamicDbBundle\Tests\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Feroz\DynamicDbBundle\DynamicDbBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal application kernel: FrameworkBundle + DoctrineBundle + this bundle on SQLite.
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        // DynamicDbBundle first on purpose: decoration must not depend on registration order.
        yield new DynamicDbBundle();
        yield new FrameworkBundle();
        yield new DoctrineBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/dynamic-db-bundle-tests/'.md5(__DIR__).'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/dynamic-db-bundle-tests/'.md5(__DIR__).'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
        ]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
            'orm' => ['mappings' => [
                'Test' => [
                    'type' => 'attribute',
                    'is_bundle' => false,
                    'dir' => __DIR__.'/Entity',
                    'prefix' => 'Feroz\DynamicDbBundle\Tests\Functional\Entity',
                ],
            ]],
        ]);
    }
}
