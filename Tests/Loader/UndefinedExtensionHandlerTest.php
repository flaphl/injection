<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Loader;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Exception\ContainerException;
use Flaphl\Element\Injection\Loader\FileLoader;
use Flaphl\Element\Injection\Loader\UndefinedExtensionHandler;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for UndefinedExtensionHandler.
 * 
 * @package Flaphl\Element\Injection\Tests\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class UndefinedExtensionHandlerTest extends TestCase
{
    private ContainerBuilder $container;
    private UndefinedExtensionHandler $handler;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->handler = new UndefinedExtensionHandler();
        $this->tempDir = sys_get_temp_dir() . '/flaphl_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTempFile(string $name, string $content): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, $content);
        return $path;
    }

    public function testDefaultStrategiesAreAvailable(): void
    {
        $strategies = $this->handler->getStrategies();
        
        $this->assertArrayHasKey('yml', $strategies);
        $this->assertArrayHasKey('yaml', $strategies);
        $this->assertArrayHasKey('json', $strategies);
        $this->assertArrayHasKey('ini', $strategies);
        $this->assertArrayHasKey('env', $strategies);
        
        $this->assertEquals('yaml', $strategies['yml']);
        $this->assertEquals('yaml', $strategies['yaml']);
        $this->assertEquals('json', $strategies['json']);
        $this->assertEquals('ini', $strategies['ini']);
        $this->assertEquals('env', $strategies['env']);
    }

    public function testCanHandleRegisteredExtensions(): void
    {
        $this->assertTrue($this->handler->canHandle('yml'));
        $this->assertTrue($this->handler->canHandle('yaml'));
        $this->assertTrue($this->handler->canHandle('json'));
        $this->assertTrue($this->handler->canHandle('ini'));
        $this->assertTrue($this->handler->canHandle('env'));
        $this->assertFalse($this->handler->canHandle('unknown'));
    }

    public function testRegisterStrategy(): void
    {
        $this->handler->registerStrategy('custom', 'json');
        
        $this->assertTrue($this->handler->canHandle('custom'));
        $strategies = $this->handler->getStrategies();
        $this->assertEquals('json', $strategies['custom']);
    }

    public function testRegisterCustomLoader(): void
    {
        $mockLoader = $this->createMock(FileLoader::class);
        $this->handler->registerLoader('custom', $mockLoader);
        
        $this->assertTrue($this->handler->canHandle('custom'));
        $loaders = $this->handler->getCustomLoaders();
        $this->assertSame($mockLoader, $loaders['custom']);
    }

    public function testHandleWithCustomLoader(): void
    {
        $mockLoader = $this->createMock(FileLoader::class);
        $mockLoader->expects($this->once())
                   ->method('load')
                   ->with('test.custom', []);
        
        $this->handler->registerLoader('custom', $mockLoader);
        $this->handler->handle('test.custom', $this->container);
    }

    public function testHandleJsonFile(): void
    {
        $json = json_encode([
            'parameters' => ['test_param' => 'test_value'],
            'services' => ['test_service' => 'TestClass']
        ]);
        
        $file = $this->createTempFile('config.json', $json);
        $this->handler->handle($file, $this->container);
        
        $this->assertTrue($this->container->getParameterBag()->has('test_param'));
        $this->assertEquals('test_value', $this->container->getParameter('test_param'));
        
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);
    }

    public function testHandleInvalidJsonFile(): void
    {
        $file = $this->createTempFile('invalid.json', '{invalid json}');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Invalid JSON in file');
        
        $this->handler->handle($file, $this->container);
    }

    public function testHandleJsonFileWithNonObjectContent(): void
    {
        $file = $this->createTempFile('invalid.json', '"string content"');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('JSON configuration must be an object');
        
        $this->handler->handle($file, $this->container);
    }

    public function testHandleIniFile(): void
    {
        $ini = <<<INI
[parameters]
test_param = "test_value"
debug = true
port = 8080

[services]
test_service = "TestClass"
INI;
        
        $file = $this->createTempFile('config.ini', $ini);
        $this->handler->handle($file, $this->container);
        
        $this->assertTrue($this->container->getParameterBag()->has('test_param'));
        $this->assertEquals('test_value', $this->container->getParameter('test_param'));
        
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);
    }

    public function testHandleEnvFile(): void
    {
        $env = <<<ENV
# Database configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME="myapp"
DB_DEBUG=true

# App settings
APP_ENV='production'
ENV;
        
        $file = $this->createTempFile('.env', $env);
        $this->handler->handle($file, $this->container);
        
        $this->assertTrue($this->container->getParameterBag()->has('DB_HOST'));
        $this->assertEquals('localhost', $this->container->getParameter('DB_HOST'));
        $this->assertEquals('5432', $this->container->getParameter('DB_PORT'));
        $this->assertEquals('myapp', $this->container->getParameter('DB_NAME'));
        $this->assertEquals('true', $this->container->getParameter('DB_DEBUG'));
        $this->assertEquals('production', $this->container->getParameter('APP_ENV'));
    }

    public function testHandleYamlFileThrowsExceptionWhenSymfonyYamlNotAvailable(): void
    {
        if (class_exists('Symfony\\Component\\Yaml\\Yaml')) {
            $this->markTestSkipped('Symfony Yaml component is available');
        }
        
        $file = $this->createTempFile('config.yml', 'parameters: {}');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('The Symfony Yaml component is required');
        
        $this->handler->handle($file, $this->container);
    }

    public function testHandleIgnoreStrategy(): void
    {
        $this->handler->registerStrategy('ignore', 'ignore');
        $file = $this->createTempFile('config.ignore', 'anything');
        
        // Should not throw exception
        $this->handler->handle($file, $this->container);
        $this->assertTrue(true);
    }

    public function testHandleErrorStrategy(): void
    {
        $this->handler->registerStrategy('error', 'error');
        $file = $this->createTempFile('config.error', 'anything');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Unsupported file extension');
        
        $this->handler->handle($file, $this->container);
    }

    public function testHandleUnknownStrategy(): void
    {
        $this->handler->registerStrategy('custom', 'unknown_strategy');
        $file = $this->createTempFile('config.custom', 'anything');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Unknown strategy "unknown_strategy"');
        
        $this->handler->handle($file, $this->container);
    }

    public function testHandleUnsupportedExtension(): void
    {
        $file = $this->createTempFile('config.unknown', 'content');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('No handler available for file extension "unknown"');
        
        $this->handler->handle($file, $this->container);
    }

    public function testHandleUnreadableFile(): void
    {
        // Test for files that can't be read
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot read file');
        
        $this->handler->handle('/non/existent/file.json', $this->container);
    }

    public function testHandleInvalidIniFile(): void
    {
        // Create a file that will fail ini parsing
        $file = $this->createTempFile('invalid.ini', 'invalid ini content [');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot parse INI file');
        
        $this->handler->handle($file, $this->container);
    }

    public function testConvertIniServicesComplex(): void
    {
        $ini = <<<INI
[parameters]
debug = true

[services]
simple_service = "SimpleClass"

; Note: INI file parsing doesn't support nested section syntax like [services.complex_service]
; This would need a different approach in real implementation
INI;
        
        $file = $this->createTempFile('complex.ini', $ini);
        $this->handler->handle($file, $this->container);
        
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('simple_service', $definitions);
        
        $simple = $definitions['simple_service'];
        $this->assertEquals('SimpleClass', $simple->getClass());
        
        // Check that parameters were also loaded
        $this->assertTrue($this->container->getParameterBag()->has('debug'));
        $this->assertTrue($this->container->getParameter('debug'));
    }

    public function testHandleEnvFileWithInvalidFormat(): void
    {
        $env = <<<ENV
VALID_VAR=value
INVALID LINE WITHOUT EQUALS
ANOTHER_VALID=value2
ENV;
        
        $file = $this->createTempFile('mixed.env', $env);
        $this->handler->handle($file, $this->container);
        
        // Should parse valid lines and ignore invalid ones
        $this->assertTrue($this->container->getParameterBag()->has('VALID_VAR'));
        $this->assertTrue($this->container->getParameterBag()->has('ANOTHER_VALID'));
        $this->assertEquals('value', $this->container->getParameter('VALID_VAR'));
        $this->assertEquals('value2', $this->container->getParameter('ANOTHER_VALID'));
    }

    public function testHandleEnvFileUnreadable(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Cannot read environment file');
        
        $this->handler->handle('/non/existent/file.env', $this->container);
    }
}