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
use Flaphl\Element\Injection\Loader\Configurator\ParameterConfigurator;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for ParameterConfigurator.
 * 
 * @package Flaphl\Element\Injection\Tests\Loader\Configurator
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ParameterConfiguratorTest extends TestCase
{
    private ContainerBuilder $container;
    private ParameterConfigurator $configurator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->configurator = new ParameterConfigurator($this->container);
        $this->tempDir = sys_get_temp_dir() . '/flaphl_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
        
        // Clean up environment variables
        unset($_ENV['TEST_VAR'], $_ENV['TEST_PREFIX_VAR1'], $_ENV['TEST_PREFIX_VAR2']);
        putenv('TEST_VAR');
        putenv('TEST_PREFIX_VAR1');
        putenv('TEST_PREFIX_VAR2');
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

    public function testSetParameter(): void
    {
        $result = $this->configurator->set('test_param', 'test_value');
        
        $this->assertSame($this->configurator, $result);
        $this->assertEquals(['test_param' => 'test_value'], $this->configurator->getParameters());
    }

    public function testStringParameter(): void
    {
        $this->configurator->string('string_param', 'test_string');
        
        $this->assertEquals(['string_param' => 'test_string'], $this->configurator->getParameters());
    }

    public function testIntParameter(): void
    {
        $this->configurator->int('int_param', 42);
        
        $this->assertEquals(['int_param' => 42], $this->configurator->getParameters());
    }

    public function testFloatParameter(): void
    {
        $this->configurator->float('float_param', 3.14);
        
        $this->assertEquals(['float_param' => 3.14], $this->configurator->getParameters());
    }

    public function testBoolParameter(): void
    {
        $this->configurator->bool('bool_param', true);
        
        $this->assertEquals(['bool_param' => true], $this->configurator->getParameters());
    }

    public function testArrayParameter(): void
    {
        $array = ['item1', 'item2', 'item3'];
        $this->configurator->array('array_param', $array);
        
        $this->assertEquals(['array_param' => $array], $this->configurator->getParameters());
    }

    public function testEnvParameter(): void
    {
        $_ENV['TEST_VAR'] = 'test_env_value';
        
        $this->configurator->env('env_param', 'TEST_VAR');
        
        $this->assertEquals(['env_param' => 'test_env_value'], $this->configurator->getParameters());
        $metadata = $this->configurator->getMetadata();
        $this->assertEquals('environment', $metadata['env_param']['source']);
        $this->assertEquals('TEST_VAR', $metadata['env_param']['env_var']);
    }

    public function testEnvParameterWithDefault(): void
    {
        // Variable doesn't exist
        $this->configurator->env('env_param_default', 'NON_EXISTENT_VAR', 'default_value');
        
        $this->assertEquals(['env_param_default' => 'default_value'], $this->configurator->getParameters());
    }

    public function testEnvParameterWithTypeCasting(): void
    {
        $_ENV['TEST_VAR'] = '42';
        
        $this->configurator->env('env_int', 'TEST_VAR', null, 'int');
        
        $this->assertEquals(['env_int' => 42], $this->configurator->getParameters());
        $this->assertIsInt($this->configurator->getParameters()['env_int']);
    }

    public function testEnvPrefix(): void
    {
        $_ENV['TEST_PREFIX_VAR1'] = 'value1';
        $_ENV['TEST_PREFIX_VAR2'] = 'value2';
        $_ENV['OTHER_VAR'] = 'other';
        
        $this->configurator->envPrefix('TEST_PREFIX_', 'app.');
        
        $parameters = $this->configurator->getParameters();
        $this->assertEquals('value1', $parameters['app.var1']);
        $this->assertEquals('value2', $parameters['app.var2']);
        $this->assertArrayNotHasKey('app.other_var', $parameters);
    }

    public function testEnvFile(): void
    {
        $envContent = <<<ENV
# Database configuration
DB_HOST=localhost
DB_PORT=5432
DB_NAME="myapp"

# Comment line
APP_ENV='production'
ENV;
        
        $envFile = $this->createTempFile('.env', $envContent);
        $this->configurator->envFile($envFile, 'app.');
        
        $parameters = $this->configurator->getParameters();
        $this->assertEquals('localhost', $parameters['app.db_host']);
        $this->assertEquals('5432', $parameters['app.db_port']);
        $this->assertEquals('myapp', $parameters['app.db_name']);
        $this->assertEquals('production', $parameters['app.app_env']);
    }

    public function testEnvFileNonExistent(): void
    {
        $this->configurator->envFile('/non/existent/file.env');
        
        // Should not throw exception or add parameters
        $this->assertEquals([], $this->configurator->getParameters());
    }

    public function testComputed(): void
    {
        $this->configurator->computed('computed_param', function () {
            return 'computed_value_' . date('Y');
        });
        
        $parameters = $this->configurator->getParameters();
        $this->assertStringStartsWith('computed_value_', $parameters['computed_param']);
        
        $metadata = $this->configurator->getMetadata();
        $this->assertEquals('computed', $metadata['computed_param']['source']);
        $this->assertIsCallable($metadata['computed_param']['callback']);
    }

    public function testValidated(): void
    {
        $validator = function ($value) {
            return is_string($value) && strlen($value) > 3;
        };
        
        $this->configurator->validated('validated_param', 'valid_string', $validator);
        
        $this->assertEquals(['validated_param' => 'valid_string'], $this->configurator->getParameters());
        
        $metadata = $this->configurator->getMetadata();
        $this->assertEquals('validated', $metadata['validated_param']['source']);
    }

    public function testValidatedThrowsExceptionOnFailure(): void
    {
        $validator = function ($value) {
            return is_string($value) && strlen($value) > 10;
        };
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "short_param" validation failed');
        
        $this->configurator->validated('short_param', 'short', $validator);
    }

    public function testValidatedWithCustomErrorMessage(): void
    {
        $validator = function ($value) {
            return $value > 0;
        };
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Value must be positive');
        
        $this->configurator->validated('negative_param', -5, $validator, 'Value must be positive');
    }

    public function testBatch(): void
    {
        $parameters = [
            'param1' => 'value1',
            'param2' => 'value2',
            'param3' => 'value3',
        ];
        
        $this->configurator->batch($parameters);
        
        $this->assertEquals($parameters, $this->configurator->getParameters());
    }

    public function testBatchWithPrefix(): void
    {
        $parameters = [
            'param1' => 'value1',
            'param2' => 'value2',
        ];
        
        $this->configurator->batch($parameters, 'app.');
        
        $expected = [
            'app.param1' => 'value1',
            'app.param2' => 'value2',
        ];
        
        $this->assertEquals($expected, $this->configurator->getParameters());
    }

    public function testLoad(): void
    {
        $this->configurator
            ->string('string_param', 'test')
            ->int('int_param', 42)
            ->bool('bool_param', true);
        
        $this->configurator->load();
        
        $this->assertTrue($this->container->getParameterBag()->has('string_param'));
        $this->assertTrue($this->container->getParameterBag()->has('int_param'));
        $this->assertTrue($this->container->getParameterBag()->has('bool_param'));
        
        $this->assertEquals('test', $this->container->getParameter('string_param'));
        $this->assertEquals(42, $this->container->getParameter('int_param'));
        $this->assertTrue($this->container->getParameter('bool_param'));
    }

    public function testExport(): void
    {
        $this->configurator
            ->string('param1', 'value1')
            ->int('param2', 42);
        
        $exported = $this->configurator->export();
        
        $expected = [
            'parameters' => [
                'param1' => 'value1',
                'param2' => 42,
            ]
        ];
        
        $this->assertEquals($expected, $exported);
    }

    public function testCastType(): void
    {
        // Test through env method with type casting
        $_ENV['TEST_STRING'] = '123';
        $_ENV['TEST_BOOL'] = 'true';
        $_ENV['TEST_FLOAT'] = '3.14';
        
        $this->configurator
            ->env('string_val', 'TEST_STRING', null, 'string')
            ->env('int_val', 'TEST_STRING', null, 'int')
            ->env('bool_val', 'TEST_BOOL', null, 'bool')
            ->env('float_val', 'TEST_FLOAT', null, 'float');
        
        $parameters = $this->configurator->getParameters();
        
        $this->assertIsString($parameters['string_val']);
        $this->assertEquals('123', $parameters['string_val']);
        
        $this->assertIsInt($parameters['int_val']);
        $this->assertEquals(123, $parameters['int_val']);
        
        $this->assertIsBool($parameters['bool_val']);
        $this->assertTrue($parameters['bool_val']);
        
        $this->assertIsFloat($parameters['float_val']);
        $this->assertEquals(3.14, $parameters['float_val']);
    }

    public function testFluentInterface(): void
    {
        $result = $this->configurator
            ->string('param1', 'value1')
            ->int('param2', 42)
            ->bool('param3', true)
            ->array('param4', ['item1', 'item2']);
        
        $this->assertSame($this->configurator, $result);
        
        $expected = [
            'param1' => 'value1',
            'param2' => 42,
            'param3' => true,
            'param4' => ['item1', 'item2'],
        ];
        
        $this->assertEquals($expected, $this->configurator->getParameters());
    }

    public function testComplexWorkflow(): void
    {
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['APP_PORT'] = '8080';
        
        $envFile = $this->createTempFile('.env', 'SECRET_KEY=mysecret');
        
        $this->configurator
            ->string('app.name', 'MyApp')
            ->env('app.debug', 'APP_DEBUG', false, 'bool')
            ->env('app.port', 'APP_PORT', 3000, 'int')
            ->envFile($envFile, 'app.')
            ->computed('app.version', fn() => '1.0.0')
            ->validated('app.timeout', 30, fn($v) => $v > 0, 'Timeout must be positive')
            ->batch(['feature.x' => true, 'feature.y' => false], 'app.');
        
        $this->configurator->load();
        
        // Verify all parameters are loaded
        $this->assertEquals('MyApp', $this->container->getParameter('app.name'));
        $this->assertTrue($this->container->getParameter('app.debug'));
        $this->assertEquals(8080, $this->container->getParameter('app.port'));
        $this->assertEquals('mysecret', $this->container->getParameter('app.secret_key'));
        $this->assertEquals('1.0.0', $this->container->getParameter('app.version'));
        $this->assertEquals(30, $this->container->getParameter('app.timeout'));
        $this->assertTrue($this->container->getParameter('app.feature.x'));
        $this->assertFalse($this->container->getParameter('app.feature.y'));
    }
}