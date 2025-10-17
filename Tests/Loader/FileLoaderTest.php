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
use PHPUnit\Framework\TestCase;

/**
 * Test cases for FileLoader base class.
 * 
 * @package Flaphl\Element\Injection\Tests\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class FileLoaderTest extends TestCase
{
    private ContainerBuilder $container;
    private TestFileLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new TestFileLoader($this->container);
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

    public function testConstructorSetsContainer(): void
    {
        $container = new ContainerBuilder();
        $loader = new TestFileLoader($container);
        
        $this->assertSame($container, $loader->getContainer());
    }

    public function testSupportsReturnsTrueForSupportedExtensions(): void
    {
        $this->assertTrue($this->loader->supports('config.test'));
        $this->assertTrue($this->loader->supports('/path/to/config.test'));
        $this->assertFalse($this->loader->supports('config.unsupported'));
    }

    public function testLoadThrowsExceptionForUnsupportedFile(): void
    {
        $file = $this->createTempFile('config.unsupported', '');
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not supported by');
        
        $this->loader->load($file);
    }

    public function testLoadThrowsExceptionForNonExistentFile(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Configuration file "/non/existent/file.test" not found');
        
        $this->loader->load('/non/existent/file.test');
    }

    public function testLoadIgnoresNonExistentFileWithIgnoreErrors(): void
    {
        $this->loader->load('/non/existent/file.test', ['ignore_errors' => true]);
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testLoadThrowsExceptionForUnreadableFile(): void
    {
        $file = $this->createTempFile('config.test', 'content');
        chmod($file, 0000); // Make unreadable
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('is not readable');
        
        try {
            $this->loader->load($file);
        } finally {
            chmod($file, 0644); // Restore permissions for cleanup
        }
    }

    public function testLoadCallsDoLoadForValidFile(): void
    {
        $file = $this->createTempFile('config.test', 'test content');
        
        $this->loader->load($file);
        
        $this->assertContains($file, $this->loader->getLoadedFiles());
        $this->assertEquals('test content', $this->loader->getLastLoadedContent());
    }

    public function testLoadPreventsCircularImports(): void
    {
        $file = $this->createTempFile('config.test', 'circular');
        
        // Simulate circular import by adding file to loading stack
        $this->loader->simulateLoading($file);
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular import detected');
        
        $this->loader->load($file);
    }

    public function testLoadAllowsCircularImportsWithOption(): void
    {
        $file = $this->createTempFile('config.test', 'circular');
        
        // Simulate circular import
        $this->loader->simulateLoading($file);
        
        $this->loader->load($file, ['allow_circular_imports' => true]);
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testLoadSkipsAlreadyLoadedFiles(): void
    {
        $file = $this->createTempFile('config.test', 'content');
        
        // Load once
        $this->loader->load($file);
        $loadCount = $this->loader->getLoadCount();
        
        // Load again
        $this->loader->load($file);
        
        // Should not load again
        $this->assertEquals($loadCount, $this->loader->getLoadCount());
    }

    public function testLoadHandlesExceptionsWithIgnoreErrors(): void
    {
        $file = $this->createTempFile('config.test', 'error_content');
        
        // Configure loader to throw exception
        $this->loader->setShouldThrowException(true);
        
        $this->loader->load($file, ['ignore_errors' => true]);
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    public function testLoadWrapsExceptionsInContainerException(): void
    {
        $file = $this->createTempFile('config.test', 'error_content');
        
        // Configure loader to throw exception
        $this->loader->setShouldThrowException(true);
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Error loading configuration file');
        
        $this->loader->load($file);
    }

    public function testResolvePathHandlesAbsolutePaths(): void
    {
        $absolutePath = '/absolute/path/config.test';
        $resolved = $this->loader->testResolvePath($absolutePath);
        
        $this->assertEquals($absolutePath, $resolved);
    }

    public function testResolvePathHandlesWindowsAbsolutePaths(): void
    {
        $windowsPath = 'C:\\absolute\\path\\config.test';
        $resolved = $this->loader->testResolvePath($windowsPath);
        
        $this->assertEquals($windowsPath, $resolved);
    }

    public function testResolvePathHandlesRelativePaths(): void
    {
        $relativePath = 'config.test';
        $resolved = $this->loader->testResolvePath($relativePath);
        
        $expected = getcwd() . DIRECTORY_SEPARATOR . $relativePath;
        $this->assertEquals($expected, $resolved);
    }

    public function testParseConfigHandlesImports(): void
    {
        $importFile = $this->createTempFile('import.test', 'imported');
        $config = [
            'imports' => [$importFile],
            'parameters' => ['test_param' => 'test_value'],
            'services' => ['test_service' => 'TestClass']
        ];
        
        $this->loader->testParseConfig($config, 'test_file.test', ['resolve_imports' => true]);
        
        // Check that import was processed
        $this->assertContains($importFile, $this->loader->getLoadedFiles());
        
        // Check that parameters were set
        $this->assertTrue($this->container->getParameterBag()->has('test_param'));
        $this->assertEquals('test_value', $this->container->getParameter('test_param'));
        
        // Check that services were registered
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);
    }

    public function testParseConfigSkipsImportsWhenDisabled(): void
    {
        $importFile = $this->createTempFile('import.test', 'imported');
        $config = [
            'imports' => [$importFile],
            'parameters' => ['test_param' => 'test_value']
        ];
        
        $this->loader->testParseConfig($config, 'test_file.test', ['resolve_imports' => false]);
        
        // Check that import was not processed
        $this->assertNotContains($importFile, $this->loader->getLoadedFiles());
        
        // Check that parameters were still set
        $this->assertTrue($this->container->getParameterBag()->has('test_param'));
    }

    public function testLoadParametersThrowsExceptionForInvalidName(): void
    {
        $parameters = [123 => 'invalid_name'];
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Parameter name must be a string');
        
        $this->loader->testLoadParameters($parameters, 'test_file.test');
    }

    public function testLoadServicesThrowsExceptionForInvalidId(): void
    {
        $services = [123 => 'TestClass'];
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Service ID must be a string');
        
        $this->loader->testLoadServices($services, 'test_file.test');
    }

    public function testLoadServiceWithStringConfig(): void
    {
        $this->loader->testLoadService('test_service', 'TestClass', 'test_file.test');
        
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);
        $definition = $definitions['test_service'];
        $this->assertEquals('TestClass', $definition->getClass());
    }

    public function testLoadServiceWithComplexConfig(): void
    {
        $config = [
            'class' => 'TestClass',
            'autowire' => true,
            'public' => false,
            'shared' => true,
            'arguments' => ['arg1', 'arg2'],
            'properties' => ['prop1' => 'value1'],
            'calls' => [
                ['method' => 'setTest', 'arguments' => ['test']]
            ],
            'tags' => [
                'tag1',
                ['name' => 'tag2', 'priority' => 10]
            ]
        ];
        
        $this->loader->testLoadService('test_service', $config, 'test_file.test');
        
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);
        $definition = $definitions['test_service'];
        
        $this->assertEquals('TestClass', $definition->getClass());
        $this->assertTrue($definition->isAutowired());
        $this->assertFalse($definition->isPublic());
        $this->assertTrue($definition->isShared());
    }

    public function testLoadServiceThrowsExceptionForInvalidConfig(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Invalid service configuration');
        
        $this->loader->testLoadService('test_service', 123, 'test_file.test');
    }

    public function testValidateSchemaReturnsTrue(): void
    {
        $config = ['test' => 'config'];
        $result = $this->loader->testValidateSchema($config, 'test_file.test');
        
        $this->assertTrue($result);
    }

    public function testLoadExtensionsDoesNothing(): void
    {
        $extensions = ['test' => 'extension'];
        
        // Should not throw exception
        $this->loader->testLoadExtensions($extensions, 'test_file.test', []);
        $this->assertTrue(true);
    }
}

/**
 * Test implementation of FileLoader for testing base functionality.
 */
