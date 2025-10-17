<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Loader\Configurator;

use Flaphl\Element\Injection\ContainerBuilder;

/**
 * Parameter configurator for fluent parameter definition building.
 * 
 * Provides a fluent interface for building parameter configurations
 * with type conversion, validation, and environment variable support.
 * 
 * @package Flaphl\Element\Injection\Loader\Configurator
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ParameterConfigurator
{
    /**
     * @var ContainerBuilder The container builder.
     */
    protected ContainerBuilder $container;

    /**
     * @var array<string, mixed> Parameter configurations.
     */
    protected array $parameters = [];

    /**
     * @var array<string, mixed> Parameter metadata.
     */
    protected array $metadata = [];

    /**
     * Create a new parameter configurator.
     * 
     * @param ContainerBuilder $container The container builder.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Set a parameter value.
     * 
     * @param string $name The parameter name.
     * @param mixed $value The parameter value.
     * @return static
     */
    public function set(string $name, mixed $value): static
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Set a string parameter.
     * 
     * @param string $name The parameter name.
     * @param string $value The string value.
     * @return static
     */
    public function string(string $name, string $value): static
    {
        return $this->set($name, $value);
    }

    /**
     * Set an integer parameter.
     * 
     * @param string $name The parameter name.
     * @param int $value The integer value.
     * @return static
     */
    public function int(string $name, int $value): static
    {
        return $this->set($name, $value);
    }

    /**
     * Set a float parameter.
     * 
     * @param string $name The parameter name.
     * @param float $value The float value.
     * @return static
     */
    public function float(string $name, float $value): static
    {
        return $this->set($name, $value);
    }

    /**
     * Set a boolean parameter.
     * 
     * @param string $name The parameter name.
     * @param bool $value The boolean value.
     * @return static
     */
    public function bool(string $name, bool $value): static
    {
        return $this->set($name, $value);
    }

    /**
     * Set an array parameter.
     * 
     * @param string $name The parameter name.
     * @param array<mixed> $value The array value.
     * @return static
     */
    public function array(string $name, array $value): static
    {
        return $this->set($name, $value);
    }

    /**
     * Set a parameter from an environment variable.
     * 
     * @param string $name The parameter name.
     * @param string $envVar The environment variable name.
     * @param mixed $default The default value if env var is not set.
     * @param string|null $type The type to cast to ('string', 'int', 'float', 'bool').
     * @return static
     */
    public function env(string $name, string $envVar, mixed $default = '__FLAPHL_NO_DEFAULT__', ?string $type = null): static
    {
        $value = $_ENV[$envVar] ?? getenv($envVar);
        
        if ($value === false) {
            $value = $default === '__FLAPHL_NO_DEFAULT__' ? null : $default;
        } elseif ($type !== null) {
            $value = $this->castType($value, $type);
        }

        $this->metadata[$name] = [
            'source' => 'environment',
            'env_var' => $envVar,
            'type' => $type,
            'default' => $default === '__FLAPHL_NO_DEFAULT__' ? null : $default,
        ];

        return $this->set($name, $value);
    }

    /**
     * Set multiple parameters from environment variables with a prefix.
     * 
     * @param string $prefix The environment variable prefix.
     * @param string|null $parameterPrefix The parameter name prefix.
     * @param string|null $type The type to cast to.
     * @return static
     */
    public function envPrefix(string $prefix, ?string $parameterPrefix = null, ?string $type = null): static
    {
        $parameterPrefix = $parameterPrefix ?? strtolower($prefix);
        
        foreach ($_ENV as $envVar => $value) {
            if (str_starts_with($envVar, $prefix)) {
                $paramName = $parameterPrefix . strtolower(substr($envVar, strlen($prefix)));
                
                if ($type !== null) {
                    $value = $this->castType($value, $type);
                }

                $this->metadata[$paramName] = [
                    'source' => 'environment',
                    'env_var' => $envVar,
                    'type' => $type,
                    'prefix' => $prefix,
                ];

                $this->set($paramName, $value);
            }
        }

        return $this;
    }

    /**
     * Set parameters from a .env file.
     * 
     * @param string $file The .env file path.
     * @param string|null $prefix Parameter name prefix.
     * @return static
     */
    public function envFile(string $file, ?string $prefix = null): static
    {
        if (!is_file($file) || !is_readable($file)) {
            return $this;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return $this;
        }

        foreach ($lines as $line) {
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

                $paramName = $prefix ? $prefix . strtolower($key) : strtolower($key);
                
                $this->metadata[$paramName] = [
                    'source' => 'env_file',
                    'file' => $file,
                    'env_var' => $key,
                ];

                $this->set($paramName, $value);
            }
        }

        return $this;
    }

    /**
     * Set a parameter with a computed value.
     * 
     * @param string $name The parameter name.
     * @param callable $callback The callback to compute the value.
     * @return static
     */
    public function computed(string $name, callable $callback): static
    {
        $value = $callback();
        
        $this->metadata[$name] = [
            'source' => 'computed',
            'callback' => $callback,
        ];

        return $this->set($name, $value);
    }

    /**
     * Set a parameter with validation.
     * 
     * @param string $name The parameter name.
     * @param mixed $value The parameter value.
     * @param callable $validator The validation callback.
     * @param string|null $errorMessage Custom error message.
     * @return static
     */
    public function validated(string $name, mixed $value, callable $validator, ?string $errorMessage = null): static
    {
        if (!$validator($value)) {
            $message = $errorMessage ?? sprintf('Parameter "%s" validation failed.', $name);
            throw new \InvalidArgumentException($message);
        }

        $this->metadata[$name] = [
            'source' => 'validated',
            'validator' => $validator,
        ];

        return $this->set($name, $value);
    }

    /**
     * Set multiple parameters from an array.
     * 
     * @param array<string, mixed> $parameters The parameters to set.
     * @param string|null $prefix Parameter name prefix.
     * @return static
     */
    public function batch(array $parameters, ?string $prefix = null): static
    {
        foreach ($parameters as $name => $value) {
            $paramName = $prefix ? $prefix . $name : $name;
            $this->set($paramName, $value);
        }

        return $this;
    }

    /**
     * Load all configured parameters into the container.
     */
    public function load(): void
    {
        foreach ($this->parameters as $name => $value) {
            $this->container->setParameter($name, $value);
        }
    }

    /**
     * Get the configured parameters array.
     * 
     * @return array<string, mixed> The parameters configuration.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get parameter metadata.
     * 
     * @return array<string, mixed> The parameter metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Export configuration as array suitable for file loaders.
     * 
     * @return array<string, mixed> The exportable configuration.
     */
    public function export(): array
    {
        return ['parameters' => $this->parameters];
    }

    /**
     * Cast a value to a specific type.
     * 
     * @param mixed $value The value to cast.
     * @param string $type The target type.
     * @return mixed The cast value.
     */
    protected function castType(mixed $value, string $type): mixed
    {
        return match ($type) {
            'string' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * Validate a parameter name.
     * 
     * @param string $name The parameter name to validate.
     * @throws \InvalidArgumentException If the name is invalid.
     */
    protected function validateParameterName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf(
                'Parameter name "%s" is invalid. Must start with a letter or underscore and contain only letters, numbers, dots, and underscores.',
                $name
            ));
        }
    }
}