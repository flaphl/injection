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
 * PHP file loader for container configurations.
 * 
 * Loads container configurations from PHP files that return
 * associative arrays with service and parameter definitions.
 * 
 * @package Flaphl\Element\Injection\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class PhpFileLoader extends FileLoader
{
    /**
     * Get the file extensions supported by this loader.
     * 
     * @return array<string> List of supported extensions.
     */
    public function getSupportedExtensions(): array
    {
        return ['php'];
    }

    /**
     * Perform the actual PHP file loading.
     * 
     * @param string $file The file path to load.
     * @param array<string, mixed> $options Loader options.
     */
    protected function doLoad(string $file, array $options): void
    {
        // Validate PHP syntax before loading
        if ($options['validate_schema']) {
            $this->validatePhpSyntax($file);
        }

        // Load the PHP file
        $config = $this->loadPhpFile($file);

        if (!is_array($config)) {
            throw new ContainerException(sprintf(
                'Configuration file "%s" must return an array.',
                $file
            ));
        }

        // Parse and apply configuration
        $this->parseConfig($config, $file, $options);
    }

    /**
     * Load a PHP configuration file.
     * 
     * @param string $file The file path to load.
     * @return mixed The returned value from the PHP file.
     */
    protected function loadPhpFile(string $file): mixed
    {
        // Use a closure to avoid variable pollution
        $loader = function (string $path) {
            return require $path;
        };

        return $loader($file);
    }

    /**
     * Validate PHP syntax before loading.
     * 
     * @param string $file The file path to validate.
     * @throws ContainerException If syntax is invalid.
     */
    protected function validatePhpSyntax(string $file): void
    {
        $output = [];
        $return = 0;
        
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $output, $return);
        
        if ($return !== 0) {
            throw new ContainerException(sprintf(
                'Syntax error in PHP configuration file "%s": %s',
                $file,
                implode("\n", $output)
            ));
        }
    }

    /**
     * Validate configuration schema.
     * 
     * @param array<string, mixed> $config The configuration to validate.
     * @param string $file The source file for error reporting.
     * @return bool True if valid.
     */
    protected function validateSchema(array $config, string $file): bool
    {
        $validKeys = ['imports', 'parameters', 'services', 'extensions'];
        
        foreach (array_keys($config) as $key) {
            if (!in_array($key, $validKeys, true)) {
                throw new ContainerException(sprintf(
                    'Invalid configuration key "%s" in "%s". Valid keys are: %s',
                    $key,
                    $file,
                    implode(', ', $validKeys)
                ));
            }
        }

        // Validate services structure
        if (isset($config['services']) && !is_array($config['services'])) {
            throw new ContainerException(sprintf(
                'Services configuration must be an array in "%s".',
                $file
            ));
        }

        // Validate parameters structure
        if (isset($config['parameters']) && !is_array($config['parameters'])) {
            throw new ContainerException(sprintf(
                'Parameters configuration must be an array in "%s".',
                $file
            ));
        }

        // Validate imports structure
        if (isset($config['imports']) && !is_array($config['imports'])) {
            throw new ContainerException(sprintf(
                'Imports configuration must be an array in "%s".',
                $file
            ));
        }

        return true;
    }
}
