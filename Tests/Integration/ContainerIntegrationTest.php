<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Integration;

use Flaphl\Element\Injection\Container;
use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\BagParameters\ContainerBag;
use Flaphl\Element\Injection\Tests\Fixtures\{
    SimpleService,
    DependentService,
    ServiceWithProperties,
    ServiceWithMethods,
    TestServiceInterface,
    TestServiceImplementation,
    AlternativeTestService
};
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for complete dependency injection workflows.
 *
 * @package Flaphl\Element\Injection\Tests\Integration
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerIntegrationTest extends TestCase
{
    public function testCompleteApplicationWorkflow(): void
    {
        // Create container builder
        $builder = new ContainerBuilder();
        
        // Set application parameters
        $builder->setParameters([
            'app.name' => 'TestApp',
            'app.version' => '1.0.0',
            'app.debug' => true,
            'db.host' => 'localhost',
            'db.port' => 3306,
            'cache.ttl' => 3600
        ]);
        
        // Register services
        $builder->register('app.simple_service', SimpleService::class);
        
        $builder->register('app.dependent_service', DependentService::class)
            ->setArguments(['@app.simple_service', '%app.name%']);
        
        $builder->register('app.config_service', ServiceWithMethods::class)
            ->addMethodCall('addData', ['app_name', '%app.name%'])
            ->addMethodCall('addData', ['version', '%app.version%'])
            ->addMethodCall('setConfig', [['debug' => '%app.debug%']]);
        
        // Register interface binding
        $builder->register(TestServiceInterface::class, TestServiceImplementation::class);
        
        // Build container
        $container = $builder->build();
        
        // Test service resolution
        $simpleService = $container->get('app.simple_service');
        $this->assertInstanceOf(SimpleService::class, $simpleService);
        
        $dependentService = $container->get('app.dependent_service');
        $this->assertInstanceOf(DependentService::class, $dependentService);
        $this->assertEquals('TestApp', $dependentService->getMessage());
        $this->assertSame($simpleService, $dependentService->getSimpleService());
        
        $configService = $container->get('app.config_service');
        $this->assertInstanceOf(ServiceWithMethods::class, $configService);
        $data = $configService->getData();
        $this->assertEquals('TestApp', $data['app_name']);
        $this->assertEquals('1.0.0', $data['version']);
        $this->assertTrue($data['debug']);
        
        $interfaceService = $container->get(TestServiceInterface::class);
        $this->assertInstanceOf(TestServiceImplementation::class, $interfaceService);
        
        // Test parameter access
        $this->assertEquals('TestApp', $container->getParameter('app.name'));
        $this->assertEquals(3306, $container->getParameter('db.port'));
    }

    public function testEnvironmentSpecificConfiguration(): void
    {
        $parameterBag = new ContainerBag();
        $builder = new ContainerBuilder($parameterBag);
        
        // Set base parameters
        $builder->setParameters([
            'app.name' => 'MyApp',
            'debug' => false
        ]);
        
        // Set environment-specific parameters
        $parameterBag->setEnvironmentParameters('development', [
            'debug' => true,
            'db.host' => 'localhost',
            'cache.enabled' => false
        ]);
        
        $parameterBag->setEnvironmentParameters('production', [
            'debug' => false,
            'db.host' => 'prod.example.com',
            'cache.enabled' => true
        ]);
        
        // Load development environment
        $parameterBag->loadEnvironment('development');
        
        // Register service that depends on environment
        $builder->register('app.service', DependentService::class)
            ->setArguments(['@simple', '%app.name%']);
        
        $builder->register('simple', SimpleService::class);
        
        $container = $builder->build();
        
        // Test environment-specific values
        $this->assertTrue($container->getParameter('debug'));
        $this->assertEquals('localhost', $container->getParameter('db.host'));
        $this->assertFalse($container->getParameter('cache.enabled'));
        
        $service = $container->get('app.service');
        $this->assertEquals('MyApp', $service->getMessage());
    }

    public function testServiceTaggingAndDiscovery(): void
    {
        $builder = new ContainerBuilder();
        
        // Register services with tags
        $builder->register('handler.simple', SimpleService::class)
            ->addTag('event.handler', ['priority' => 10, 'event' => 'user.login']);
        
        $builder->register('handler.dependent', DependentService::class)
            ->setArguments(['@handler.simple', 'event message'])
            ->addTag('event.handler', ['priority' => 5, 'event' => 'user.logout']);
        
        $builder->register('other.service', ServiceWithMethods::class)
            ->addTag('data.processor');
        
        $container = $builder->build();
        
        // Test tagged service discovery
        $eventHandlers = $builder->findTaggedServiceIds('event.handler');
        $this->assertCount(2, $eventHandlers);
        $this->assertArrayHasKey('handler.simple', $eventHandlers);
        $this->assertArrayHasKey('handler.dependent', $eventHandlers);
        
        $dataProcessors = $builder->findTaggedServiceIds('data.processor');
        $this->assertCount(1, $dataProcessors);
        $this->assertArrayHasKey('other.service', $dataProcessors);
        
        // Test tag attributes
        $this->assertEquals(10, $eventHandlers['handler.simple']['priority']);
        $this->assertEquals('user.login', $eventHandlers['handler.simple']['event']);
    }

    public function testComplexParameterResolution(): void
    {
        $parameterBag = new ContainerBag();
        $container = new Container();
        $parameterBag->setContainer($container);
        
        // Register service
        $container->instance('config.service', new ServiceWithMethods());
        
        // Set parameters with references
        $parameterBag->set('service.reference', '@config.service');
        $parameterBag->set('base.url', 'https://api.example.com');
        $parameterBag->set('api.endpoint', '%base.url%');
        
        // Test service reference resolution
        $service = $parameterBag->resolveWithContainer('service.reference');
        $this->assertInstanceOf(ServiceWithMethods::class, $service);
        
        // Test parameter reference resolution
        $endpoint = $parameterBag->resolveWithContainer('api.endpoint');
        $this->assertEquals('https://api.example.com', $endpoint);
        
        // Test processed get methods
        $processedService = $parameterBag->get('service.reference');
        $this->assertInstanceOf(ServiceWithMethods::class, $processedService);
        
        $processedEndpoint = $parameterBag->get('api.endpoint');
        $this->assertEquals('https://api.example.com', $processedEndpoint);
    }

    public function testCompilerPassExecution(): void
    {
        $builder = new ContainerBuilder();
        
        // Track compiler pass execution
        $executionLog = [];
        
        // Add compiler passes with different priorities
        $builder->addCompilerPass(function ($container, $builder) use (&$executionLog) {
            $executionLog[] = 'low-priority';
            // Add a parameter during compilation
            $container->setParameter('compiled.param', 'compiled-value');
        }, 0);
        
        $builder->addCompilerPass(function ($container, $builder) use (&$executionLog) {
            $executionLog[] = 'high-priority';
            // Register a service during compilation
            $container->bind('compiled.service', SimpleService::class);
        }, 10);
        
        $builder->addCompilerPass(function ($container, $builder) use (&$executionLog) {
            $executionLog[] = 'medium-priority';
        }, 5);
        
        $container = $builder->build();
        
        // Test execution order (high to low priority)
        $this->assertEquals(['high-priority', 'medium-priority', 'low-priority'], $executionLog);
        
        // Test that compiler passes affected the container
        $this->assertTrue($container->hasParameter('compiled.param'));
        $this->assertEquals('compiled-value', $container->getParameter('compiled.param'));
        $this->assertTrue($container->has('compiled.service'));
    }

    public function testFullConfigurationFileWorkflow(): void
    {
        // Create a temporary configuration file
        $configFile = tempnam(sys_get_temp_dir(), 'integration_config') . '.json';
        $config = [
            'parameters' => [
                'app.name' => 'IntegrationApp',
                'app.version' => '2.0.0',
                'database.host' => 'db.example.com',
                'database.port' => 5432,
                'features.cache' => true,
                'features.debug' => false
            ],
            'services' => [
                'app.main' => [
                    'class' => DependentService::class,
                    'arguments' => ['@app.dependency', '%app.name%']
                ],
                'app.dependency' => [
                    'class' => SimpleService::class,
                    'shared' => true
                ],
                'app.config' => [
                    'class' => ServiceWithMethods::class,
                    'calls' => [
                        ['addData', ['host', '%database.host%']],
                        ['addData', ['port', '%database.port%']],
                        ['setConfig', [['cache' => '%features.cache%', 'debug' => '%features.debug%']]]
                    ],
                    'tags' => [
                        ['name' => 'app.config', 'type' => 'database'],
                        'configuration'
                    ]
                ]
            ]
        ];
        
        file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
        
        try {
            $builder = new ContainerBuilder();
            $builder->loadFromFile($configFile);
            
            $container = $builder->build();
            
            // Test parameters
            $this->assertEquals('IntegrationApp', $container->getParameter('app.name'));
            $this->assertEquals(5432, $container->getParameter('database.port'));
            $this->assertTrue($container->getParameter('features.cache'));
            
            // Test service resolution
            $mainService = $container->get('app.main');
            $this->assertInstanceOf(DependentService::class, $mainService);
            $this->assertEquals('IntegrationApp', $mainService->getMessage());
            
            $dependency = $container->get('app.dependency');
            $this->assertInstanceOf(SimpleService::class, $dependency);
            $this->assertSame($dependency, $mainService->getSimpleService()); // Should be shared
            
            $configService = $container->get('app.config');
            $this->assertInstanceOf(ServiceWithMethods::class, $configService);
            
            $configData = $configService->getData();
            $this->assertEquals('db.example.com', $configData['host']);
            $this->assertEquals(5432, $configData['port']);
            $this->assertTrue($configData['cache']);
            $this->assertFalse($configData['debug']);
            
            // Test tagged services
            $configTags = $builder->findTaggedServiceIds('app.config');
            $this->assertArrayHasKey('app.config', $configTags);
            $this->assertEquals('database', $configTags['app.config']['type']);
            
        } finally {
            unlink($configFile);
        }
    }

    public function testServiceOverrideAndRebinding(): void
    {
        $container = new Container();
        
        // Initial binding
        $container->bind(TestServiceInterface::class, TestServiceImplementation::class);
        $service1 = $container->get(TestServiceInterface::class);
        $this->assertEquals('implementation', $service1->getType());
        
        // Override binding
        $container->bind(TestServiceInterface::class, AlternativeTestService::class);
        $service2 = $container->get(TestServiceInterface::class);
        $this->assertEquals('alternative', $service2->getType());
        
        // Test that new instances use the new binding
        $service3 = $container->make(TestServiceInterface::class);
        $this->assertEquals('alternative', $service3->getType());
    }

    public function testParameterAndServiceInteraction(): void
    {
        $builder = new ContainerBuilder();
        
        // Set parameters
        $builder->setParameters([
            'message.prefix' => 'Hello',
            'message.suffix' => 'World',
            'service.class' => SimpleService::class
        ]);
        
        // Register services using parameter references
        $builder->register('dynamic.service', '%service.class%');
        
        $builder->register('message.service', DependentService::class)
            ->setArguments(['@dynamic.service', '%message.prefix% %message.suffix%']);
        
        $container = $builder->build();
        
        $messageService = $container->get('message.service');
        $this->assertInstanceOf(DependentService::class, $messageService);
        $this->assertEquals('Hello World', $messageService->getMessage());
        
        $dynamicService = $container->get('dynamic.service');
        $this->assertInstanceOf(SimpleService::class, $dynamicService);
    }

    public function testContainerAsServiceDependency(): void
    {
        $container = new Container();
        
        // Register a service that depends on the container itself
        $container->bind('container.aware', function ($container) {
            return new class($container) {
                public function __construct(private $container) {}
                public function getContainer() { return $this->container; }
                public function getService(string $id) { return $this->container->get($id); }
            };
        });
        
        $container->bind('simple', SimpleService::class);
        
        $containerAware = $container->get('container.aware');
        $this->assertSame($container, $containerAware->getContainer());
        
        $simple = $containerAware->getService('simple');
        $this->assertInstanceOf(SimpleService::class, $simple);
    }
}