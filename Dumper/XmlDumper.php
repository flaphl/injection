<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Dumper;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Exception\ContainerException;

/**
 * XML dumper for exporting containers as XML configurations.
 * 
 * Exports container definitions to XML format for configuration
 * exchange and human-readable documentation.
 * 
 * @package Flaphl\Element\Injection\Dumper
 * @author Jade Phyressi <jade@flaphl.com>
 */
class XmlDumper extends Dumper
{
    /**
     * Get the file extension for XML files.
     * 
     * @return string The file extension without dot.
     */
    public function getFileExtension(): string
    {
        return 'xml';
    }

    /**
     * Get the MIME type for XML content.
     * 
     * @return string The MIME type.
     */
    public function getMimeType(): string
    {
        return 'application/xml';
    }

    /**
     * Escape a string for use in XML.
     * 
     * @param string $string The string to escape.
     * @return string The escaped string.
     */
    protected function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Perform the actual XML dumping logic.
     * 
     * @param ContainerBuilder $container The container to dump.
     * @param array<string, mixed> $options Merged dumper options.
     * @return string The dumped container configuration.
     */
    protected function doDump(ContainerBuilder $container, array $options): string
    {
        if (!extension_loaded('dom')) {
            throw new ContainerException('The DOM extension is required for XML dumping.');
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // Create root element
        $containerElement = $dom->createElement('container');
        $containerElement->setAttribute('xmlns', 'http://flaphl.com/schema/dic/services');
        $containerElement->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $containerElement->setAttribute('xsi:schemaLocation', 'http://flaphl.com/schema/dic/services http://flaphl.com/schema/dic/services/services-1.0.xsd');
        
        $dom->appendChild($containerElement);

        // Add parameters
        $this->dumpParameters($dom, $containerElement, $container);

        // Add services
        $this->dumpServices($dom, $containerElement, $container);

        return $dom->saveXML();
    }

    /**
     * Dump parameters to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $containerElement The container element.
     * @param ContainerBuilder $container The container to dump.
     */
    protected function dumpParameters(\DOMDocument $dom, \DOMElement $containerElement, ContainerBuilder $container): void
    {
        $parameters = $container->getParameterBag()->all();
        
        if (empty($parameters)) {
            return;
        }

        $parametersElement = $dom->createElement('parameters');
        $containerElement->appendChild($parametersElement);

        foreach ($parameters as $name => $value) {
            $parameterElement = $dom->createElement('parameter');
            $parameterElement->setAttribute('key', $name);
            
            if (is_array($value)) {
                $this->dumpArray($dom, $parameterElement, $value);
            } else {
                $parameterElement->nodeValue = $this->escape((string) $value);
            }

            $parametersElement->appendChild($parameterElement);
        }
    }

    /**
     * Dump services to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $containerElement The container element.
     * @param ContainerBuilder $container The container to dump.
     */
    protected function dumpServices(\DOMDocument $dom, \DOMElement $containerElement, ContainerBuilder $container): void
    {
        $definitions = $container->getDefinitions();
        
        if (empty($definitions)) {
            return;
        }

        $servicesElement = $dom->createElement('services');
        $containerElement->appendChild($servicesElement);

        foreach ($definitions as $id => $definition) {
            $serviceElement = $dom->createElement('service');
            $serviceElement->setAttribute('id', $id);
            $serviceElement->setAttribute('class', $definition->getClass());

            // Set service attributes
            if (!$definition->isPublic()) {
                $serviceElement->setAttribute('public', 'false');
            }

            if (!$definition->isShared()) {
                $serviceElement->setAttribute('shared', 'false');
            }

            if ($definition->isAutowired()) {
                $serviceElement->setAttribute('autowire', 'true');
            }

            // Add arguments
            $this->dumpArguments($dom, $serviceElement, $definition->getArguments());

            // Add method calls
            $this->dumpMethodCalls($dom, $serviceElement, $definition->getMethodCalls());

            // Add properties
            $this->dumpProperties($dom, $serviceElement, $definition->getProperties());

            // Add tags
            $this->dumpTags($dom, $serviceElement, $definition->getTags());

            $servicesElement->appendChild($serviceElement);
        }
    }

    /**
     * Dump service arguments to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $serviceElement The service element.
     * @param array $arguments The arguments to dump.
     */
    protected function dumpArguments(\DOMDocument $dom, \DOMElement $serviceElement, array $arguments): void
    {
        if (empty($arguments)) {
            return;
        }

        foreach ($arguments as $argument) {
            $argumentElement = $dom->createElement('argument');
            $this->dumpValue($dom, $argumentElement, $argument);
            $serviceElement->appendChild($argumentElement);
        }
    }

    /**
     * Dump method calls to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $serviceElement The service element.
     * @param array $methodCalls The method calls to dump.
     */
    protected function dumpMethodCalls(\DOMDocument $dom, \DOMElement $serviceElement, array $methodCalls): void
    {
        if (empty($methodCalls)) {
            return;
        }

        foreach ($methodCalls as $call) {
            [$method, $arguments] = $call;
            
            $callElement = $dom->createElement('call');
            $callElement->setAttribute('method', $method);

            foreach ($arguments as $argument) {
                $argumentElement = $dom->createElement('argument');
                $this->dumpValue($dom, $argumentElement, $argument);
                $callElement->appendChild($argumentElement);
            }

            $serviceElement->appendChild($callElement);
        }
    }

    /**
     * Dump properties to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $serviceElement The service element.
     * @param array $properties The properties to dump.
     */
    protected function dumpProperties(\DOMDocument $dom, \DOMElement $serviceElement, array $properties): void
    {
        if (empty($properties)) {
            return;
        }

        foreach ($properties as $property => $value) {
            $propertyElement = $dom->createElement('property');
            $propertyElement->setAttribute('name', $property);
            $this->dumpValue($dom, $propertyElement, $value);
            $serviceElement->appendChild($propertyElement);
        }
    }

    /**
     * Dump tags to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $serviceElement The service element.
     * @param array $tags The tags to dump.
     */
    protected function dumpTags(\DOMDocument $dom, \DOMElement $serviceElement, array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        foreach ($tags as $name => $attributes) {
            $tagElement = $dom->createElement('tag');
            $tagElement->setAttribute('name', $name);

            foreach ($attributes as $key => $value) {
                $tagElement->setAttribute($key, (string) $value);
            }

            $serviceElement->appendChild($tagElement);
        }
    }

    /**
     * Dump a value to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $element The element to append to.
     * @param mixed $value The value to dump.
     */
    protected function dumpValue(\DOMDocument $dom, \DOMElement $element, mixed $value): void
    {
        if (is_array($value)) {
            $element->setAttribute('type', 'collection');
            $this->dumpArray($dom, $element, $value);
        } elseif (is_string($value)) {
            if (str_starts_with($value, '@')) {
                $element->setAttribute('type', 'service');
                $element->setAttribute('id', substr($value, 1));
            } elseif (str_starts_with($value, '%') && str_ends_with($value, '%')) {
                $element->setAttribute('type', 'parameter');
                $element->nodeValue = substr($value, 1, -1);
            } else {
                $element->nodeValue = $this->escape($value);
            }
        } elseif (is_bool($value)) {
            $element->setAttribute('type', 'boolean');
            $element->nodeValue = $value ? 'true' : 'false';
        } elseif (is_int($value)) {
            $element->setAttribute('type', 'integer');
            $element->nodeValue = (string) $value;
        } elseif (is_float($value)) {
            $element->setAttribute('type', 'float');
            $element->nodeValue = (string) $value;
        } elseif ($value === null) {
            $element->setAttribute('type', 'null');
        } else {
            $element->nodeValue = $this->escape((string) $value);
        }
    }

    /**
     * Dump an array to XML.
     * 
     * @param \DOMDocument $dom The DOM document.
     * @param \DOMElement $element The element to append to.
     * @param array $array The array to dump.
     */
    protected function dumpArray(\DOMDocument $dom, \DOMElement $element, array $array): void
    {
        foreach ($array as $key => $value) {
            if (is_int($key)) {
                $itemElement = $dom->createElement('item');
            } else {
                $itemElement = $dom->createElement('item');
                $itemElement->setAttribute('key', (string) $key);
            }

            $this->dumpValue($dom, $itemElement, $value);
            $element->appendChild($itemElement);
        }
    }
}
