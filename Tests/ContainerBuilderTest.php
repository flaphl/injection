<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\ServiceDefinition;
use Flaphl\Element\Injection\BagParameters\ParameterBag;
use Flaphl\Element\Injection\Tests\Fixtures\{
    SimpleService,
    DependentService,
    ServiceWithProperties,
    ServiceWithMethods,
    TestServiceInterface,
    TestServiceImplementation
};
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for the ContainerBuilder class.
 *
 * @package Flaphl\Element\Injection\Tests
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerBuilderTest extends TestCase
{
    private ContainerBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
    }

    public function testBuilderCreation(): void
    {
        $this->assertInstanceOf(ContainerBuilder::class, $this->builder);
    }

    public function testBuilderWithCustomParameterBag(): void
    {
        $parameterBag = new ParameterBag(['initial' => 'value']);
        $builder = new ContainerBuilder($parameterBag);
        
        $this->assertEquals('value', $builder->getParameter('initial'));
    }

    public function testRegisterService(): void
    {
        $definition = $this->builder->register('simple', SimpleService::class);
        
        $this->assertInstanceOf(ServiceDefinition::class, $definition);
        $this->assertEquals(SimpleService::class, $definition->getClass());
        
        $definitions = $this->builder->getDefinitions();
        $this->assertArrayHasKey('simple', $definitions);
    }

    public function testRegisterServiceWithoutClass(): void
    {
        $definition = $this->builder->register(SimpleService::class);
        
        $this->assertEquals(SimpleService::class, $definition->getClass());
    }

    public function testAutowireService(): void
    {
        $definition = $this->builder->autowire('simple', SimpleService::class);
        
        $this->assertTrue($definition->isAutowired());
        $this->assertInstanceOf(ServiceDefinition::class, $definition);
    }

    public function testSetAndGetParameter(): void
    {
        $this->builder->setParameter('test.param', 'test-value');
        
        $this->assertEquals('test-value', $this->builder->getParameter('test.param'));
    }

    public function testSetParameters(): void
    {
        $parameters = ['param1' => 'value1', 'param2' => 'value2'];
        $this->builder->setParameters($parameters);
        
        $this->assertEquals('value1', $this->builder->getParameter('param1'));
        $this->assertEquals('value2', $this->builder->getParameter('param2'));
    }

    public function testLoadFromConfig(): void
    {
        $config = [
            'parameters' => [
                'app.name' => 'TestApp',
                'app.version' => '1.0.0'
            ],
            'services' => [
                'simple.service' => [
                    'class' => SimpleService::class
                ],
                'dependent.service' => [
                    'class' => DependentService::class,
                    'arguments' => ['@simple.service', 'injected message']
                ]
            ]
        ];
        
        $this->builder->loadFromConfig($config);
        
        $this->assertEquals('TestApp', $this->builder->getParameter('app.name'));
        $this->assertEquals('1.0.0', $this->builder->getParameter('app.version'));
        
        $definitions = $this->builder->getDefinitions();
        $this->assertArrayHasKey('simple.service', $definitions);
        $this->assertArrayHasKey('dependent.service', $definitions);
    }

    public function testLoadFromPhpFile(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'config') . '.php';
        $config = [
            'parameters' => ['from.file' => 'value'],
            'services' => ['file.service' => ['class' => SimpleService::class]]
        ];
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');
        
        try {
            $this->builder->loadFromFile($configFile);
            
            $this->assertEquals('value', $this->builder->getParameter('from.file'));
            $this->assertArrayHasKey('file.service', $this->builder->getDefinitions());
        } finally {
            unlink($configFile);
        }
    }

    public function testLoadFromJsonFile(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'config') . '.json';
        $config = [
            'parameters' => ['from.json' => 'value'],
            'services' => ['json.service' => ['class' => SimpleService::class]]
        ];
        file_put_contents($configFile, json_encode($config));
        
        try {
            $this->builder->loadFromFile($configFile);
            
            $this->assertEquals('value', $this->builder->getParameter('from.json'));
            $this->assertArrayHasKey('json.service', $this->builder->getDefinitions());
        } finally {
            unlink($configFile);
        }
    }

    public function testLoadFromNonExistentFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Configuration file [non-existent.php] not found');
        
        $this->builder->loadFromFile('non-existent.php');
    }

    public function testLoadFromUnsupportedFileFormat(): void
    {
        $configFile = tempnam(sys_get_temp_dir(), 'config') . '.xml';
        file_put_contents($configFile, '<config></config>');
        
        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Unsupported configuration file format [xml]');
            
            $this->builder->loadFromFile($configFile);
        } finally {
            unlink($configFile);
        }
    }

    public function testAddCompilerPass(): void
    {
        $executed = false;
        
        $this->builder->addCompilerPass(function ($container, $builder) use (&$executed) {
            $executed = true;
        });
        
        $this->builder->build();
        $this->assertTrue($executed);
    }

    public function testCompilerPassPriority(): void
    {
        $execution_order = [];
        
        $this->builder->addCompilerPass(function () use (&$execution_order) {
            $execution_order[] = 'second';
        }, 0);
        
        $this->builder->addCompilerPass(function () use (&$execution_order) {
            $execution_order[] = 'first';
        }, 10);
        
        $this->builder->build();
        $this->assertEquals(['first', 'second'], $execution_order);
    }

    public function testSetBuildConfig(): void
    {
        $config = ['compile' => false, 'debug' => true];
        $this->builder->setBuildConfig($config);
        
        // Build config is private, so we test its effect
        $this->builder->addCompilerPass(function () {
            throw new \Exception('Should not execute');
        });
        
        // Should not throw since compilation is disabled
        $container = $this->builder->build();
        $this->assertNotNull($container);
    }

    public function testSetCompile(): void
    {
        $this->builder->setCompile(false);
        
        $this->builder->addCompilerPass(function () {
            throw new \Exception('Should not execute');
        });
        
        $container = $this->builder->build();
        $this->assertNotNull($container);
    }

    public function testSetDebug(): void
    {
        $result = $this->builder->setDebug(true);
        $this->assertSame($this->builder, $result);
    }

    public function testBuild(): void
    {
        $this->builder->setParameter('test.param', 'test-value');
        $this->builder->register('simple', SimpleService::class);
        
        $container = $this->builder->build();
        
        $this->assertTrue($container->hasParameter('test.param'));
        $this->assertEquals('test-value', $container->getParameter('test.param'));
        $this->assertTrue($container->has('simple'));
    }

    public function testGetContainer(): void
    {
        $container = $this->builder->getContainer();
        $this->assertNotNull($container);
    }

    public function testFindTaggedServiceIds(): void
    {
        $this->builder->register('service1', SimpleService::class)
            ->addTag('test.tag', ['priority' => 10]);
        
        $this->builder->register('service2', SimpleService::class)
            ->addTag('test.tag', ['priority' => 5]);
        
        $this->builder->register('service3', SimpleService::class)
            ->addTag('other.tag');
        
        $this->builder->build(); // Build to process tags
        
        $taggedServices = $this->builder->findTaggedServiceIds('test.tag');
        $this->assertArrayHasKey('service1', $taggedServices);
        $this->assertArrayHasKey('service2', $taggedServices);
        $this->assertArrayNotHasKey('service3', $taggedServices);
    }

    public function testComplexServiceConfiguration(): void
    {
        $config = [
            'services' => [
                'complex.service' => [
                    'class' => ServiceWithMethods::class,
                    'calls' => [
                        ['addData', ['key1', 'value1']],
                        ['setConfig', [['config_key' => 'config_value']]]
                    ],
                    'properties' => [
                        // Properties would need to be public to work
                    ],
                    'tags' => [
                        ['name' => 'tagged.service', 'priority' => 10],
                        'simple.tag'
                    ],
                    'public' => true,
                    'shared' => true,
                    'autowire' => false
                ]
            ]
        ];
        
        $this->builder->loadFromConfig($config);
        $definitions = $this->builder->getDefinitions();
        
        $this->assertArrayHasKey('complex.service', $definitions);
        $definition = $definitions['complex.service'];
        
        $this->assertTrue($definition->isPublic());
        $this->assertTrue($definition->isShared());
        $this->assertFalse($definition->isAutowired());
        
        $tags = $definition->getTags();
        $this->assertArrayHasKey('tagged.service', $tags);
        $this->assertArrayHasKey('simple.tag', $tags);
    }

    public function testServiceInstantiation(): void
    {
        $this->builder->register('simple', SimpleService::class);
        $this->builder->register('dependent', DependentService::class)
            ->setArguments(['@simple', 'custom message']);
        
        $container = $this->builder->build();
        $service = $container->get('dependent');
        
        $this->assertInstanceOf(DependentService::class, $service);
        $this->assertEquals('custom message', $service->getMessage());
        $this->assertInstanceOf(SimpleService::class, $service->getSimpleService());
    }

    public function testParameterResolution(): void
    {
        $this->builder->setParameter('message', 'parameter message');
        $this->builder->register('simple', SimpleService::class);
        $this->builder->register('dependent', DependentService::class)
            ->setArguments(['@simple', '%message%']);
        
        $container = $this->builder->build();
        $service = $container->get('dependent');
        
        $this->assertEquals('parameter message', $service->getMessage());
    }

    public function testFluentInterface(): void
    {
        $builderResult = $this->builder
            ->setParameter('param', 'value')
            ->setDebug(false);
        
        $serviceDefinition = $this->builder->register('service', SimpleService::class);
        
        $this->assertSame($this->builder, $builderResult);
        $this->assertInstanceOf(ServiceDefinition::class, $serviceDefinition);
    }
}