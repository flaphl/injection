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
 * Handler for unsupported file extensions.
 * 
 * Provides fallback handling for configuration files with extensions
 * that don't have specific loaders, with configurable strategies.
 * 
 * @package Flaphl\Element\Injection\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class UndefinedExtensionHandler
{
    /**
     * @var array<string, string> Mapping of extensions to fallback strategies.
     */
    protected array $strategies = [];

    /**
     * @var array<string, FileLoader> Custom loaders for specific extensions.
     */
    protected array $customLoaders = [];

    /**
     * Create a new undefined extension handler.
     */
    public function __construct()
    {
        // Default strategies for common extensions
        $this->strategies = [
            'yml' => 'yaml',
            'yaml' => 'yaml',
            'json' => 'json',
            'ini' => 'ini',
            'conf' => 'ini',
            'cfg' => 'ini',
            'env' => 'env',
        ];
    }

    /**
     * Check if the handler can handle a specific extension.
     * 
     * @param string $extension The file extension to check.
     * @return bool True if the extension can be handled.
     */
    public function canHandle(string $extension): bool
    {
        return isset($this->strategies[$extension]) || isset($this->customLoaders[$extension]);
    }

    /**
     * Handle a file with an undefined extension.
     * 
     * @param string $file The file path to handle.
     * @param ContainerBuilder $container The container builder.
     * @param array<string, mixed> $options Handler options.
     */
    public function handle(string $file, ContainerBuilder $container, array $options = []): void
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        // Use custom loader if available
        if (isset($this->customLoaders[$extension])) {
            $this->customLoaders[$extension]->load($file, $options);
            return;
        }

        // Use strategy-based handling
        if (isset($this->strategies[$extension])) {
            $strategy = $this->strategies[$extension];
            $this->handleByStrategy($file, $container, $strategy, $options);
            return;
        }

        throw new ContainerException(sprintf(
            'No handler available for file extension "%s" in file "%s".',
            $extension,
            $file
        ));
    }

    /**
     * Register a custom loader for a specific extension.
     * 
     * @param string $extension The file extension.
     * @param FileLoader $loader The loader instance.
     */
    public function registerLoader(string $extension, FileLoader $loader): void
    {
        $this->customLoaders[$extension] = $loader;
    }

    /**
     * Register a fallback strategy for an extension.
     * 
     * @param string $extension The file extension.
     * @param string $strategy The fallback strategy.
     */
    public function registerStrategy(string $extension, string $strategy): void
    {
        $this->strategies[$extension] = $strategy;
    }

    /**
     * Handle a file using a specific strategy.
     * 
     * @param string $file The file path to handle.
     * @param ContainerBuilder $container The container builder.
     * @param string $strategy The handling strategy.
     * @param array<string, mixed> $options Handler options.
     */
    protected function handleByStrategy(string $file, ContainerBuilder $container, string $strategy, array $options): void
    {
        switch ($strategy) {
            case 'yaml':
                $this->handleYamlFile($file, $container, $options);
                break;

            case 'json':
                $this->handleJsonFile($file, $container, $options);
                break;

            case 'ini':
                $this->handleIniFile($file, $container, $options);
                break;

            case 'env':
                $this->handleEnvFile($file, $container, $options);
                break;

            case 'ignore':
                // Silently ignore the file
                break;

            case 'error':
                throw new ContainerException(sprintf('Unsupported file extension in "%s".', $file));

            default:
                throw new ContainerException(sprintf('Unknown strategy "%s" for file "%s".', $strategy, $file));
        }
    }

    /**
     * Handle YAML files (requires symfony/yaml).
     * 
     * @param string $file The file path to handle.
     * @param ContainerBuilder $container The container builder.
     * @param array<string, mixed> $options Handler options.
     */
    protected function handleYamlFile(string $file, ContainerBuilder $container, array $options): void
    {
        if (!class_exists('Symfony\\Component\\Yaml\\Yaml')) {
            throw new ContainerException(
                'The Symfony Yaml component is required to load YAML configuration files. ' .
                'Try running "composer require symfony/yaml".'
            );
        }

        try {
            $config = \Symfony\Component\Yaml\Yaml::parseFile($file);
            
            if (!is_array($config)) {
                throw new ContainerException(sprintf('Invalid YAML configuration in "%s".', $file));
            }

            $this->loadArrayConfig($config, $container, $file);
            
        } catch (\Exception $e) {
            throw new ContainerException(
                sprintf('Error parsing YAML file "%s": %s', $file, $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Handle JSON files.
     * 
     * @param string $file The file path to handle.
     * @param ContainerBuilder $container The container builder.
     * @param array<string, mixed> $options Handler options.
     */
    protected function handleJsonFile(string $file, ContainerBuilder $container, array $options): void
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new ContainerException(sprintf('Cannot read file "%s".', $file));
        }

        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ContainerException(sprintf(
                'Invalid JSON in file "%s": %s',
                $file,
                json_last_error_msg()
            ));
        }

        if (!is_array($config)) {
            throw new ContainerException(sprintf('JSON configuration must be an object in "%s".', $file));
        }

        $this->loadArrayConfig($config, $container, $file);
    }

    /**
     * Handle INI files.
     * 
     * @param string $file The file path to handle.
     * @param ContainerBuilder $container The container builder.
     * @param array<string, mixed> $options Handler options.
     */
    protected function handleIniFile(string $file, ContainerBuilder $container, array $options): void
    {
        $config = parse_ini_file($file, true, INI_SCANNER_TYPED);
        
        if ($config === false) {
            throw new ContainerException(sprintf('Cannot parse INI file "%s".', $file));
        }

        // Convert INI structure to container configuration
        $containerConfig = [];

        // Load parameters from INI sections
        if (isset($config['parameters'])) {
            $containerConfig['parameters'] = $config['parameters'];
        }

        // Try to load services (limited support)
        if (isset($config['services'])) {
            $containerConfig['services'] = $this->convertIniServices($config['services']);
        }

        $this->loadArrayConfig($containerConfig, $container, $file);
    }

    /**
     * Handle .env files (environment variables).
     * 
     * @param string $file The file path to handle.
     * @param ContainerBuilder $container The container builder.
     * @param array<string, mixed> $options Handler options.
     */
    protected function handleEnvFile(string $file, ContainerBuilder $container, array $options): void
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            throw new ContainerException(sprintf('Cannot read environment file "%s".', $file));
        }

        $parameters = [];

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            
            // Skip comments
            if (str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=VALUE format
            if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*)$/', $line, $matches)) {
                $key = $matches[1];
                $value = $matches[2];

                // Remove quotes if present
                if (preg_match('/^"(.*)"$/', $value, $quoted)) {
                    $value = $quoted[1];
                } elseif (preg_match('/^\'(.*)\'$/', $value, $quoted)) {
                    $value = $quoted[1];
                }

                $parameters[$key] = $value;
            }
        }

        // Load as parameters
        foreach ($parameters as $name => $value) {
            $container->setParameter($name, $value);
        }
    }

    /**
     * Load array configuration into container.
     * 
     * @param array<string, mixed> $config The configuration array.
     * @param ContainerBuilder $container The container builder.
     * @param string $file The source file for error reporting.
     */
    protected function loadArrayConfig(array $config, ContainerBuilder $container, string $file): void
    {
        // Create a temporary PHP loader to handle the array config
        $loader = new class($container) extends PhpFileLoader {
            public function loadArrayConfig(array $config, string $file): void {
                $this->parseConfig($config, $file, $this->defaultOptions);
            }
        };

        $loader->loadArrayConfig($config, $file);
    }

    /**
     * Convert INI services format to container services format.
     * 
     * @param array<string, mixed> $iniServices The INI services configuration.
     * @return array<string, mixed> The converted services configuration.
     */
    protected function convertIniServices(array $iniServices): array
    {
        $services = [];

        foreach ($iniServices as $id => $config) {
            if (is_string($config)) {
                // Simple class name
                $services[$id] = $config;
            } elseif (is_array($config)) {
                // Complex service definition
                $service = [];
                
                if (isset($config['class'])) {
                    $service['class'] = $config['class'];
                }

                if (isset($config['arguments'])) {
                    $service['arguments'] = explode(',', $config['arguments']);
                }

                if (isset($config['public'])) {
                    $service['public'] = filter_var($config['public'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($config['shared'])) {
                    $service['shared'] = filter_var($config['shared'], FILTER_VALIDATE_BOOLEAN);
                }

                if (isset($config['autowire'])) {
                    $service['autowire'] = filter_var($config['autowire'], FILTER_VALIDATE_BOOLEAN);
                }

                $services[$id] = $service;
            }
        }

        return $services;
    }

    /**
     * Get available strategies.
     * 
     * @return array<string, string> Available strategies by extension.
     */
    public function getStrategies(): array
    {
        return $this->strategies;
    }

    /**
     * Get registered custom loaders.
     * 
     * @return array<string, FileLoader> Custom loaders by extension.
     */
    public function getCustomLoaders(): array
    {
        return $this->customLoaders;
    }
}
