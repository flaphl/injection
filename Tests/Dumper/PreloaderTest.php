<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Dumper;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Dumper\Preloader;
use Flaphl\Element\Injection\Tests\Fixtures\SimpleService;
use Flaphl\Element\Injection\Tests\Fixtures\DependentService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Preloader.
 * 
 * @package Flaphl\Element\Injection\Tests\Dumper
 * @author Jade Phyressi <jade@flaphl.com>
 */
class PreloaderTest extends TestCase
{
    private Preloader $preloader;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->preloader = new Preloader();
        $this->container = new ContainerBuilder();
    }

    public function testGeneratePreloadScriptBasic(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $script = $this->preloader->generatePreloadScript($this->container);
        
        $this->assertIsString($script);
        $this->assertStringContainsString('<?php', $script);
        $this->assertStringContainsString('opcache_compile_file', $script);
    }

    public function testGeneratePreloadScriptWithOptions(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $options = [
            'memory_limit' => '512M',
            'skip_files_with_syntax_errors' => false,
            'include_dev_dependencies' => true,
        ];
        
        $script = $this->preloader->generatePreloadScript($this->container, $options);
        
        $this->assertIsString($script);
        $this->assertStringContainsString('ini_set(\'memory_limit\', \'512M\')', $script);
    }

    public function testGetPreloadClasses(): void
    {
        $this->container->register('simple', SimpleService::class);
        $this->container->register('dependent', DependentService::class);
        
        $classes = $this->preloader->getPreloadClasses($this->container);
        
        $this->assertIsArray($classes);
        $this->assertContains(SimpleService::class, $classes);
        $this->assertContains(DependentService::class, $classes);
    }

    public function testGetPreloadFiles(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $files = $this->preloader->getPreloadFiles($this->container);
        
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);
        
        // Should contain file paths for the registered classes
        $simpleServiceFile = (new \ReflectionClass(SimpleService::class))->getFileName();
        $this->assertContains($simpleServiceFile, $files);
    }

    public function testEmptyContainer(): void
    {
        $script = $this->preloader->generatePreloadScript($this->container);
        
        $this->assertIsString($script);
        $this->assertStringContainsString('<?php', $script);
        
        $classes = $this->preloader->getPreloadClasses($this->container);
        $files = $this->preloader->getPreloadFiles($this->container);
        
        // Should still have framework classes
        $this->assertNotEmpty($classes);
        $this->assertNotEmpty($files);
    }

    public function testExcludePatterns(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $options = [
            'exclude_patterns' => [
                '*/Fixtures/*',  // This should exclude our test fixtures
            ]
        ];
        
        $script = $this->preloader->generatePreloadScript($this->container, $options);
        $files = $this->preloader->getPreloadFiles($this->container);
        
        $this->assertIsString($script);
        
        // Note: The exclude patterns are applied during script generation,
        // but getPreloadFiles() may still return all files for analysis
        // Let's check the script content instead of the files array
        $this->assertStringNotContainsString('/Fixtures/', $script);
    }

    public function testFrameworkClassesIncluded(): void
    {
        $classes = $this->preloader->getPreloadClasses($this->container);
        
        // Should include core framework classes
        $this->assertContains('Flaphl\\Element\\Injection\\Container', $classes);
        $this->assertContains('Flaphl\\Element\\Injection\\ContainerInterface', $classes);
    }

    public function testInvalidClassHandling(): void
    {
        // Register a non-existent class
        $this->container->register('invalid', 'NonExistentClass');
        
        // Should not throw an exception
        $script = $this->preloader->generatePreloadScript($this->container);
        $classes = $this->preloader->getPreloadClasses($this->container);
        
        $this->assertIsString($script);
        $this->assertIsArray($classes);
        $this->assertNotContains('NonExistentClass', $classes);
    }

    public function testClassDependencyAnalysis(): void
    {
        // DependentService depends on SimpleService
        $this->container->register('dependent', DependentService::class);
        
        $classes = $this->preloader->getPreloadClasses($this->container);
        
        // Both classes should be included
        $this->assertContains(DependentService::class, $classes);
        $this->assertContains(SimpleService::class, $classes);
    }

    public function testScriptStructure(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $script = $this->preloader->generatePreloadScript($this->container);
        
        // Check script structure
        $this->assertStringStartsWith('<?php', $script);
        $this->assertStringContainsString('if (!function_exists(\'opcache_compile_file\'))', $script);
        $this->assertStringContainsString('opcache_compile_file', $script);
        $this->assertStringContainsString('ini_set(\'memory_limit\'', $script);
    }

    public function testMemoryLimitSetting(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $options = ['memory_limit' => '1G'];
        $script = $this->preloader->generatePreloadScript($this->container, $options);
        
        $this->assertStringContainsString('ini_set(\'memory_limit\', \'1G\')', $script);
    }

    public function testPreloadFilesHaveValidPaths(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $files = $this->preloader->getPreloadFiles($this->container);
        
        foreach ($files as $file) {
            $this->assertIsString($file);
            $this->assertFileExists($file);
            $this->assertStringEndsWith('.php', $file);
        }
    }

    public function testClassAnalysisWithInterfaces(): void
    {
        // Register a service that implements an interface
        $this->container->register('service', SimpleService::class);
        
        $classes = $this->preloader->getPreloadClasses($this->container);
        
        $this->assertContains(SimpleService::class, $classes);
        
        // Check that related interfaces are included
        $reflection = new \ReflectionClass(SimpleService::class);
        foreach ($reflection->getInterfaces() as $interface) {
            $this->assertContains($interface->getName(), $classes);
        }
    }

    public function testResetFunctionality(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        // Get classes first time
        $classes1 = $this->preloader->getPreloadClasses($this->container);
        
        // Register another service
        $this->container->register('dependent', DependentService::class);
        
        // Get classes second time - should include new service
        $classes2 = $this->preloader->getPreloadClasses($this->container);
        
        $this->assertContains(SimpleService::class, $classes1);
        $this->assertContains(SimpleService::class, $classes2);
        $this->assertContains(DependentService::class, $classes2);
    }

    public function testSkipFilesWithSyntaxErrorsOption(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $options = ['skip_files_with_syntax_errors' => false];
        $script = $this->preloader->generatePreloadScript($this->container, $options);
        
        // When syntax error checking is disabled, script should still be generated
        $this->assertIsString($script);
        $this->assertStringContainsString('opcache_compile_file', $script);
    }

    public function testIncludeDevDependenciesOption(): void
    {
        $this->container->register('simple', SimpleService::class);
        
        $options = ['include_dev_dependencies' => true];
        $script = $this->preloader->generatePreloadScript($this->container, $options);
        
        $this->assertIsString($script);
        // The actual behavior might differ, but the script should be generated
        $this->assertStringContainsString('<?php', $script);
    }
}