<?php

namespace Flaphl\Element\Injection\Tests\Dumper;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Dumper\PhpDumper;
use PHPUnit\Framework\TestCase;

class PhpDumperTest extends TestCase
{
    private ContainerBuilder $container;
    private PhpDumper $dumper;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->dumper = new PhpDumper();
    }

    public function testDumpBasicContainer(): void
    {
        $this->container->setParameter('app.name', 'Test App');
        $this->container->register('test_service', 'stdClass');

        $code = $this->dumper->dump($this->container);

        $this->assertStringContainsString('class CompiledContainer', $code);
        $this->assertStringContainsString('app.name', $code);
        $this->assertStringContainsString('test_service', $code);
        $this->assertStringContainsString('stdClass', $code);
    }

    public function testDumpWithCustomOptions(): void
    {
        $this->container->setParameter('debug', true);
        
        $options = [
            'class' => 'CustomContainer',
            'namespace' => 'App\\Generated',
            'debug' => true,
        ];

        $code = $this->dumper->dump($this->container, $options);

        $this->assertStringContainsString('namespace App\\Generated', $code);
        $this->assertStringContainsString('class CustomContainer', $code);
        $this->assertStringContainsString('getDebugInfo', $code);
    }

    public function testDumpWithArguments(): void
    {
        $this->container->register('service_with_args', 'stdClass')
            ->addArgument('@other_service')
            ->addArgument('%parameter%');
        
        $this->container->register('other_service', 'ArrayObject');
        $this->container->setParameter('parameter', 'test_value');

        $code = $this->dumper->dump($this->container);

        $this->assertStringContainsString('$this->get(', $code);
        $this->assertStringContainsString('$this->getParameter(', $code);
    }

    public function testGetFileExtension(): void
    {
        $this->assertEquals('php', $this->dumper->getFileExtension());
    }

    public function testGetMimeType(): void
    {
        $this->assertEquals('text/x-php', $this->dumper->getMimeType());
    }

    public function testIsSupported(): void
    {
        $this->assertTrue($this->dumper->isSupported($this->container));
    }
}