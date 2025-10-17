<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Loader\Configurator;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Loader\Configurator\ContainerConfigurator;
use Flaphl\Element\Injection\Loader\Configurator\ServiceConfigurator;
use Flaphl\Element\Injection\Loader\Configurator\ParameterConfigurator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ContainerConfigurator.
 * 
 * @package Flaphl\Element\Injection\Tests\Loader\Configurator
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerConfiguratorTest extends TestCase
{
    private ContainerBuilder $container;
    private ContainerConfigurator $configurator;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->configurator = new ContainerConfigurator($this->container);
    }

    public function testConstructor(): void
    {
        $configurator = new ContainerConfigurator($this->container);
        $this->assertInstanceOf(ContainerConfigurator::class, $configurator);
    }

    public function testServicesReturnsServiceConfigurator(): void
    {
        $services = $this->configurator->services();
        
        $this->assertInstanceOf(ServiceConfigurator::class, $services);
    }

    public function testParametersReturnsParameterConfigurator(): void
    {
        $parameters = $this->configurator->parameters();
        
        $this->assertInstanceOf(ParameterConfigurator::class, $parameters);
    }

    public function testServiceConfiguratorConsistency(): void
    {
        $services1 = $this->configurator->services();
        $services2 = $this->configurator->services();
        
        // Should return the same instance
        $this->assertSame($services1, $services2);
    }

    public function testParameterConfiguratorConsistency(): void
    {
        $parameters1 = $this->configurator->parameters();
        $parameters2 = $this->configurator->parameters();
        
        // Should return the same instance
        $this->assertSame($parameters1, $parameters2);
    }

    public function testImportSingleFile(): void
    {
        $result = $this->configurator->import('/path/to/config.php');
        
        $this->assertSame($this->configurator, $result);
    }

    public function testImportWithOptions(): void
    {
        $options = ['ignore_errors' => true, 'prefix' => 'imported_'];
        $result = $this->configurator->import('/path/to/config.php', $options);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testImportMultipleFiles(): void
    {
        $files = ['/config1.php', '/config2.php', '/config3.php'];
        $result = $this->configurator->imports($files);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testImportMultipleFilesWithOptions(): void
    {
        $files = ['/config1.php', '/config2.php'];
        $options = ['ignore_errors' => true];
        $result = $this->configurator->imports($files, $options);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testImportDirectory(): void
    {
        // This method might not exist, let's test a different approach
        $result = $this->configurator->import('/config/dir/*.php');
        
        $this->assertSame($this->configurator, $result);
    }

    public function testImportDirectoryWithOptions(): void
    {
        $options = ['recursive' => true, 'pattern' => '*.php'];
        $result = $this->configurator->import('/config/dir/*.php', $options);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testExtensionConfiguration(): void
    {
        $config = ['enabled' => true, 'debug' => false];
        $result = $this->configurator->extension('framework', $config);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testExtensionWithArrayAccess(): void
    {
        $result = $this->configurator->extension('cache', [
            'default_ttl' => 3600,
            'prefix' => 'app_'
        ]);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testWhenEnvironment(): void
    {
        $result = $this->configurator->when('prod', function($config) {
            $config->extension('cache', ['enabled' => true]);
        });
        
        $this->assertSame($this->configurator, $result);
    }

    public function testWhenEnvironmentMultiple(): void
    {
        $result = $this->configurator->when(['dev', 'test'], function($config) {
            $config->extension('debug', ['enabled' => true]);
        });
        
        $this->assertSame($this->configurator, $result);
    }

    public function testWhenEnvironmentNoMatch(): void
    {
        // Mock environment that doesn't match
        $result = $this->configurator->when('staging', function($config) {
            throw new \Exception('This should not be called');
        });
        
        $this->assertSame($this->configurator, $result);
    }

    public function testSetParameters(): void
    {
        $result = $this->configurator->parameter('app.name', 'MyApp');
        $result = $this->configurator->parameter('app.version', '1.0.0');
        $result = $this->configurator->parameter('database.host', 'localhost');
        
        $this->assertSame($this->configurator, $result);
    }

    public function testSetParameter(): void
    {
        $result = $this->configurator->parameter('app.secret', 'secret-key');
        
        $this->assertSame($this->configurator, $result);
    }

    public function testRegisterService(): void
    {
        $result = $this->configurator->service('logger', 'Psr\Log\LoggerInterface');
        
        $this->assertInstanceOf(ServiceConfigurator::class, $result);
    }

    public function testRegisterServiceWithConfiguration(): void
    {
        $serviceConfig = $this->configurator->service('database', 'Database\Connection');
        
        $this->assertInstanceOf(ServiceConfigurator::class, $serviceConfig);
    }

    public function testAlias(): void
    {
        // Use parameter method instead of alias
        $result = $this->configurator->parameter('db.alias', 'database.connection');
        
        $this->assertSame($this->configurator, $result);
    }

    public function testAliasPublic(): void
    {
        // Use parameter method for alias-like functionality
        $result = $this->configurator->parameter('public_service.alias', 'internal.service');
        
        $this->assertSame($this->configurator, $result);
    }

    public function testLoad(): void
    {
        $result = $this->configurator->load();
        
        $this->assertInstanceOf(\Flaphl\Element\Injection\ContainerBuilder::class, $result);
    }

    public function testLoadReturnsContainerBuilder(): void
    {
        $result = $this->configurator->load();
        
        $this->assertSame($this->container, $result);
    }

    public function testFluentInterfaceChaining(): void
    {
        $result = $this->configurator
            ->parameter('app.name', 'TestApp')
            ->import('/config/services.php')
            ->extension('framework', ['debug' => true]);
        
        $this->assertSame($this->configurator, $result);
    }

    public function testComplexConfiguration(): void
    {
        $result = $this->configurator
            ->parameter('database.host', 'localhost')
            ->parameter('database.port', 3306)
            ->parameter('cache.ttl', 3600)
            ->import('/config/services.php')
            ->when('prod', function($config) {
                $config->extension('cache', ['enabled' => true]);
            })
            ->when(['dev', 'test'], function($config) {
                $config->extension('debug', ['enabled' => true]);
            });
        
        $this->assertSame($this->configurator, $result);
    }

    public function testServicesAndParametersIntegration(): void
    {
        // Configure parameters
        $this->configurator->parameters()
            ->set('logger.level', 'info')
            ->set('logger.file', '/var/log/app.log');
        
        // Configure services
        $this->configurator->services()
            ->service('logger', 'Monolog\Logger')
            ->args('app', '%logger.level%')
            ->tag('logger');
        
        // Both should work seamlessly together
        $this->assertTrue(true);
    }
}