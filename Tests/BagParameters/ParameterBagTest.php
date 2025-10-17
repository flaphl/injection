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

use Flaphl\Element\Injection\BagParameters\ParameterBag;
use Flaphl\Element\Injection\BagParameters\ParameterBagInterface;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/**
 * Comprehensive tests for the ParameterBag class.
 *
 * @package Flaphl\Element\Injection\Tests\BagParameters
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ParameterBagTest extends TestCase
{
    private ParameterBag $bag;

    protected function setUp(): void
    {
        $this->bag = new ParameterBag();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(ParameterBagInterface::class, $this->bag);
    }

    public function testCreationWithInitialParameters(): void
    {
        $initial = ['param1' => 'value1', 'param2' => 'value2'];
        $bag = new ParameterBag($initial);
        
        $this->assertEquals($initial, $bag->all());
    }

    public function testSetAndGet(): void
    {
        $this->bag->set('test.param', 'test-value');
        
        $this->assertTrue($this->bag->has('test.param'));
        $this->assertEquals('test-value', $this->bag->get('test.param'));
    }

    public function testGetNonExistentParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter [non-existent] not found');
        
        $this->bag->get('non-existent');
    }

    public function testGetWithDefault(): void
    {
        $this->bag->set('existing', 'value');
        
        $this->assertEquals('value', $this->bag->getWithDefault('existing', 'default'));
        $this->assertEquals('default', $this->bag->getWithDefault('non-existent', 'default'));
    }

    public function testHas(): void
    {
        $this->assertFalse($this->bag->has('test'));
        
        $this->bag->set('test', 'value');
        $this->assertTrue($this->bag->has('test'));
        
        $this->bag->set('null-value', null);
        $this->assertTrue($this->bag->has('null-value'));
    }

    public function testRemove(): void
    {
        $this->bag->set('test', 'value');
        $this->assertTrue($this->bag->has('test'));
        
        $this->bag->remove('test');
        $this->assertFalse($this->bag->has('test'));
    }

    public function testAll(): void
    {
        $this->bag->set('param1', 'value1');
        $this->bag->set('param2', 'value2');
        
        $expected = ['param1' => 'value1', 'param2' => 'value2'];
        $this->assertEquals($expected, $this->bag->all());
    }

    public function testKeys(): void
    {
        $this->bag->set('param1', 'value1');
        $this->bag->set('param2', 'value2');
        
        $keys = $this->bag->keys();
        $this->assertContains('param1', $keys);
        $this->assertContains('param2', $keys);
        $this->assertCount(2, $keys);
    }

    public function testClear(): void
    {
        $this->bag->set('param1', 'value1');
        $this->bag->set('param2', 'value2');
        
        $this->assertCount(2, $this->bag->all());
        
        $this->bag->clear();
        $this->assertEmpty($this->bag->all());
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->bag->count());
        
        $this->bag->set('param1', 'value1');
        $this->assertEquals(1, $this->bag->count());
        
        $this->bag->set('param2', 'value2');
        $this->assertEquals(2, $this->bag->count());
        
        $this->bag->remove('param1');
        $this->assertEquals(1, $this->bag->count());
    }

    public function testResolve(): void
    {
        $this->bag->set('string-param', '123');
        $this->bag->set('int-param', '456');
        $this->bag->set('float-param', '123.45');
        $this->bag->set('bool-param', 'true');
        $this->bag->set('array-param', 'one,two,three');
        
        $this->assertSame('123', $this->bag->resolve('string-param', 'string'));
        $this->assertSame(456, $this->bag->resolve('int-param', 'int'));
        $this->assertSame(123.45, $this->bag->resolve('float-param', 'float'));
        $this->assertTrue($this->bag->resolve('bool-param', 'bool'));
        $this->assertEquals(['one', 'two', 'three'], $this->bag->resolve('array-param', 'array'));
    }

    public function testResolveWithDefault(): void
    {
        $this->bag->set('existing', '123');
        
        $this->assertSame(123, $this->bag->resolveWithDefault('existing', 'int', 456));
        $this->assertSame(789, $this->bag->resolveWithDefault('non-existent', 'int', 789));
    }

    public function testResolveInvalidType(): void
    {
        $this->bag->set('test', 'value');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported type [invalid] for parameter [test]');
        
        $this->bag->resolve('test', 'invalid');
    }

    public function testSetMultiple(): void
    {
        $this->bag->set('existing', 'old-value');
        
        $new_params = ['param1' => 'value1', 'param2' => 'value2', 'existing' => 'new-value'];
        $this->bag->setMultiple($new_params);
        
        $this->assertEquals('value1', $this->bag->get('param1'));
        $this->assertEquals('value2', $this->bag->get('param2'));
        $this->assertEquals('new-value', $this->bag->get('existing'));
    }

    public function testSetMultipleWithoutReplace(): void
    {
        $this->bag->set('existing', 'old-value');
        
        $new_params = ['param1' => 'value1', 'existing' => 'new-value'];
        $this->bag->setMultiple($new_params, false);
        
        $this->assertEquals('value1', $this->bag->get('param1'));
        $this->assertEquals('old-value', $this->bag->get('existing')); // Should not be replaced
    }

    public function testGetMatching(): void
    {
        $this->bag->set('app.name', 'MyApp');
        $this->bag->set('app.version', '1.0.0');
        $this->bag->set('db.host', 'localhost');
        $this->bag->set('db.port', 3306);
        
        $app_params = $this->bag->getMatching('/^app\./');
        $expected = ['app.name' => 'MyApp', 'app.version' => '1.0.0'];
        
        $this->assertEquals($expected, $app_params);
    }

    public function testValidate(): void
    {
        $this->bag->set('name', 'John Doe');
        $this->bag->set('age', 25);
        $this->bag->set('email', 'john@example.com');
        
        $schema = [
            'name' => ['required' => true, 'type' => 'string'],
            'age' => ['required' => true, 'type' => 'int', 'min' => 18, 'max' => 100],
            'email' => ['required' => true, 'regex' => '/^[^@]+@[^@]+\.[^@]+$/']
        ];
        
        $this->assertTrue($this->bag->validate($schema));
    }

    public function testValidateFailure(): void
    {
        $this->bag->set('age', 15); // Below minimum
        
        $schema = [
            'age' => ['required' => true, 'min' => 18]
        ];
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter [age] validation failed');
        
        $this->bag->validate($schema);
    }

    // ==================== Type Conversion Tests ====================

    public function testConvertToInt(): void
    {
        $this->bag->set('numeric-string', '123');
        $this->bag->set('float-string', '123.45');
        
        $this->assertSame(123, $this->bag->resolve('numeric-string', 'int'));
        $this->assertSame(123, $this->bag->resolve('float-string', 'int'));
    }

    public function testConvertToIntFailure(): void
    {
        $this->bag->set('non-numeric', 'not-a-number');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert parameter [non-numeric] to integer');
        
        $this->bag->resolve('non-numeric', 'int');
    }

    public function testConvertToFloat(): void
    {
        $this->bag->set('numeric-string', '123.45');
        $this->bag->set('int-string', '123');
        
        $this->assertSame(123.45, $this->bag->resolve('numeric-string', 'float'));
        $this->assertSame(123.0, $this->bag->resolve('int-string', 'float'));
    }

    public function testConvertToFloatFailure(): void
    {
        $this->bag->set('non-numeric', 'not-a-number');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert parameter [non-numeric] to float');
        
        $this->bag->resolve('non-numeric', 'float');
    }

    public function testConvertToBool(): void
    {
        $this->bag->set('true-string', 'true');
        $this->bag->set('false-string', 'false');
        $this->bag->set('one-string', '1');
        $this->bag->set('zero-string', '0');
        $this->bag->set('yes-string', 'yes');
        $this->bag->set('no-string', 'no');
        $this->bag->set('on-string', 'on');
        $this->bag->set('off-string', 'off');
        $this->bag->set('empty-string', '');
        $this->bag->set('other-string', 'other');
        
        $this->assertTrue($this->bag->resolve('true-string', 'bool'));
        $this->assertFalse($this->bag->resolve('false-string', 'bool'));
        $this->assertTrue($this->bag->resolve('one-string', 'bool'));
        $this->assertFalse($this->bag->resolve('zero-string', 'bool'));
        $this->assertTrue($this->bag->resolve('yes-string', 'bool'));
        $this->assertFalse($this->bag->resolve('no-string', 'bool'));
        $this->assertTrue($this->bag->resolve('on-string', 'bool'));
        $this->assertFalse($this->bag->resolve('off-string', 'bool'));
        $this->assertFalse($this->bag->resolve('empty-string', 'bool'));
        $this->assertTrue($this->bag->resolve('other-string', 'bool'));
    }

    public function testConvertToArray(): void
    {
        $this->bag->set('array-value', [1, 2, 3]);
        $this->bag->set('string-value', 'one,two,three');
        $this->bag->set('json-value', '["a","b","c"]');
        $this->bag->set('object-value', (object)['key' => 'value']);
        
        $this->assertEquals([1, 2, 3], $this->bag->resolve('array-value', 'array'));
        $this->assertEquals(['one', 'two', 'three'], $this->bag->resolve('string-value', 'array'));
        $this->assertEquals(['a', 'b', 'c'], $this->bag->resolve('json-value', 'array'));
        $this->assertEquals(['key' => 'value'], $this->bag->resolve('object-value', 'array'));
    }

    public function testConvertToArrayFailure(): void
    {
        $this->bag->set('invalid', null);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert parameter [invalid] to array');
        
        $this->bag->resolve('invalid', 'array');
    }

    public function testConvertToObject(): void
    {
        $this->bag->set('object-value', (object)['key' => 'value']);
        $this->bag->set('array-value', ['key' => 'value']);
        $this->bag->set('json-value', '{"key":"value"}');
        
        $result1 = $this->bag->resolve('object-value', 'object');
        $this->assertIsObject($result1);
        $this->assertEquals('value', $result1->key);
        
        $result2 = $this->bag->resolve('array-value', 'object');
        $this->assertIsObject($result2);
        $this->assertEquals('value', $result2->key);
        
        $result3 = $this->bag->resolve('json-value', 'object');
        $this->assertIsObject($result3);
        $this->assertEquals('value', $result3->key);
    }

    public function testConvertToObjectFailure(): void
    {
        $this->bag->set('invalid', 123);
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot convert parameter [invalid] to object');
        
        $this->bag->resolve('invalid', 'object');
    }

    public function testFluentInterface(): void
    {
        $result = $this->bag
            ->set('param1', 'value1')
            ->set('param2', 'value2')
            ->remove('param1')
            ->clear();
        
        $this->assertSame($this->bag, $result);
        $this->assertEmpty($this->bag->all());
    }
}