class TestFileLoader extends FileLoader
{
    private array $loadedFiles = [];
    private string $lastLoadedContent = '';
    private int $loadCount = 0;
    private bool $shouldThrowException = false;

    public function getSupportedExtensions(): array
    {
        return ['test'];
    }

    protected function doLoad(string $file, array $options): void
    {
        $this->loadCount++;
        $this->loadedFiles[] = $file;
        
        if ($this->shouldThrowException) {
            throw new \RuntimeException('Test exception');
        }
        
        $this->lastLoadedContent = file_get_contents($file);
        
        // Parse as simple array if it's JSON-like
        if (str_starts_with($this->lastLoadedContent, '{')) {
            $config = json_decode($this->lastLoadedContent, true) ?: [];
            $this->parseConfig($config, $file, $options);
        }
    }

    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }

    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }

    public function getLastLoadedContent(): string
    {
        return $this->lastLoadedContent;
    }

    public function getLoadCount(): int
    {
        return $this->loadCount;
    }

    public function setShouldThrowException(bool $shouldThrow): void
    {
        $this->shouldThrowException = $shouldThrow;
    }

    public function simulateLoading(string $file): void
    {
        $this->loading[] = $file;
    }

    public function testResolvePath(string $file): string
    {
        return $this->resolvePath($file);
    }

    public function testParseConfig(array $config, string $file, array $options): void
    {
        $this->parseConfig($config, $file, $options);
    }

    public function testLoadParameters(array $parameters, string $file): void
    {
        $this->loadParameters($parameters, $file);
    }

    public function testLoadServices(array $services, string $file): void
    {
        $this->loadServices($services, $file);
    }

    public function testLoadService(string $id, mixed $config, string $file): void
    {
        $this->loadService($id, $config, $file);
    }

    public function testLoadExtensions(array $extensions, string $file, array $options): void
    {
        $this->loadExtensions($extensions, $file, $options);
    }

    public function testValidateSchema(array $config, string $file): bool
    {
        return $this->validateSchema($config, $file);
    }

    protected function loadImports(array $imports, string $currentFile, array $options): void
    {
        // Override to avoid instantiating non-existent loaders in tests
        $currentDir = dirname($currentFile);

        foreach ($imports as $import) {
            if (is_string($import)) {
                $importFile = $import;
                $importOptions = [];
            } elseif (is_array($import) && isset($import['resource'])) {
                $importFile = $import['resource'];
                $importOptions = array_merge($options, array_intersect_key($import, $options));
            } else {
                throw new \Flaphl\Element\Injection\Exception\ContainerException(sprintf(
                    'Invalid import configuration in "%s".',
                    $currentFile
                ));
            }

            // Resolve relative paths
            if (!str_starts_with($importFile, '/') && !preg_match('/^[A-Za-z]:/', $importFile)) {
                $importFile = $currentDir . DIRECTORY_SEPARATOR . $importFile;
            }

            // For testing, just use this loader for all imports
            $this->load($importFile, $importOptions);
        }
    }
}