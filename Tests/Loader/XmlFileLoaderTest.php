<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Loader;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Exception\ContainerException;
use Flaphl\Element\Injection\Loader\XmlFileLoader;
use PHPUnit\Framework\TestCase;

/**
 * Test cases for XmlFileLoader.
 * 
 * @package Flaphl\Element\Injection\Tests\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class XmlFileLoaderTest extends TestCase
{
    private ContainerBuilder $container;
    private TestableXmlFileLoader $loader;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->loader = new TestableXmlFileLoader($this->container);
    }

    public function testGetSupportedExtensions(): void
    {
        $this->assertEquals(['xml'], $this->loader->getSupportedExtensions());
    }

    public function testSupportsXmlFiles(): void
    {
        $this->assertTrue($this->loader->supports('config.xml'));
        $this->assertTrue($this->loader->supports('/path/to/config.xml'));
        $this->assertFalse($this->loader->supports('config.yml'));
    }

    public function testThrowsExceptionWhenDomExtensionNotLoaded(): void
    {
        if (!extension_loaded('dom')) {
            $this->expectException(ContainerException::class);
            $this->expectExceptionMessage('The DOM extension is required');
            
            $this->loader->testDoLoad('dummy.xml', []);
        } else {
            $this->markTestSkipped('DOM extension is loaded');
        }
    }

    public function testThrowsExceptionWhenLibxmlExtensionNotLoaded(): void
    {
        if (!extension_loaded('libxml')) {
            $this->expectException(ContainerException::class);
            $this->expectExceptionMessage('The libxml extension is required');
            
            $this->loader->testDoLoad('dummy.xml', []);
        } else {
            $this->markTestSkipped('libxml extension is loaded');
        }
    }

    public function testParseXmlToArrayBasicStructure(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<container>
    <parameters>
        <parameter key="test_param">test_value</parameter>
    </parameters>
    <services>
        <service id="test_service" class="TestClass" />
    </services>
</container>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $config = $this->loader->testParseXmlToArray($dom, 'test.xml');
        
        $this->assertArrayHasKey('parameters', $config);
        $this->assertArrayHasKey('services', $config);
        $this->assertEquals(['test_param' => 'test_value'], $config['parameters']);
        $this->assertArrayHasKey('test_service', $config['services']);
    }

    public function testParseImports(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<container>
    <import resource="config1.xml" />
    <import resource="config2.xml" ignore-errors="true" />
</container>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $imports = $this->loader->testParseImports($dom->documentElement);
        
        $this->assertCount(2, $imports);
        $this->assertEquals('config1.xml', $imports[0]['resource']);
        $this->assertEquals('config2.xml', $imports[1]['resource']);
        $this->assertTrue($imports[1]['ignore_errors']);
    }

    public function testParseParameters(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<container>
    <parameters>
        <parameter key="string_param">test</parameter>
        <parameter key="int_param" type="integer">42</parameter>
        <parameter key="bool_param" type="boolean">true</parameter>
    </parameters>
</container>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $parameters = $this->loader->testParseParameters($dom->documentElement);
        
        $this->assertEquals('test', $parameters['string_param']);
        $this->assertEquals(42, $parameters['int_param']);
        $this->assertTrue($parameters['bool_param']);
    }

    public function testParseServices(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<container>
    <services>
        <service id="simple_service" class="SimpleClass" />
        <service id="complex_service" class="ComplexClass" autowire="true" public="false">
            <argument type="string">test</argument>
            <call method="setTest">
                <argument type="integer">42</argument>
            </call>
            <property name="prop1" type="boolean">true</property>
            <tag name="tag1" priority="10" />
        </service>
    </services>
</container>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $services = $this->loader->testParseServices($dom->documentElement);
        
        $this->assertArrayHasKey('simple_service', $services);
        $this->assertArrayHasKey('complex_service', $services);
        
        $simple = $services['simple_service'];
        $this->assertEquals('SimpleClass', $simple['class']);
        
        $complex = $services['complex_service'];
        $this->assertEquals('ComplexClass', $complex['class']);
        $this->assertTrue($complex['autowire']);
        $this->assertFalse($complex['public']);
        // The complex service has the following structure:
        // 1 argument element, 1 call element, 1 property element, 1 tag element
        $this->assertArrayHasKey('arguments', $complex);
        $this->assertArrayHasKey('calls', $complex);
        $this->assertArrayHasKey('properties', $complex);
        $this->assertArrayHasKey('tags', $complex);
    }

    public function testParseValue(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<container>
    <element type="string">test</element>
    <element type="integer">42</element>
    <element type="float">3.14</element>
    <element type="boolean">true</element>
    <element type="null"></element>
    <element type="service" id="test_service"></element>
    <element type="parameter">test_param</element>
    <element type="collection">
        <item type="string">item1</item>
        <item key="key1" type="string">value1</item>
    </element>
</container>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        $elements = $dom->getElementsByTagName('element');
        
        $this->assertEquals('test', $this->loader->testParseValue($elements->item(0)));
        $this->assertEquals(42, $this->loader->testParseValue($elements->item(1)));
        $this->assertEquals(3.14, $this->loader->testParseValue($elements->item(2)));
        $this->assertTrue($this->loader->testParseValue($elements->item(3)));
        $this->assertNull($this->loader->testParseValue($elements->item(4)));
        $this->assertEquals('@test_service', $this->loader->testParseValue($elements->item(5)));
        $this->assertEquals('%test_param%', $this->loader->testParseValue($elements->item(6)));
        
        $collection = $this->loader->testParseValue($elements->item(7));
        $this->assertEquals(['item1', 'key1' => 'value1'], $collection);
    }

    public function testThrowsExceptionForInvalidRootElement(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<invalid-root>
</invalid-root>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('root element must be "container"');

        $this->loader->testParseXmlToArray($dom, 'test.xml');
    }

    public function testValidateSchemaBasicStructure(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<invalid-root>
</invalid-root>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Invalid XML schema');

        $this->loader->testValidateXmlSchema($dom, 'test.xml');
    }

    public function testValidateSchemaPassesForValidStructure(): void
    {
        if (!extension_loaded('dom') || !extension_loaded('libxml')) {
            $this->markTestSkipped('DOM and libxml extensions required');
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<container>
</container>';

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        // Should not throw exception
        $this->loader->testValidateXmlSchema($dom, 'test.xml');
        $this->assertTrue(true);
    }
}

/**
 * Testable version of XmlFileLoader that exposes protected methods.
 */
class TestableXmlFileLoader extends XmlFileLoader
{
    public function testDoLoad(string $file, array $options): void
    {
        $this->doLoad($file, $options);
    }

    public function testParseXmlToArray(\DOMDocument $dom, string $file): array
    {
        return $this->parseXmlToArray($dom, $file);
    }

    public function testParseImports(\DOMElement $container): array
    {
        return $this->parseImports($container);
    }

    public function testParseParameters(\DOMElement $container): array
    {
        return $this->parseParameters($container);
    }

    public function testParseServices(\DOMElement $container): array
    {
        return $this->parseServices($container);
    }

    public function testParseValue(\DOMElement $element): mixed
    {
        return $this->parseValue($element);
    }

    public function testValidateXmlSchema(\DOMDocument $dom, string $file): void
    {
        $this->validateXmlSchema($dom, $file);
    }
}