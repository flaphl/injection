<?php

namespace Flaphl\Element\Injection\Tests\Dumper;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Dumper\XmlDumper;
use PHPUnit\Framework\TestCase;

class XmlDumperTest extends TestCase
{
    private ContainerBuilder $container;
    private XmlDumper $dumper;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->dumper = new XmlDumper();
    }

    public function testDumpBasicContainer(): void
    {
        if (!extension_loaded('dom')) {
            $this->markTestSkipped('DOM extension not available');
        }

        $this->container->setParameter('app.name', 'Test App');
        $this->container->register('test_service', 'stdClass');

        $xml = $this->dumper->dump($this->container);

        $this->assertStringContainsString('<container', $xml);
        $this->assertStringContainsString('<parameters>', $xml);
        $this->assertStringContainsString('<services>', $xml);
        $this->assertStringContainsString('app.name', $xml);
        $this->assertStringContainsString('test_service', $xml);
    }

    public function testDumpWithComplexService(): void
    {
        if (!extension_loaded('dom')) {
            $this->markTestSkipped('DOM extension not available');
        }

        $this->container->register('complex_service', 'stdClass')
            ->addArgument('@other_service')
            ->addMethodCall('setProperty', ['%parameter%'])
            ->addTag('my_tag', ['priority' => 10])
            ->setPublic(false);

        $this->container->register('other_service', 'ArrayObject');
        $this->container->setParameter('parameter', 'test_value');

        $xml = $this->dumper->dump($this->container);

        $this->assertStringContainsString('public="false"', $xml);
        $this->assertStringContainsString('<call method="setProperty"', $xml);
        $this->assertStringContainsString('<tag name="my_tag"', $xml);
        $this->assertStringContainsString('type="service"', $xml);
        $this->assertStringContainsString('type="parameter"', $xml);
    }

    public function testGetFileExtension(): void
    {
        $this->assertEquals('xml', $this->dumper->getFileExtension());
    }

    public function testGetMimeType(): void
    {
        $this->assertEquals('application/xml', $this->dumper->getMimeType());
    }

    public function testIsSupported(): void
    {
        $this->assertTrue($this->dumper->isSupported($this->container));
    }
}