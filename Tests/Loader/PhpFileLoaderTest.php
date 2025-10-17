<?php

namespace Flaphl\Element\Injection\Tests\Loader;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Loader\PhpFileLoader;
use Flaphl\Element\Injection\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

class PhpFileLoaderTest extends TestCase
{
    private ContainerBuilder $container;
    private PhpFileLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new PhpFileLoader($this->container);
        $this->tempDir = sys_get_temp_dir() . '/flaphl_loader_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testLoadBasicConfiguration(): void
    {
        $config = [
            'parameters' => [
                'app.name' => 'Test App',
                'debug' => true,
            ],
            'services' => [
                'test_service' => 'stdClass',
                'complex_service' => [
                    'class' => 'ArrayObject',
                    'arguments' => ['@test_service'],
                    'public' => false,
                ],
            ],
        ];

        $configFile = $this->tempDir . '/config.php';
        file_put_contents($configFile, '<?php return ' . var_export($config, true) . ';');

        $this->loader->load($configFile);

        // Check parameters
        $this->assertEquals('Test App', $this->container->getParameter('app.name'));
        $this->assertTrue($this->container->getParameter('debug'));

        // Check services
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('test_service', $definitions);
        $this->assertArrayHasKey('complex_service', $definitions);
        $this->assertEquals('stdClass', $definitions['test_service']->getClass());
        $this->assertEquals('ArrayObject', $definitions['complex_service']->getClass());
        $this->assertFalse($definitions['complex_service']->isPublic());
    }

    public function testLoadWithImports(): void
    {
        // Create imported file
        $importedConfig = [
            'parameters' => ['imported.param' => 'imported_value'],
            'services' => ['imported_service' => 'stdClass'],
        ];
        $importedFile = $this->tempDir . '/imported.php';
        file_put_contents($importedFile, '<?php return ' . var_export($importedConfig, true) . ';');

        // Create main config file
        $mainConfig = [
            'imports' => ['imported.php'],
            'parameters' => ['main.param' => 'main_value'],
        ];
        $mainFile = $this->tempDir . '/main.php';
        file_put_contents($mainFile, '<?php return ' . var_export($mainConfig, true) . ';');

        $this->loader->load($mainFile);

        $this->assertEquals('imported_value', $this->container->getParameter('imported.param'));
        $this->assertEquals('main_value', $this->container->getParameter('main.param'));
        
        $definitions = $this->container->getDefinitions();
        $this->assertArrayHasKey('imported_service', $definitions);
    }

    public function testGetSupportedExtensions(): void
    {
        $this->assertEquals(['php'], $this->loader->getSupportedExtensions());
    }

    public function testSupports(): void
    {
        $this->assertTrue($this->loader->supports('/path/to/config.php'));
        $this->assertFalse($this->loader->supports('/path/to/config.xml'));
        $this->assertFalse($this->loader->supports('/path/to/config.yml'));
    }

    public function testLoadNonExistentFile(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Configuration file "/non/existent/file.php" not found');

        $this->loader->load('/non/existent/file.php');
    }

    public function testLoadInvalidPhpFile(): void
    {
        $invalidFile = $this->tempDir . '/invalid.php';
        file_put_contents($invalidFile, '<?php return "not an array";');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('must return an array');

        $this->loader->load($invalidFile);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}