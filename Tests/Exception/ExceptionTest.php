<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Exception;

use Flaphl\Element\Injection\Exception\{
    ExceptionInterface,
    ContainerException,
    NotFoundException,
    CircularReferenceException,
    ParameterException,
    ServiceDefinitionException
};
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Comprehensive tests for all exception classes.
 *
 * @package Flaphl\Element\Injection\Tests\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ExceptionTest extends TestCase
{
    // ==================== Base ExceptionInterface ====================

    public function testExceptionInterfaceExtendsThrowable(): void
    {
        $this->assertTrue(is_a(ExceptionInterface::class, \Throwable::class, true));
    }

    // ==================== ContainerException ====================

    public function testContainerExceptionImplementsInterfaces(): void
    {
        $exception = new ContainerException('Test message');
        
        $this->assertInstanceOf(ContainerExceptionInterface::class, $exception);
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testContainerExceptionMessage(): void
    {
        $exception = new ContainerException('Container error occurred');
        
        $this->assertEquals('Container error occurred', $exception->getMessage());
    }

    public function testContainerExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ContainerException('Test message', 123, $previous);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    // ==================== NotFoundException ====================

    public function testNotFoundExceptionImplementsInterfaces(): void
    {
        $exception = new NotFoundException('Service not found');
        
        $this->assertInstanceOf(NotFoundExceptionInterface::class, $exception);
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testNotFoundExceptionMessage(): void
    {
        $exception = new NotFoundException('Service [test.service] not found');
        
        $this->assertEquals('Service [test.service] not found', $exception->getMessage());
    }

    // ==================== CircularReferenceException ====================

    public function testCircularReferenceExceptionInheritance(): void
    {
        $path = ['ServiceA', 'ServiceB', 'ServiceA'];
        $exception = new CircularReferenceException($path);
        
        $this->assertInstanceOf(ContainerException::class, $exception);
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testCircularReferenceExceptionPath(): void
    {
        $path = ['ServiceA', 'ServiceB', 'ServiceC', 'ServiceA'];
        $exception = new CircularReferenceException($path);
        
        $this->assertEquals($path, $exception->getPath());
    }

    public function testCircularReferenceExceptionDefaultMessage(): void
    {
        $path = ['ServiceA', 'ServiceB', 'ServiceA'];
        $exception = new CircularReferenceException($path);
        
        $expectedMessage = 'Circular reference detected for service dependency: ServiceA -> ServiceB -> ServiceA';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testCircularReferenceExceptionCustomMessage(): void
    {
        $path = ['ServiceA', 'ServiceB'];
        $customMessage = 'Custom circular reference message';
        $exception = new CircularReferenceException($path, $customMessage);
        
        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertEquals($path, $exception->getPath());
    }

    public function testCircularReferenceExceptionWithCodeAndPrevious(): void
    {
        $path = ['ServiceA', 'ServiceB'];
        $previous = new \Exception('Previous exception');
        $exception = new CircularReferenceException($path, 'Custom message', 456, $previous);
        
        $this->assertEquals('Custom message', $exception->getMessage());
        $this->assertEquals(456, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals($path, $exception->getPath());
    }

    // ==================== ParameterException ====================

    public function testParameterExceptionImplementsInterface(): void
    {
        $exception = new ParameterException('test.param');
        
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testParameterExceptionParameterName(): void
    {
        $exception = new ParameterException('test.parameter');
        
        $this->assertEquals('test.parameter', $exception->getParameterName());
    }

    public function testParameterExceptionParameterValue(): void
    {
        $exception = new ParameterException('test.param');
        
        $this->assertNull($exception->getParameterValue());
    }

    public function testParameterExceptionDefaultMessage(): void
    {
        $exception = new ParameterException('test.param');
        
        $expectedMessage = 'Parameter validation failed for parameter [test.param]';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testParameterExceptionCustomMessage(): void
    {
        $customMessage = 'Custom parameter error';
        $exception = new ParameterException('test.param', $customMessage);
        
        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertEquals('test.param', $exception->getParameterName());
    }

    public function testParameterExceptionWithValue(): void
    {
        $value = 'invalid-value';
        $exception = ParameterException::withValue('test.param', $value, 'Custom message');
        
        $this->assertEquals('test.param', $exception->getParameterName());
        $this->assertEquals($value, $exception->getParameterValue());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testParameterExceptionWithValueDefaultMessage(): void
    {
        $exception = ParameterException::withValue('test.param', 'value');
        
        $expectedMessage = 'Parameter validation failed for parameter [test.param]';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testParameterExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ParameterException('test.param', 'Custom message', 789, $previous);
        
        $this->assertEquals('Custom message', $exception->getMessage());
        $this->assertEquals(789, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals('test.param', $exception->getParameterName());
    }

    // ==================== ServiceDefinitionException ====================

    public function testServiceDefinitionExceptionInheritance(): void
    {
        $exception = new ServiceDefinitionException('test.service');
        
        $this->assertInstanceOf(ContainerException::class, $exception);
        $this->assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testServiceDefinitionExceptionServiceId(): void
    {
        $exception = new ServiceDefinitionException('my.service');
        
        $this->assertEquals('my.service', $exception->getServiceId());
    }

    public function testServiceDefinitionExceptionDefaultMessage(): void
    {
        $exception = new ServiceDefinitionException('test.service');
        
        $expectedMessage = 'Service definition error for service [test.service]';
        $this->assertEquals($expectedMessage, $exception->getMessage());
    }

    public function testServiceDefinitionExceptionCustomMessage(): void
    {
        $customMessage = 'Custom service definition error';
        $exception = new ServiceDefinitionException('test.service', $customMessage);
        
        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertEquals('test.service', $exception->getServiceId());
    }

    public function testServiceDefinitionExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ServiceDefinitionException('test.service', 'Custom message', 999, $previous);
        
        $this->assertEquals('Custom message', $exception->getMessage());
        $this->assertEquals(999, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertEquals('test.service', $exception->getServiceId());
    }

    // ==================== Exception Hierarchy Tests ====================

    public function testAllExceptionsImplementBaseInterface(): void
    {
        $exceptions = [
            new ContainerException('test'),
            new NotFoundException('test'),
            new CircularReferenceException(['A', 'B']),
            new ParameterException('test'),
            new ServiceDefinitionException('test')
        ];

        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(ExceptionInterface::class, $exception);
            $this->assertInstanceOf(\Throwable::class, $exception);
        }
    }

    public function testPsrComplianceForContainerExceptions(): void
    {
        $containerException = new ContainerException('test');
        $notFoundException = new NotFoundException('test');
        $circularException = new CircularReferenceException(['A', 'B']);
        $serviceDefException = new ServiceDefinitionException('test');

        $this->assertInstanceOf(ContainerExceptionInterface::class, $containerException);
        $this->assertInstanceOf(ContainerExceptionInterface::class, $circularException);
        $this->assertInstanceOf(ContainerExceptionInterface::class, $serviceDefException);
        
        $this->assertInstanceOf(NotFoundExceptionInterface::class, $notFoundException);
    }

    public function testExceptionMessageFormatting(): void
    {
        // Test that exception messages follow consistent formatting
        $exceptions = [
            new ContainerException('Container error'),
            new NotFoundException('Service [test] not found'),
            new CircularReferenceException(['A', 'B', 'A']),
            new ParameterException('test.param'),
            new ServiceDefinitionException('test.service')
        ];

        foreach ($exceptions as $exception) {
            $this->assertNotEmpty($exception->getMessage());
            $this->assertIsString($exception->getMessage());
        }
    }
}