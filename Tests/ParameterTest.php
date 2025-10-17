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

use Flaphl\Element\Injection\Parameter;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive tests for the Parameter class.
 *
 * @package Flaphl\Element\Injection\Tests
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ParameterTest extends TestCase
{
    public function testParameterCreation(): void
    {
        $parameter = new Parameter('test.param', 'test-value');
        
        $this->assertEquals('test.param', $parameter->getName());
        $this->assertEquals('test-value', $parameter->getValue());
        $this->assertNull($parameter->getType());
        $this->assertNull($parameter->getDescription());
        $this->assertFalse($parameter->isRequired());
        $this->assertNull($parameter->getDefault());
        $this->assertEmpty($parameter->getRules());
        $this->assertEmpty($parameter->getMetadata());
    }

    public function testParameterWithOptions(): void
    {
        $options = [
            'type' => 'string',
            'description' => 'Test parameter',
            'required' => true,
            'default' => 'default-value',
            'rules' => ['minLength' => 5],
            'metadata' => ['category' => 'test']
        ];
        
        $parameter = new Parameter('test.param', 'test-value', $options);
        
        $this->assertEquals('string', $parameter->getType());
        $this->assertEquals('Test parameter', $parameter->getDescription());
        $this->assertTrue($parameter->isRequired());
        $this->assertEquals('default-value', $parameter->getDefault());
        $this->assertEquals(['minLength' => 5], $parameter->getRules());
        $this->assertEquals(['category' => 'test'], $parameter->getMetadata());
    }

    public function testSettersAndGetters(): void
    {
        $parameter = new Parameter('test', 'value');
        
        $parameter->setValue('new-value');
        $this->assertEquals('new-value', $parameter->getValue());
        
        $parameter->setType('integer');
        $this->assertEquals('integer', $parameter->getType());
        
        $parameter->setDescription('New description');
        $this->assertEquals('New description', $parameter->getDescription());
        
        $parameter->setRequired(true);
        $this->assertTrue($parameter->isRequired());
        
        $parameter->setDefault('new-default');
        $this->assertEquals('new-default', $parameter->getDefault());
        
        $parameter->setRules(['max' => 100]);
        $this->assertEquals(['max' => 100], $parameter->getRules());
        
        $parameter->addRule('min', 10);
        $this->assertEquals(['max' => 100, 'min' => 10], $parameter->getRules());
        
        $parameter->setMetadata(['key' => 'value']);
        $this->assertEquals(['key' => 'value'], $parameter->getMetadata());
        
        $parameter->setMetadataValue('new-key', 'new-value');
        $this->assertEquals(['key' => 'value', 'new-key' => 'new-value'], $parameter->getMetadata());
    }

    public function testMetadataOperations(): void
    {
        $parameter = new Parameter('test', 'value');
        $parameter->setMetadataValue('key1', 'value1');
        $parameter->setMetadataValue('key2', 'value2');
        
        $this->assertEquals('value1', $parameter->getMetadataValue('key1'));
        $this->assertEquals('default', $parameter->getMetadataValueWithDefault('non-existent', 'default'));
    }

    public function testMetadataValueNotFound(): void
    {
        $parameter = new Parameter('test', 'value');
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Metadata key [non-existent] not found');
        
        $parameter->getMetadataValue('non-existent');
    }

    public function testValidation(): void
    {
        $parameter = new Parameter('test', 'test-value');
        $parameter->setRequired(true);
        $parameter->addRule('type', 'string');
        $parameter->addRule('minLength', 5);
        
        $this->assertTrue($parameter->validate());
    }

    public function testValidationFailsForRequired(): void
    {
        $parameter = new Parameter('test', null);
        $parameter->setRequired(true);
        
        $this->assertFalse($parameter->validate());
    }

    public function testValidationRules(): void
    {
        // Test type validation
        $parameter = new Parameter('test', 'string-value');
        $parameter->addRule('type', 'string');
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue(123);
        $this->assertFalse($parameter->validate());
        
        // Test numeric rules
        $parameter = new Parameter('test', 50);
        $parameter->addRule('min', 10);
        $parameter->addRule('max', 100);
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue(5);
        $this->assertFalse($parameter->validate());
        
        // Test string length rules
        $parameter = new Parameter('test', 'hello world');
        $parameter->addRule('minLength', 5);
        $parameter->addRule('maxLength', 20);
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue('hi');
        $this->assertFalse($parameter->validate());
        
        // Test in rule
        $parameter = new Parameter('test', 'blue');
        $parameter->addRule('in', ['red', 'green', 'blue']);
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue('yellow');
        $this->assertFalse($parameter->validate());
        
        // Test regex rule
        $parameter = new Parameter('test', 'test@example.com');
        $parameter->addRule('regex', '/^[^@]+@[^@]+\.[^@]+$/');
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue('invalid-email');
        $this->assertFalse($parameter->validate());
        
        // Test email rule
        $parameter = new Parameter('test', 'valid@example.com');
        $parameter->addRule('email', true);
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue('invalid-email');
        $this->assertFalse($parameter->validate());
        
        // Test URL rule
        $parameter = new Parameter('test', 'https://example.com');
        $parameter->addRule('url', true);
        $this->assertTrue($parameter->validate());
        
        $parameter->setValue('not-a-url');
        $this->assertFalse($parameter->validate());
    }

    public function testGetTypedValue(): void
    {
        // Test string conversion
        $parameter = new Parameter('test', 123);
        $parameter->setType('string');
        $this->assertSame('123', $parameter->getTypedValue());
        
        // Test integer conversion
        $parameter = new Parameter('test', '456');
        $parameter->setType('int');
        $this->assertSame(456, $parameter->getTypedValue());
        
        // Test float conversion
        $parameter = new Parameter('test', '123.45');
        $parameter->setType('float');
        $this->assertSame(123.45, $parameter->getTypedValue());
        
        // Test boolean conversion
        $parameter = new Parameter('test', 1);
        $parameter->setType('bool');
        $this->assertTrue($parameter->getTypedValue());
        
        // Test array conversion
        $parameter = new Parameter('test', 'value');
        $parameter->setType('array');
        $this->assertSame(['value'], $parameter->getTypedValue());
        
        // Test object conversion
        $parameter = new Parameter('test', ['key' => 'value']);
        $parameter->setType('object');
        $result = $parameter->getTypedValue();
        $this->assertIsObject($result);
        $this->assertEquals('value', $result->key);
        
        // Test no type conversion
        $parameter = new Parameter('test', 'raw-value');
        $this->assertEquals('raw-value', $parameter->getTypedValue());
    }

    public function testHasValue(): void
    {
        $parameter = new Parameter('test', 'value');
        $this->assertTrue($parameter->hasValue());
        
        $parameter->setValue(null);
        $this->assertFalse($parameter->hasValue());
        
        $parameter->setValue(0);
        $this->assertTrue($parameter->hasValue());
        
        $parameter->setValue('');
        $this->assertTrue($parameter->hasValue());
    }

    public function testToArray(): void
    {
        $parameter = new Parameter('test.param', 'test-value', [
            'type' => 'string',
            'description' => 'Test parameter',
            'required' => true,
            'default' => 'default-value',
            'rules' => ['minLength' => 5],
            'metadata' => ['category' => 'test']
        ]);
        
        $expected = [
            'name' => 'test.param',
            'value' => 'test-value',
            'type' => 'string',
            'description' => 'Test parameter',
            'required' => true,
            'default' => 'default-value',
            'rules' => ['minLength' => 5],
            'metadata' => ['category' => 'test']
        ];
        
        $this->assertEquals($expected, $parameter->toArray());
    }

    public function testFromArray(): void
    {
        $data = [
            'name' => 'test.param',
            'value' => 'test-value',
            'type' => 'string',
            'description' => 'Test parameter',
            'required' => true,
            'default' => 'default-value',
            'rules' => ['minLength' => 5],
            'metadata' => ['category' => 'test']
        ];
        
        $parameter = Parameter::fromArray($data);
        
        $this->assertEquals('test.param', $parameter->getName());
        $this->assertEquals('test-value', $parameter->getValue());
        $this->assertEquals('string', $parameter->getType());
        $this->assertEquals('Test parameter', $parameter->getDescription());
        $this->assertTrue($parameter->isRequired());
        $this->assertEquals('default-value', $parameter->getDefault());
        $this->assertEquals(['minLength' => 5], $parameter->getRules());
        $this->assertEquals(['category' => 'test'], $parameter->getMetadata());
    }

    public function testFromArrayWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter name is required');
        
        Parameter::fromArray(['value' => 'test']);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $parameter = Parameter::fromArray(['name' => 'test']);
        
        $this->assertEquals('test', $parameter->getName());
        $this->assertNull($parameter->getValue());
    }
}