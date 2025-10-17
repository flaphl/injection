<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\BagParameters;

use Flaphl\Element\Injection\BagParameters\ContainerBag;
use Flaphl\Element\Injection\BagParameters\ContainerBagInterface;
use Flaphl\Element\Injection\BagParameters\ParameterBag;
use Flaphl\Element\Injection\Container;
use Flaphl\Element\Injection\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for the ContainerBag class.
 *
 * @package Flaphl\Element\Injection\Tests\BagParameters
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerBagTest extends TestCase
{
    private ContainerBag $bag;
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->bag = new ContainerBag();
        $this->bag->setContainer($this->container);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ContainerBagInterface::class, $this->bag);
    }

    public function testExtendsParameterBag(): void
    {
        $this->assertInstanceOf(ParameterBag::class, $this->bag);
    }

    public function testSetAndGetContainer(): void
    {
        $newContainer = new Container();
        $this->bag->setContainer($newContainer);
        
        $this->assertSame($newContainer, $this->bag->getContainer());
    }

    public function testBindServiceParameter(): void
    {
        $this->bag->bindServiceParameter('my.service', 'host', 'localhost');
        $this->bag->bindServiceParameter('my.service', 'port', 3306);
        
        $params = $this->bag->getServiceParameters('my.service');
        $expected = ['host' => 'localhost', 'port' => 3306];
        
        $this->assertEquals($expected, $params);
    }

    public function testGetServiceParametersForNonExistentService(): void
    {
        $params = $this->bag->getServiceParameters('non.existent');
        $this->assertEmpty($params);
    }

    public function testResolveWithContainer(): void
    {
        // Register a service
        $this->container->bind('test.service', SimpleService::class);
        
        // Set parameter with service reference
        $this->bag->set('my.service', '@test.service');
        
        $resolved = $this->bag->resolveWithContainer('my.service');
        $this->assertInstanceOf(SimpleService::class, $resolved);
    }

    public function testResolveWithContainerParameterReference(): void
    {
        $this->bag->set('base.url', 'https://example.com');
        $this->bag->set('api.url', '%base.url%');
        
        $resolved = $this->bag->resolveWithContainer('api.url');
        $this->assertEquals('https://example.com', $resolved);
    }

    public function testResolveWithContainerNonExistentService(): void
    {
        $this->bag->set('service', '@non.existent');
        
        $resolved = $this->bag->resolveWithContainer('service');
        $this->assertEquals('@non.existent', $resolved); // Should return original value
    }

    public function testResolveWithContainerNonExistentParameter(): void
    {
        $this->bag->set('param', '%non.existent%');
        
        $resolved = $this->bag->resolveWithContainer('param');
        $this->assertEquals('%non.existent%', $resolved); // Should return original value
    }

    public function testSetEnvironmentParameters(): void
    {
        $devParams = ['debug' => true, 'db.host' => 'localhost'];
        $prodParams = ['debug' => false, 'db.host' => 'prod.example.com'];
        
        $this->bag->setEnvironmentParameters('dev', $devParams);
        $this->bag->setEnvironmentParameters('prod', $prodParams);
        
        $this->assertEquals($devParams, $this->bag->getEnvironmentParameters('dev'));
        $this->assertEquals($prodParams, $this->bag->getEnvironmentParameters('prod'));
    }

    public function testGetEnvironmentParametersForNonExistentEnvironment(): void
    {
        $params = $this->bag->getEnvironmentParameters('non.existent');
        $this->assertEmpty($params);
    }

    public function testImportFrom(): void
    {
        $sourceBag = new ParameterBag(['param1' => 'value1', 'param2' => 'value2']);
        
        $this->bag->importFrom($sourceBag);
        
        $this->assertEquals('value1', $this->bag->getWithDefault('param1', null));
        $this->assertEquals('value2', $this->bag->getWithDefault('param2', null));
    }

    public function testImportFromWithMapping(): void
    {
        $sourceBag = new ParameterBag(['old.param1' => 'value1', 'old.param2' => 'value2']);
        $mapping = ['old.param1' => 'new.param1', 'old.param2' => 'new.param2'];
        
        $this->bag->importFrom($sourceBag, $mapping);
        
        $this->assertEquals('value1', $this->bag->getWithDefault('new.param1', null));
        $this->assertEquals('value2', $this->bag->getWithDefault('new.param2', null));
        $this->assertNull($this->bag->getWithDefault('old.param1', null));
    }

    public function testExportTo(): void
    {
        $this->bag->set('param1', 'value1');
        $this->bag->set('param2', 'value2');
        
        $targetBag = new ParameterBag();
        $this->bag->exportTo($targetBag);
        
        $this->assertEquals('value1', $targetBag->getWithDefault('param1', null));
        $this->assertEquals('value2', $targetBag->getWithDefault('param2', null));
    }

    public function testExportToWithSpecificKeys(): void
    {
        $this->bag->set('param1', 'value1');
        $this->bag->set('param2', 'value2');
        $this->bag->set('param3', 'value3');
        
        $targetBag = new ParameterBag();
        $this->bag->exportTo($targetBag, ['param1', 'param3']);
        
        $this->assertEquals('value1', $targetBag->getWithDefault('param1', null));
        $this->assertNull($targetBag->getWithDefault('param2', null));
        $this->assertEquals('value3', $targetBag->getWithDefault('param3', null));
    }

    public function testGetAllExtended(): void
    {
        $this->bag->set('param1', 'value1');
        $this->bag->bindServiceParameter('service1', 'host', 'localhost');
        $this->bag->setEnvironmentParameters('dev', ['debug' => true]);
        
        $extended = $this->bag->getAllExtended();
        
        $this->assertArrayHasKey('parameters', $extended);
        $this->assertArrayHasKey('serviceParameters', $extended);
        $this->assertArrayHasKey('environmentParameters', $extended);
        
        $this->assertEquals(['param1' => 'value1'], $extended['parameters']);
        $this->assertEquals(['service1' => ['host' => 'localhost']], $extended['serviceParameters']);
        $this->assertEquals(['dev' => ['debug' => true]], $extended['environmentParameters']);
    }

    public function testLoadEnvironment(): void
    {
        $this->bag->setEnvironmentParameters('dev', ['debug' => true, 'cache' => false]);
        
        $this->bag->loadEnvironment('dev');
        
        $this->assertTrue($this->bag->getWithDefault('debug', false));
        $this->assertFalse($this->bag->getWithDefault('cache', true));
    }

    public function testProcessedGetMethod(): void
    {
        // Set up service reference
        $this->container->bind('test.service', SimpleService::class);
        $this->bag->set('service.ref', '@test.service');
        
        // Set up parameter reference
        $this->bag->set('base.url', 'https://example.com');
        $this->bag->set('api.url', '%base.url%');
        
        $service = $this->bag->get('service.ref');
        $this->assertInstanceOf(SimpleService::class, $service);
        
        $url = $this->bag->get('api.url');
        $this->assertEquals('https://example.com', $url);
    }

    public function testProcessedGetWithDefaultMethod(): void
    {
        $this->bag->set('base.url', 'https://example.com');
        $this->bag->set('api.url', '%base.url%');
        
        $url = $this->bag->getWithDefault('api.url', 'default');
        $this->assertEquals('https://example.com', $url);
        
        $default = $this->bag->getWithDefault('non.existent', 'default');
        $this->assertEquals('default', $default);
    }

    public function testComplexParameterProcessing(): void
    {
        $this->bag->set('protocol', 'https');
        $this->bag->set('domain', 'example.com');
        $this->bag->set('port', '8080');
        $this->bag->set('path', '/api/v1');
        
        // Test individual parameter resolution
        $this->assertEquals('https', $this->bag->get('protocol'));
        $this->assertEquals('example.com', $this->bag->get('domain'));
        $this->assertEquals('8080', $this->bag->get('port'));
        $this->assertEquals('/api/v1', $this->bag->get('path'));
        
        // Test simple parameter references
        $this->bag->set('base.url', '%protocol%://example.com');
        $baseUrl = $this->bag->get('base.url');
        $this->assertEquals('https://example.com', $baseUrl);
    }

    public function testFluentInterface(): void
    {
        $result = $this->bag
            ->setContainer($this->container)
            ->bindServiceParameter('service', 'param', 'value')
            ->setEnvironmentParameters('dev', ['debug' => true]);
        
        $this->assertSame($this->bag, $result);
    }

    public function testWithoutContainer(): void
    {
        $bagWithoutContainer = new ContainerBag();
        $bagWithoutContainer->set('service.ref', '@test.service');
        
        $resolved = $bagWithoutContainer->resolveWithContainer('service.ref');
        $this->assertEquals('@test.service', $resolved); // Should return original value
    }
}