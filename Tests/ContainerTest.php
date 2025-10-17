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

use Flaphl\Element\Injection\Container;
use Flaphl\Element\Injection\ContainerInterface;
use Flaphl\Element\Injection\Tests\Fixtures\{
    SimpleService,
    DependentService,
    CircularServiceA,
    CircularServiceB,
    ServiceWithProperties,
    ServiceWithMethods,
    TestServiceInterface,
    TestServiceImplementation,
    AlternativeTestService
};
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Flaphl\Element\Injection\Exception\{
    CircularReferenceException,
    NotFoundException
};

/**
 * Comprehensive tests for the Container class.
 *
 * @package Flaphl\Element\Injection\Tests
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    // ==================== Basic Container Functionality ====================

    public function testContainerImplementsContainerInterface(): void
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
        $this->assertInstanceOf(\Psr\Container\ContainerInterface::class, $this->container);
    }

    public function testBindAndGet(): void
    {
        $this->container->bind('simple', SimpleService::class);
        
        $this->assertTrue($this->container->has('simple'));
        $service = $this->container->get('simple');
        
        $this->assertInstanceOf(SimpleService::class, $service);
        $this->assertEquals('SimpleService', $service->getName());
    }

    public function testBindWithClosure(): void
    {
        $this->container->bind('closure', function ($container) {
            return new SimpleService();
        });
        
        $service = $this->container->get('closure');
        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testBindWithInstance(): void
    {
        $instance = new SimpleService();
        $this->container->instance('instance', $instance);
        
        $retrieved = $this->container->get('instance');
        $this->assertSame($instance, $retrieved);
    }

    public function testSingleton(): void
    {
        $this->container->singleton('singleton', SimpleService::class);
        
        $service1 = $this->container->get('singleton');
        $service2 = $this->container->get('singleton');
        
        $this->assertSame($service1, $service2);
    }

    public function testNonSharedBinding(): void
    {
        $this->container->bind('non-shared', SimpleService::class, false);
        
        $service1 = $this->container->get('non-shared');
        $service2 = $this->container->get('non-shared');
        
        $this->assertNotSame($service1, $service2);
        $this->assertInstanceOf(SimpleService::class, $service1);
        $this->assertInstanceOf(SimpleService::class, $service2);
    }

    // ==================== Dependency Injection ====================

    public function testDependencyInjection(): void
    {
        $this->container->bind('simple', SimpleService::class);
        $this->container->bind('dependent', DependentService::class);
        
        $service = $this->container->get('dependent');
        
        $this->assertInstanceOf(DependentService::class, $service);
        $this->assertInstanceOf(SimpleService::class, $service->getSimpleService());
        $this->assertEquals('default', $service->getMessage());
    }

    public function testDependencyInjectionWithParameters(): void
    {
        $this->container->bind('simple', SimpleService::class);
        
        $service = $this->container->make(DependentService::class, ['message' => 'custom']);
        
        $this->assertInstanceOf(DependentService::class, $service);
        $this->assertEquals('custom', $service->getMessage());
    }

    public function testInterfaceBinding(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceImplementation::class);
        
        $service = $this->container->get(TestServiceInterface::class);
        
        $this->assertInstanceOf(TestServiceImplementation::class, $service);
        $this->assertEquals('implementation', $service->getType());
    }

    // ==================== Advanced Features ====================

    public function testMakeWithoutBinding(): void
    {
        $service = $this->container->make(SimpleService::class);
        
        $this->assertInstanceOf(SimpleService::class, $service);
    }

    public function testCanMake(): void
    {
        $this->container->bind('bound', SimpleService::class);
        
        $this->assertTrue($this->container->canMake('bound'));
        $this->assertTrue($this->container->canMake(SimpleService::class));
        $this->assertFalse($this->container->canMake('non-existent'));
    }

    public function testCall(): void
    {
        $this->container->bind('simple', SimpleService::class);
        
        $result = $this->container->call(function (SimpleService $service) {
            return $service->getName();
        });
        
        $this->assertEquals('SimpleService', $result);
    }

    public function testCallWithClassMethod(): void
    {
        $this->container->bind('simple', SimpleService::class);
        $this->container->bind('dependent', DependentService::class);
        
        $result = $this->container->call([DependentService::class, 'getFullMessage']);
        
        $this->assertEquals('default from SimpleService', $result);
    }

    public function testCallWithStringCallback(): void
    {
        $this->container->bind('dependent', DependentService::class);
        $this->container->bind('simple', SimpleService::class);
        
        $result = $this->container->call('Flaphl\Element\Injection\Tests\Fixtures\DependentService@getFullMessage');
        
        $this->assertEquals('default from SimpleService', $result);
    }

    // ==================== Parameter Management ====================

    public function testSetAndGetParameter(): void
    {
        $this->container->setParameter('test.param', 'test-value');
        
        $this->assertTrue($this->container->hasParameter('test.param'));
        $this->assertEquals('test-value', $this->container->getParameter('test.param'));
    }

    public function testGetParameterWithDefault(): void
    {
        $value = $this->container->getParameter('non-existent', ['default' => 'default-value']);
        
        $this->assertEquals('default-value', $value);
    }

    public function testGetBindings(): void
    {
        $this->container->bind('service1', SimpleService::class);
        $this->container->bind('service2', DependentService::class);
        
        $bindings = $this->container->getBindings();
        
        $this->assertContains('service1', $bindings);
        $this->assertContains('service2', $bindings);
    }

    public function testGetParameterNames(): void
    {
        $this->container->setParameter('param1', 'value1');
        $this->container->setParameter('param2', 'value2');
        
        $names = $this->container->getParameterNames();
        
        $this->assertContains('param1', $names);
        $this->assertContains('param2', $names);
    }

    public function testUnbind(): void
    {
        $this->container->bind('service', SimpleService::class);
        $this->assertTrue($this->container->has('service'));
        
        $this->container->unbind('service');
        $this->assertFalse($this->container->has('service'));
    }

    // ==================== Contextual Binding ====================

    public function testContextualBinding(): void
    {
        $this->container->bind(TestServiceInterface::class, TestServiceImplementation::class);
        
        $builder = $this->container->when(DependentService::class);
        $this->assertInstanceOf(\Flaphl\Element\Injection\ContextualBindingBuilder::class, $builder);
    }

    // ==================== Error Handling ====================

    public function testGetNonExistentServiceThrowsException(): void
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->container->get('non-existent');
    }

    public function testCircularDependencyDetection(): void
    {
        $this->container->bind(CircularServiceA::class, CircularServiceA::class);
        $this->container->bind(CircularServiceB::class, CircularServiceB::class);
        
        $this->expectException(CircularReferenceException::class);
        $this->expectExceptionMessage('Circular reference detected');
        
        $this->container->get(CircularServiceA::class);
    }

    public function testNonInstantiableClassThrowsException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('is not instantiable');
        
        $this->container->make(TestServiceInterface::class);
    }

    public function testNonExistentClassThrowsException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('does not exist');
        
        $this->container->make('NonExistentClass');
    }

    // ==================== Edge Cases ====================

    public function testBindingOverride(): void
    {
        $this->container->bind('service', SimpleService::class);
        $first = $this->container->get('service');
        
        $this->container->bind('service', DependentService::class);
        $second = $this->container->get('service');
        
        $this->assertInstanceOf(SimpleService::class, $first);
        $this->assertInstanceOf(DependentService::class, $second);
    }

    public function testSingletonOverride(): void
    {
        $this->container->singleton('service', SimpleService::class);
        $first = $this->container->get('service');
        
        $this->container->singleton('service', SimpleService::class);
        $second = $this->container->get('service');
        
        // Should be different instances since binding was overridden
        $this->assertNotSame($first, $second);
    }

    public function testInstanceOverride(): void
    {
        $instance1 = new SimpleService();
        $instance2 = new SimpleService();
        
        $this->container->instance('service', $instance1);
        $retrieved1 = $this->container->get('service');
        
        $this->container->instance('service', $instance2);
        $retrieved2 = $this->container->get('service');
        
        $this->assertSame($instance1, $retrieved1);
        $this->assertSame($instance2, $retrieved2);
    }

    public function testEmptyParameterOperations(): void
    {
        $this->assertFalse($this->container->hasParameter('empty'));
        $this->assertEmpty($this->container->getParameterNames());
        $this->assertEmpty($this->container->getBindings());
    }

    public function testCallWithAdditionalParameters(): void
    {
        $result = $this->container->call(function ($custom, SimpleService $service) {
            return $custom . ' ' . $service->getName();
        }, ['custom' => 'Hello']);
        
        $this->assertEquals('Hello SimpleService', $result);
    }
}