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

use Flaphl\Element\Injection\Container;
use Flaphl\Element\Injection\ContextualBindingBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ContextualBindingBuilder.
 * 
 * @package Flaphl\Element\Injection\Tests
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContextualBindingBuilderTest extends TestCase
{
    private Container $container;
    private ContextualBindingBuilder $builder;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->builder = new ContextualBindingBuilder($this->container, 'TestClass');
    }

    public function testConstructor(): void
    {
        $builder = new ContextualBindingBuilder($this->container, 'TestClass');
        
        $this->assertInstanceOf(ContextualBindingBuilder::class, $builder);
    }

    public function testNeedsReturnsBuilderInstance(): void
    {
        $result = $this->builder->needs('SomeInterface');
        
        $this->assertSame($this->builder, $result);
        $this->assertInstanceOf(ContextualBindingBuilder::class, $result);
    }

    public function testNeedsWithDifferentTypes(): void
    {
        // Test with interface
        $result1 = $this->builder->needs('SomeInterface');
        $this->assertSame($this->builder, $result1);

        // Test with class
        $result2 = $this->builder->needs('SomeClass');
        $this->assertSame($this->builder, $result2);

        // Test with abstract class
        $result3 = $this->builder->needs('AbstractClass');
        $this->assertSame($this->builder, $result3);
    }

    public function testGiveWithoutNeedsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contextual binding needs must be defined before giving implementation.');
        
        $this->builder->give('SomeImplementation');
    }

    public function testGiveTaggedWithoutNeedsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contextual binding needs must be defined before giving implementation.');
        
        $this->builder->giveTagged('SomeImplementation');
    }

    public function testGiveSingletonWithoutNeedsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contextual binding needs must be defined before giving implementation.');
        
        $this->builder->giveSingleton('SomeImplementation');
    }

    public function testGiveWithNeedsReturnsContainer(): void
    {
        $result = $this->builder
            ->needs('SomeInterface')
            ->give('SomeImplementation');
        
        $this->assertSame($this->container, $result);
    }

    public function testGiveTaggedWithNeedsReturnsContainer(): void
    {
        $result = $this->builder
            ->needs('SomeInterface')
            ->giveTagged('SomeImplementation');
        
        $this->assertSame($this->container, $result);
    }

    public function testGiveSingletonWithNeedsReturnsContainer(): void
    {
        $result = $this->builder
            ->needs('SomeInterface')
            ->giveSingleton('SomeImplementation');
        
        $this->assertSame($this->container, $result);
    }

    public function testContextualBindingKeyGeneration(): void
    {
        // We can't directly test the binding key generation since it's internal,
        // but we can test that the binding is created by checking if it can be resolved
        $this->builder
            ->needs('SomeInterface')
            ->give('SomeImplementation');

        // The binding should be stored with key format: 'TestClass::SomeInterface'
        $this->assertTrue($this->container->has('TestClass::SomeInterface'));
    }

    public function testFluentInterfaceChaining(): void
    {
        $result = $this->builder
            ->needs('SomeInterface')
            ->give('SomeImplementation');
        
        $this->assertSame($this->container, $result);
        $this->assertTrue($this->container->has('TestClass::SomeInterface'));
    }

    public function testMultipleContextualBindings(): void
    {
        // First binding
        $this->builder
            ->needs('FirstInterface')
            ->give('FirstImplementation');

        // Second binding on same builder (after needs reset)
        $builder2 = new ContextualBindingBuilder($this->container, 'TestClass');
        $builder2
            ->needs('SecondInterface')
            ->give('SecondImplementation');

        $this->assertTrue($this->container->has('TestClass::FirstInterface'));
        $this->assertTrue($this->container->has('TestClass::SecondInterface'));
    }

    public function testGiveWithClosure(): void
    {
        $closure = function() {
            return 'implementation';
        };

        $result = $this->builder
            ->needs('SomeInterface')
            ->give($closure);
        
        $this->assertSame($this->container, $result);
        $this->assertTrue($this->container->has('TestClass::SomeInterface'));
    }

    public function testGiveWithArray(): void
    {
        $config = ['class' => 'SomeClass', 'args' => ['param1', 'param2']];

        $result = $this->builder
            ->needs('SomeInterface')
            ->give($config);
        
        $this->assertSame($this->container, $result);
        $this->assertTrue($this->container->has('TestClass::SomeInterface'));
    }

    public function testSingletonContextualBinding(): void
    {
        $result = $this->builder
            ->needs('SomeInterface')
            ->giveSingleton('SomeImplementation');
        
        $this->assertSame($this->container, $result);
        $this->assertTrue($this->container->has('TestClass::SomeInterface'));
    }

    public function testDifferentConcreteClasses(): void
    {
        $builder1 = new ContextualBindingBuilder($this->container, 'FirstClass');
        $builder2 = new ContextualBindingBuilder($this->container, 'SecondClass');

        $builder1->needs('SameInterface')->give('FirstImplementation');
        $builder2->needs('SameInterface')->give('SecondImplementation');

        $this->assertTrue($this->container->has('FirstClass::SameInterface'));
        $this->assertTrue($this->container->has('SecondClass::SameInterface'));
    }

    public function testNeedsOverride(): void
    {
        // Test that calling needs() multiple times uses the latest value
        $result = $this->builder
            ->needs('FirstInterface')
            ->needs('SecondInterface')  // This should override the first
            ->give('SomeImplementation');
        
        $this->assertSame($this->container, $result);
        $this->assertTrue($this->container->has('TestClass::SecondInterface'));
        $this->assertFalse($this->container->has('TestClass::FirstInterface'));
    }

    public function testGiveCallsGiveTagged(): void
    {
        // Test that give() is an alias for giveTagged()
        $result1 = $this->builder->needs('Interface1')->give('Implementation1');
        
        $builder2 = new ContextualBindingBuilder($this->container, 'TestClass2');
        $result2 = $builder2->needs('Interface1')->giveTagged('Implementation1');
        
        // Both should return the container
        $this->assertSame($this->container, $result1);
        $this->assertSame($this->container, $result2);
    }
}