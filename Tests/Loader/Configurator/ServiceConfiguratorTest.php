<?php

namespace Flaphl\Element\Injection\Tests\Loader\Configurator;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Loader\Configurator\ServiceConfigurator;
use PHPUnit\Framework\TestCase;

class ServiceConfiguratorTest extends TestCase
{
    private ContainerBuilder $container;
    private ServiceConfigurator $configurator;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->configurator = new ServiceConfigurator($this->container);
    }

    public function testBasicServiceConfiguration(): void
    {
        $this->configurator
            ->service('test_service', 'stdClass')
            ->arg('argument1')
            ->service_ref('other_service')
            ->param_ref('parameter')
            ->call('setProperty', ['value'])
            ->property('publicProperty', 'public_value')
            ->tag('my_tag', ['priority' => 10])
            ->private()
            ->prototype()
            ->autowire();

        $this->configurator->load();

        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);

        $definition = $definitions['test_service'];
        $this->assertEquals('stdClass', $definition->getClass());
        $this->assertFalse($definition->isPublic());
        $this->assertFalse($definition->isShared());
        $this->assertTrue($definition->isAutowired());

        $arguments = $definition->getArguments();
        $this->assertCount(3, $arguments);
        $this->assertEquals('argument1', $arguments[0]);
        $this->assertEquals('@other_service', $arguments[1]);
        $this->assertEquals('%parameter%', $arguments[2]);

        $methodCalls = $definition->getMethodCalls();
        $this->assertCount(1, $methodCalls);
        $this->assertEquals(['setProperty', ['value']], $methodCalls[0]);

        $properties = $definition->getProperties();
        $this->assertEquals('public_value', $properties['publicProperty']);

        $tags = $definition->getTags();
        $this->assertArrayHasKey('my_tag', $tags);
        $this->assertEquals(['priority' => 10], $tags['my_tag']);
    }

    public function testFluentServiceConfiguration(): void
    {
        $this->configurator
            ->service('logger', 'Monolog\\Logger')
            ->args('app', [])
            ->call('pushHandler', ['@log_handler'])
            ->public();

        $this->configurator
            ->service('log_handler', 'Monolog\\Handler\\StreamHandler')
            ->args('/var/log/app.log')
            ->private();

        $this->configurator->load();

        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('logger', $definitions);
        $this->assertArrayHasKey('log_handler', $definitions);

        $logger = $definitions['logger'];
        $this->assertTrue($logger->isPublic());
        $this->assertEquals(['app', []], $logger->getArguments());

        $handler = $definitions['log_handler'];
        $this->assertFalse($handler->isPublic());
        $this->assertEquals(['/var/log/app.log'], $handler->getArguments());
    }

    public function testExportConfiguration(): void
    {
        $this->configurator
            ->service('test_service', 'stdClass')
            ->args('arg1', 'arg2')
            ->autowire()
            ->private();

        $config = $this->configurator->export();

        $expected = [
            'services' => [
                'test_service' => [
                    'class' => 'stdClass',
                    'arguments' => ['arg1', 'arg2'],
                    'autowire' => true,
                    'public' => false,
                ],
            ],
        ];

        $this->assertEquals($expected, $config);
    }

    public function testThrowsExceptionWhenNoServiceConfigured(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No service is currently being configured');

        $this->configurator->args('test');
    }
}