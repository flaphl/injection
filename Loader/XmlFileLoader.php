<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Loader;

use Flaphl\Element\Injection\Exception\ContainerException;

/**
 * XML file loader for container configurations.
 * 
 * Loads container configurations from XML files with schema validation
 * and comprehensive error handling.
 * 
 * @package Flaphl\Element\Injection\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class XmlFileLoader extends FileLoader
{
    /**
     * Get the file extensions supported by this loader.
     * 
     * @return array<string> List of supported extensions.
     */
    public function getSupportedExtensions(): array
    {
        return ['xml'];
    }

    /**
     * Perform the actual XML file loading.
     * 
     * @param string $file The file path to load.
     * @param array<string, mixed> $options Loader options.
     */
    protected function doLoad(string $file, array $options): void
    {
        if (!extension_loaded('dom')) {
            throw new ContainerException('The DOM extension is required for XML configuration loading.');
        }

        if (!extension_loaded('libxml')) {
            throw new ContainerException('The libxml extension is required for XML configuration loading.');
        }

        // Load and parse XML
        $dom = $this->loadXmlFile($file, $options);
        
        // Convert to array format
        $config = $this->parseXmlToArray($dom, $file);

        // Parse and apply configuration
        $this->parseConfig($config, $file, $options);
    }

    /**
     * Load an XML file and return DOM document.
     * 
     * @param string $file The file path to load.
     * @param array<string, mixed> $options Loader options.
     * @return \DOMDocument The loaded DOM document.
     */
    protected function loadXmlFile(string $file, array $options): \DOMDocument
    {
        $dom = new \DOMDocument();
        $dom->validateOnParse = true;

        // Configure error handling
        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);

        try {
            if (!$dom->load($file, LIBXML_NONET | LIBXML_NOBLANKS)) {
                $errors = libxml_get_errors();
                $errorMessages = array_map(fn($error) => trim($error->message), $errors);
                
                throw new ContainerException(sprintf(
                    'Invalid XML in configuration file "%s": %s',
                    $file,
                    implode(', ', $errorMessages)
                ));
            }

            // Validate schema if enabled
            if ($options['validate_schema']) {
                $this->validateXmlSchema($dom, $file);
            }

            return $dom;

        } finally {
            libxml_use_internal_errors($internalErrors);
            libxml_disable_entity_loader($disableEntities);
        }
    }

    /**
     * Parse XML DOM to array configuration.
     * 
     * @param \DOMDocument $dom The DOM document to parse.
     * @param string $file The source file for error reporting.
     * @return array<string, mixed> The parsed configuration.
     */
    protected function parseXmlToArray(\DOMDocument $dom, string $file): array
    {
        $config = [];
        $container = $dom->documentElement;

        if (!$container || $container->nodeName !== 'container') {
            throw new ContainerException(sprintf(
                'Invalid XML structure in "%s": root element must be "container".',
                $file
            ));
        }

        // Parse imports
        $imports = $this->parseImports($container);
        if (!empty($imports)) {
            $config['imports'] = $imports;
        }

        // Parse parameters
        $parameters = $this->parseParameters($container);
        if (!empty($parameters)) {
            $config['parameters'] = $parameters;
        }

        // Parse services
        $services = $this->parseServices($container);
        if (!empty($services)) {
            $config['services'] = $services;
        }

        return $config;
    }

    /**
     * Parse imports from XML.
     * 
     * @param \DOMElement $container The container element.
     * @return array<mixed> The parsed imports.
     */
    protected function parseImports(\DOMElement $container): array
    {
        $imports = [];
        $importElements = $container->getElementsByTagName('import');

        foreach ($importElements as $importElement) {
            $resource = $importElement->getAttribute('resource');
            if (!$resource) {
                continue;
            }

            $import = ['resource' => $resource];

            // Add optional attributes
            if ($importElement->hasAttribute('ignore-errors')) {
                $import['ignore_errors'] = $importElement->getAttribute('ignore-errors') === 'true';
            }

            $imports[] = $import;
        }

        return $imports;
    }

    /**
     * Parse parameters from XML.
     * 
     * @param \DOMElement $container The container element.
     * @return array<string, mixed> The parsed parameters.
     */
    protected function parseParameters(\DOMElement $container): array
    {
        $parameters = [];
        $parametersElements = $container->getElementsByTagName('parameters');

        foreach ($parametersElements as $parametersElement) {
            $parameterElements = $parametersElement->getElementsByTagName('parameter');

            foreach ($parameterElements as $parameterElement) {
                $key = $parameterElement->getAttribute('key');
                if (!$key) {
                    continue;
                }

                $value = $this->parseValue($parameterElement);
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Parse services from XML.
     * 
     * @param \DOMElement $container The container element.
     * @return array<string, mixed> The parsed services.
     */
    protected function parseServices(\DOMElement $container): array
    {
        $services = [];
        $servicesElements = $container->getElementsByTagName('services');

        foreach ($servicesElements as $servicesElement) {
            $serviceElements = $servicesElement->getElementsByTagName('service');

            foreach ($serviceElements as $serviceElement) {
                $id = $serviceElement->getAttribute('id');
                if (!$id) {
                    continue;
                }

                $service = [];

                // Parse class
                if ($serviceElement->hasAttribute('class')) {
                    $service['class'] = $serviceElement->getAttribute('class');
                }

                // Parse attributes
                if ($serviceElement->hasAttribute('public')) {
                    $service['public'] = $serviceElement->getAttribute('public') === 'true';
                }

                if ($serviceElement->hasAttribute('shared')) {
                    $service['shared'] = $serviceElement->getAttribute('shared') === 'true';
                }

                if ($serviceElement->hasAttribute('autowire')) {
                    $service['autowire'] = $serviceElement->getAttribute('autowire') === 'true';
                }

                // Parse arguments
                $arguments = $this->parseArguments($serviceElement);
                if (!empty($arguments)) {
                    $service['arguments'] = $arguments;
                }

                // Parse method calls
                $calls = $this->parseMethodCalls($serviceElement);
                if (!empty($calls)) {
                    $service['calls'] = $calls;
                }

                // Parse properties
                $properties = $this->parseProperties($serviceElement);
                if (!empty($properties)) {
                    $service['properties'] = $properties;
                }

                // Parse tags
                $tags = $this->parseTags($serviceElement);
                if (!empty($tags)) {
                    $service['tags'] = $tags;
                }

                $services[$id] = $service;
            }
        }

        return $services;
    }

    /**
     * Parse service arguments from XML.
     * 
     * @param \DOMElement $serviceElement The service element.
     * @return array<mixed> The parsed arguments.
     */
    protected function parseArguments(\DOMElement $serviceElement): array
    {
        $arguments = [];
        $argumentElements = $serviceElement->getElementsByTagName('argument');

        foreach ($argumentElements as $argumentElement) {
            $arguments[] = $this->parseValue($argumentElement);
        }

        return $arguments;
    }

    /**
     * Parse method calls from XML.
     * 
     * @param \DOMElement $serviceElement The service element.
     * @return array<mixed> The parsed method calls.
     */
    protected function parseMethodCalls(\DOMElement $serviceElement): array
    {
        $calls = [];
        $callElements = $serviceElement->getElementsByTagName('call');

        foreach ($callElements as $callElement) {
            $method = $callElement->getAttribute('method');
            if (!$method) {
                continue;
            }

            $arguments = [];
            $argumentElements = $callElement->getElementsByTagName('argument');

            foreach ($argumentElements as $argumentElement) {
                $arguments[] = $this->parseValue($argumentElement);
            }

            $calls[] = [
                'method' => $method,
                'arguments' => $arguments,
            ];
        }

        return $calls;
    }

    /**
     * Parse properties from XML.
     * 
     * @param \DOMElement $serviceElement The service element.
     * @return array<string, mixed> The parsed properties.
     */
    protected function parseProperties(\DOMElement $serviceElement): array
    {
        $properties = [];
        $propertyElements = $serviceElement->getElementsByTagName('property');

        foreach ($propertyElements as $propertyElement) {
            $name = $propertyElement->getAttribute('name');
            if (!$name) {
                continue;
            }

            $properties[$name] = $this->parseValue($propertyElement);
        }

        return $properties;
    }

    /**
     * Parse tags from XML.
     * 
     * @param \DOMElement $serviceElement The service element.
     * @return array<mixed> The parsed tags.
     */
    protected function parseTags(\DOMElement $serviceElement): array
    {
        $tags = [];
        $tagElements = $serviceElement->getElementsByTagName('tag');

        foreach ($tagElements as $tagElement) {
            $name = $tagElement->getAttribute('name');
            if (!$name) {
                continue;
            }

            $tag = ['name' => $name];

            // Add all other attributes
            foreach ($tagElement->attributes as $attribute) {
                if ($attribute->nodeName !== 'name') {
                    $tag[$attribute->nodeName] = $attribute->nodeValue;
                }
            }

            $tags[] = $tag;
        }

        return $tags;
    }

    /**
     * Parse a value from an XML element.
     * 
     * @param \DOMElement $element The element to parse.
     * @return mixed The parsed value.
     */
    protected function parseValue(\DOMElement $element): mixed
    {
        $type = $element->getAttribute('type');

        switch ($type) {
            case 'collection':
                return $this->parseCollection($element);

            case 'service':
                return '@' . $element->getAttribute('id');

            case 'parameter':
                return '%' . $element->nodeValue . '%';

            case 'boolean':
                return $element->nodeValue === 'true';

            case 'integer':
                return (int) $element->nodeValue;

            case 'float':
                return (float) $element->nodeValue;

            case 'null':
                return null;

            default:
                return $element->nodeValue;
        }
    }

    /**
     * Parse a collection from XML.
     * 
     * @param \DOMElement $element The collection element.
     * @return array<mixed> The parsed collection.
     */
    protected function parseCollection(\DOMElement $element): array
    {
        $collection = [];
        $itemElements = $element->getElementsByTagName('item');

        foreach ($itemElements as $itemElement) {
            $key = $itemElement->getAttribute('key');
            $value = $this->parseValue($itemElement);

            if ($key !== '') {
                $collection[$key] = $value;
            } else {
                $collection[] = $value;
            }
        }

        return $collection;
    }

    /**
     * Validate XML against schema.
     * 
     * @param \DOMDocument $dom The DOM document to validate.
     * @param string $file The source file for error reporting.
     */
    protected function validateXmlSchema(\DOMDocument $dom, string $file): void
    {
        // For now, just check basic structure
        // In a full implementation, you would validate against an XSD schema
        $container = $dom->documentElement;
        
        if (!$container || $container->nodeName !== 'container') {
            throw new ContainerException(sprintf(
                'Invalid XML schema in "%s": root element must be "container".',
                $file
            ));
        }
    }
}
