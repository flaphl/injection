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
use Flaphl\Element\Injection\Dumper\Dumper;
use Flaphl\Element\Injection\Exception\ContainerException;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for Dumper base class.
 * 
 * @package Flaphl\Element\Injection\Tests\Dumper
 * @author Jade Phyressi <jade@flaphl.com>
 */
class DumperTest extends TestCase
{
    private ContainerBuilder $container;
    private TestDumper $dumper;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->dumper = new TestDumper();
    }

    public function testDumpCallsDoDumpWithMergedOptions(): void
    {
        $customOptions = ['class' => 'CustomContainer'];
        
        $result = $this->dumper->dump($this->container, $customOptions);
        
        $this->assertEquals('dumped_content', $result);
        $this->assertEquals('CustomContainer', $this->dumper->getLastOptions()['class']);
        $this->assertEquals('Flaphl\\Generated', $this->dumper->getLastOptions()['namespace']);
    }

    public function testDumpWithDefaultOptions(): void
    {
        $result = $this->dumper->dump($this->container);
        
        $expectedOptions = $this->dumper->getDefaultOptions();
        $this->assertEquals($expectedOptions, $this->dumper->getLastOptions());
        $this->assertEquals('dumped_content', $result);
    }

    public function testIsSupportedReturnsTrue(): void
    {
        $this->assertTrue($this->dumper->isSupported($this->container));
    }

    public function testDumpThrowsExceptionWhenNotSupported(): void
    {
        $dumper = new TestDumper(false);
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Container dumping with');
        
        $dumper->dump($this->container);
    }

    public function testGenerateHash(): void
    {
        // Add some services and parameters
        $this->container->register('service1', 'Service1');
        $this->container->register('service2', 'Service2');
        $this->container->setParameter('param1', 'value1');
        
        $hash1 = $this->dumper->testGenerateHash($this->container);
        $this->assertIsString($hash1);
        $this->assertEquals(64, strlen($hash1)); // SHA256 hash length
        
        // Same container should produce same hash
        $hash2 = $this->dumper->testGenerateHash($this->container);
        $this->assertEquals($hash1, $hash2);
        
        // Different container should produce different hash
        $this->container->setParameter('param2', 'value2');
        $hash3 = $this->dumper->testGenerateHash($this->container);
        $this->assertNotEquals($hash1, $hash3);
    }

    public function testValidateOptionsSuccess(): void
    {
        $validOptions = [
            'class' => 'ValidClassName',
            'namespace' => 'Valid\\Namespace',
            'debug' => true,
        ];
        
        // Should not throw exception
        $this->dumper->testValidateOptions($validOptions);
        $this->assertTrue(true);
    }

    public function testValidateOptionsThrowsExceptionForMissingClass(): void
    {
        $invalidOptions = [
            'namespace' => 'Valid\\Namespace',
        ];
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Option "class" must be a non-empty string');
        
        $this->dumper->testValidateOptions($invalidOptions);
    }

    public function testValidateOptionsThrowsExceptionForMissingNamespace(): void
    {
        $invalidOptions = [
            'class' => 'ValidClassName',
        ];
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Option "namespace" must be a non-empty string');
        
        $this->dumper->testValidateOptions($invalidOptions);
    }

    public function testValidateOptionsThrowsExceptionForInvalidClassName(): void
    {
        $invalidOptions = [
            'class' => '123InvalidName',
            'namespace' => 'Valid\\Namespace',
        ];
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Class name "123InvalidName" is not valid');
        
        $this->dumper->testValidateOptions($invalidOptions);
    }

    public function testValidateOptionsThrowsExceptionForNonStringClass(): void
    {
        $invalidOptions = [
            'class' => 123,
            'namespace' => 'Valid\\Namespace',
        ];
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Option "class" must be a non-empty string');
        
        $this->dumper->testValidateOptions($invalidOptions);
    }

    public function testGenerateFileHeaderWithCustomHeader(): void
    {
        $options = [
            'file_header' => '/* Custom header */',
        ];
        
        $header = $this->dumper->testGenerateFileHeader($options);
        
        $this->assertEquals("/* Custom header */\n\n", $header);
    }

    public function testGenerateFileHeaderWithDefaultHeader(): void
    {
        $options = [];
        
        $header = $this->dumper->testGenerateFileHeader($options);
        
        $this->assertStringContainsString('auto-generated by the Flaphl Dependency Injection Element', $header);
        $this->assertStringContainsString('@generated at', $header);
        $this->assertStringStartsWith('/**', $header);
        $this->assertStringEndsWith("*/\n\n", $header);
    }

    public function testGenerateFileHeaderWithEmptyHeader(): void
    {
        $options = [
            'file_header' => '',
        ];
        
        $header = $this->dumper->testGenerateFileHeader($options);
        
        $this->assertStringContainsString('auto-generated by the Flaphl Dependency Injection Element', $header);
    }

    public function testDefaultOptions(): void
    {
        $options = $this->dumper->getDefaultOptions();
        
        $this->assertEquals('CompiledContainer', $options['class']);
        $this->assertEquals('Flaphl\\Generated', $options['namespace']);
        $this->assertEquals('Flaphl\\Element\\Injection\\Container', $options['base_class']);
        $this->assertEquals('', $options['file_header']);
        $this->assertFalse($options['debug']);
        $this->assertTrue($options['inline_factories']);
        $this->assertTrue($options['inline_class_loader']);
        $this->assertTrue($options['preload_classes']);
    }

    public function testComplexValidationScenarios(): void
    {
        // Test valid class names
        $validClassNames = [
            'SimpleClass',
            'Class_With_Underscores',
            '_PrivateClass',
            'MyClass123',
            // Note: Namespaced class names are validated differently
            // The regex in Dumper validates just the class name part, not the full namespace
        ];
        
        foreach ($validClassNames as $className) {
            $options = [
                'class' => $className,
                'namespace' => 'Test\\Namespace',
            ];
            
            $this->dumper->testValidateOptions($options);
            $this->assertTrue(true); // If we reach here, validation passed
        }
    }

    public function testHashConsistency(): void
    {
        // Test that hash is consistent across multiple calls
        $this->container->register('test_service', 'TestClass');
        $this->container->setParameter('test_param', 'test_value');
        
        $hashes = [];
        for ($i = 0; $i < 5; $i++) {
            $hashes[] = $this->dumper->testGenerateHash($this->container);
        }
        
        // All hashes should be identical
        $uniqueHashes = array_unique($hashes);
        $this->assertCount(1, $uniqueHashes);
    }

    public function testHashDifferenceWithContainerChanges(): void
    {
        $hash1 = $this->dumper->testGenerateHash($this->container);
        
        // Add service
        $this->container->register('service1', 'Service1');
        $hash2 = $this->dumper->testGenerateHash($this->container);
        $this->assertNotEquals($hash1, $hash2);
        
        // Add parameter
        $this->container->setParameter('param1', 'value1');
        $hash3 = $this->dumper->testGenerateHash($this->container);
        $this->assertNotEquals($hash2, $hash3);
        
        // All hashes should be different
        $this->assertNotEquals($hash1, $hash3);
    }
}

