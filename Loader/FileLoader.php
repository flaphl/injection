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

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Exception\ContainerException;

/**
 * Base class for file-based configuration loaders.
 * 
 * Provides common functionality for loading container configurations
 * from various file formats with validation and error handling.
 * 
 * @package Flaphl\Element\Injection\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
abstract class FileLoader
{
    /**
     * @var ContainerBuilder The container builder to configure.
     */
    protected ContainerBuilder $container;

    /**
     * @var array<string> Currently loading files to detect circular imports.
     */
    protected array $loading = [];

    /**
     * @var array<string, bool> Already loaded files to prevent duplicate loading.
     */
    protected array $loaded = [];

    /**
     * @var array<string, mixed> Default loader options.
     */
    protected array $defaultOptions = [
        'ignore_errors' => false,
        'allow_circular_imports' => false,
        'resolve_imports' => true,
        'validate_schema' => true,
        'cache_config' => true,
    ];

    /**
     * Create a new file loader.
     * 
     * @param ContainerBuilder $container The container builder to configure.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Load configuration from a file.
     * 
     * @param string $file The file path to load.
     * @param array<string, mixed> $options Loader options.
     * @throws ContainerException If the file cannot be loaded.
     */
    public function load(string $file, array $options = []): void
    {
        $options = array_merge($this->defaultOptions, $options);
        
        $file = $this->resolvePath($file);
        
        if (!$this->supports($file)) {
            throw new ContainerException(sprintf(
                'File "%s" is not supported by %s.',
                $file,
                static::class
            ));
        }

        if (!is_file($file)) {
            if ($options['ignore_errors']) {
                return;
            }
            throw new ContainerException(sprintf('Configuration file "%s" not found.', $file));
        }

        if (!is_readable($file)) {
            throw new ContainerException(sprintf('Configuration file "%s" is not readable.', $file));
        }

        // Check for circular imports
        if (in_array($file, $this->loading, true)) {
            if (!$options['allow_circular_imports']) {
                throw new ContainerException(sprintf(
                    'Circular import detected: %s',
                    implode(' -> ', [...$this->loading, $file])
                ));
            }
            return;
        }

        // Check if already loaded
        if (isset($this->loaded[$file])) {
            return;
        }

        $this->loading[] = $file;

        try {
            $this->doLoad($file, $options);
            $this->loaded[$file] = true;
        } catch (\Exception $e) {
            if (!$options['ignore_errors']) {
                throw new ContainerException(
                    sprintf('Error loading configuration file "%s": %s', $file, $e->getMessage()),
                    0,
                    $e
                );
            }
        } finally {
            array_pop($this->loading);
        }
    }

    /**
     * Check if this loader supports the given file.
     * 
     * @param string $file The file path to check.
     * @return bool True if supported.
     */
    public function supports(string $file): bool
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        return in_array($extension, $this->getSupportedExtensions(), true);
    }

    /**
     * Get the file extensions supported by this loader.
     * 
     * @return array<string> List of supported extensions.
     */
    abstract public function getSupportedExtensions(): array;

    /**
     * Perform the actual file loading.
     * 
     * @param string $file The file path to load.
     * @param array<string, mixed> $options Loader options.
     */
    abstract protected function doLoad(string $file, array $options): void;

    /**
     * Resolve a file path to an absolute path.
     * 
     * @param string $file The file path to resolve.
     * @return string The resolved absolute path.
     */
    protected function resolvePath(string $file): string
    {
        if (str_starts_with($file, '/') || preg_match('/^[A-Za-z]:/', $file)) {
            return $file; // Already absolute
        }

        // Make relative to current working directory
        return getcwd() . DIRECTORY_SEPARATOR . $file;
    }

    /**
     * Parse configuration data and apply to container.
     * 
     * @param array<string, mixed> $config The configuration data.
     * @param string $file The source file path for error reporting.
     * @param array<string, mixed> $options Loader options.
     */
    protected function parseConfig(array $config, string $file, array $options): void
    {
        // Load imports first
        if ($options['resolve_imports'] && isset($config['imports'])) {
            $this->loadImports($config['imports'], $file, $options);
        }

        // Load parameters
        if (isset($config['parameters'])) {
            $this->loadParameters($config['parameters'], $file);
        }

        // Load services
        if (isset($config['services'])) {
            $this->loadServices($config['services'], $file);
        }

        // Load extensions (if supported)
        if (isset($config['extensions'])) {
            $this->loadExtensions($config['extensions'], $file, $options);
        }
    }

    /**
     * Load imported configuration files.
     * 
     * @param array<mixed> $imports List of imports.
     * @param string $currentFile Current file path for relative resolution.
     * @param array<string, mixed> $options Loader options.
     */
    protected function loadImports(array $imports, string $currentFile, array $options): void
    {
        $currentDir = dirname($currentFile);

        foreach ($imports as $import) {
            if (is_string($import)) {
                $importFile = $import;
                $importOptions = [];
            } elseif (is_array($import) && isset($import['resource'])) {
                $importFile = $import['resource'];
                $importOptions = array_merge($options, array_intersect_key($import, $options));
            } else {
                throw new ContainerException(sprintf(
                    'Invalid import configuration in "%s".',
                    $currentFile
                ));
            }

            // Resolve relative paths
            if (!str_starts_with($importFile, '/') && !preg_match('/^[A-Za-z]:/', $importFile)) {
                $importFile = $currentDir . DIRECTORY_SEPARATOR . $importFile;
            }

            // Load with appropriate loader
            if (str_ends_with($importFile, '.php')) {
                $loader = new PhpFileLoader($this->container);
            } elseif (str_ends_with($importFile, '.xml')) {
                $loader = new XmlFileLoader($this->container);
            } else {
                // Try to auto-detect or use current loader
                $loader = $this;
            }

            $loader->load($importFile, $importOptions);
        }
    }

    /**
     * Load parameters into the container.
     * 
     * @param array<string, mixed> $parameters The parameters to load.
     * @param string $file The source file for error reporting.
     */
    protected function loadParameters(array $parameters, string $file): void
    {
        foreach ($parameters as $name => $value) {
            if (!is_string($name)) {
                throw new ContainerException(sprintf(
                    'Parameter name must be a string in "%s".',
                    $file
                ));
            }

            $this->container->setParameter($name, $value);
        }
    }

    /**
     * Load services into the container.
     * 
     * @param array<string, mixed> $services The services to load.
     * @param string $file The source file for error reporting.
     */
    protected function loadServices(array $services, string $file): void
    {
        foreach ($services as $id => $config) {
            if (!is_string($id)) {
                throw new ContainerException(sprintf(
                    'Service ID must be a string in "%s".',
                    $file
                ));
            }

            $this->loadService($id, $config, $file);
        }
    }

    /**
     * Load a single service definition.
     * 
     * @param string $id The service ID.
     * @param mixed $config The service configuration.
     * @param string $file The source file for error reporting.
     */
    protected function loadService(string $id, mixed $config, string $file): void
    {
        if (is_string($config)) {
            // Simple class name
            $definition = $this->container->register($id, $config);
        } elseif (is_array($config)) {
            // Complex service definition
            $class = $config['class'] ?? $id;
            $definition = $this->container->register($id, $class);

            // Set autowiring
            if (isset($config['autowire'])) {
                $definition->setAutowired((bool) $config['autowire']);
            }

            // Set public/private
            if (isset($config['public'])) {
                $definition->setPublic((bool) $config['public']);
            }

            // Set shared/singleton
            if (isset($config['shared'])) {
                $definition->setShared((bool) $config['shared']);
            }

            // Set arguments
            if (isset($config['arguments'])) {
                foreach ((array) $config['arguments'] as $argument) {
                    $definition->addArgument($argument);
                }
            }

            // Set properties
            if (isset($config['properties'])) {
                foreach ((array) $config['properties'] as $property => $value) {
                    $definition->setProperty($property, $value);
                }
            }

            // Set method calls
            if (isset($config['calls'])) {
                foreach ((array) $config['calls'] as $call) {
                    if (is_array($call) && isset($call['method'])) {
                        $method = $call['method'];
                        $arguments = $call['arguments'] ?? [];
                        $definition->addMethodCall($method, $arguments);
                    }
                }
            }

            // Set tags
            if (isset($config['tags'])) {
                foreach ((array) $config['tags'] as $tag) {
                    if (is_string($tag)) {
                        $definition->addTag($tag);
                    } elseif (is_array($tag) && isset($tag['name'])) {
                        $name = $tag['name'];
                        $attributes = array_diff_key($tag, ['name' => null]);
                        $definition->addTag($name, $attributes);
                    }
                }
            }
        } else {
            throw new ContainerException(sprintf(
                'Invalid service configuration for "%s" in "%s".',
                $id,
                $file
            ));
        }
    }

    /**
     * Load extensions (placeholder for subclasses).
     * 
     * @param array<string, mixed> $extensions The extensions to load.
     * @param string $file The source file for error reporting.
     * @param array<string, mixed> $options Loader options.
     */
    protected function loadExtensions(array $extensions, string $file, array $options): void
    {
        // Base implementation does nothing
        // Subclasses can override to provide extension support
    }

    /**
     * Validate configuration schema (placeholder for subclasses).
     * 
     * @param array<string, mixed> $config The configuration to validate.
     * @param string $file The source file for error reporting.
     * @return bool True if valid.
     */
    protected function validateSchema(array $config, string $file): bool
    {
        // Base implementation always returns true
        // Subclasses can override to provide schema validation
        return true;
    }
}
