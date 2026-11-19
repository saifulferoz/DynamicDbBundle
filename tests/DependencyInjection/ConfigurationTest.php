<?php

namespace Feroz\DynamicDbBundle\Tests\DependencyInjection;

use Feroz\DynamicDbBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testMappingsAreOptional(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        $this->assertSame(['managers' => []], $config);
    }

    public function testManagerWithoutMappingsIsAccepted(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [
            ['managers' => ['analytics' => []]],
        ]);

        $this->assertSame([], $config['managers']['analytics']['mappings']);
    }
}