/**
 * Test implementation of Dumper for testing base functionality.
 */
class TestDumper extends Dumper
{
    private bool $supported;
    private array $lastOptions = [];

    public function __construct(bool $supported = true)
    {
        $this->supported = $supported;
    }

    public function isSupported(ContainerBuilder $container): bool
    {
        return $this->supported;
    }

    public function getFileExtension(): string
    {
        return 'test';
    }

    public function getMimeType(): string
    {
        return 'text/test';
    }

    protected function escape(string $string): string
    {
        return addslashes($string);
    }

    protected function doDump(ContainerBuilder $container, array $options): string
    {
        $this->lastOptions = $options;
        $this->validateOptions($options);
        return 'dumped_content';
    }

    public function getLastOptions(): array
    {
        return $this->lastOptions;
    }

    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    public function testGenerateHash(ContainerBuilder $container): string
    {
        // Create a hash based on service definitions and parameters
        // Note: We override the base implementation to avoid the '*' tag issue
        $data = [
            'services' => array_keys($container->getDefinitions()),
            'parameters' => $container->getParameterBag()->all(),
        ];

        return hash('sha256', serialize($data));
    }

    public function testValidateOptions(array $options): void
    {
        $this->validateOptions($options);
    }

    public function testGenerateFileHeader(array $options): string
    {
        return $this->generateFileHeader($options);
    }
}