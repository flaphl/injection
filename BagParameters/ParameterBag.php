<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\BagParameters;

use InvalidArgumentException;

/**
 * Parameter bag implementation for managing configuration parameters.
 * 
 * Provides comprehensive parameter management with type conversion,
 * validation, and flexible retrieval options.
 * 
 * @package Flaphl\Element\Injection\BagParameters
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ParameterBag implements ParameterBagInterface
{
    /**
     * The parameters storage.
     */
    protected array $parameters = [];

    /**
     * Create a new parameter bag.
     *
     * @param array $parameters Initial parameters
     */
    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $name, mixed $value): static
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $name): mixed
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("Parameter [{$name}] not found");
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getWithDefault(string $name, mixed $default): mixed
    {
        return $this->has($name) ? $this->parameters[$name] : $default;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $name): static
    {
        unset($this->parameters[$name]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function keys(): array
    {
        return array_keys($this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): static
    {
        $this->parameters = [];
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return count($this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(string $name, string $type): mixed
    {
        $value = $this->get($name);
        return $this->convertType($value, $type, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function resolveWithDefault(string $name, string $type, mixed $default): mixed
    {
        if (!$this->has($name)) {
            return $default;
        }

        return $this->convertType($this->parameters[$name], $type, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(array $parameters, bool $replace = true): static
    {
        if ($replace) {
            $this->parameters = array_merge($this->parameters, $parameters);
        } else {
            $this->parameters = array_merge($parameters, $this->parameters);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMatching(string $pattern): array
    {
        $matching = [];

        foreach ($this->parameters as $key => $value) {
            if (preg_match($pattern, $key)) {
                $matching[$key] = $value;
            }
        }

        return $matching;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $schema): bool
    {
        foreach ($schema as $parameter => $rules) {
            if (!$this->validateParameter($parameter, $rules)) {
                throw new InvalidArgumentException("Parameter [{$parameter}] validation failed");
            }
        }

        return true;
    }

    /**
     * Convert value to specified type.
     */
    protected function convertType(mixed $value, string $type, string $parameterName): mixed
    {
        return match (strtolower($type)) {
            'string', 'str' => (string) $value,
            'int', 'integer' => $this->convertToInt($value, $parameterName),
            'float', 'double' => $this->convertToFloat($value, $parameterName),
            'bool', 'boolean' => $this->convertToBool($value),
            'array' => $this->convertToArray($value, $parameterName),
            'object' => $this->convertToObject($value, $parameterName),
            'null' => null,
            default => throw new InvalidArgumentException("Unsupported type [{$type}] for parameter [{$parameterName}]")
        };
    }

    /**
     * Convert value to integer.
     */
    protected function convertToInt(mixed $value, string $parameterName): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException("Cannot convert parameter [{$parameterName}] to integer");
    }

    /**
     * Convert value to float.
     */
    protected function convertToFloat(mixed $value, string $parameterName): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidArgumentException("Cannot convert parameter [{$parameterName}] to float");
    }

    /**
     * Convert value to boolean.
     */
    protected function convertToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return match (strtolower($value)) {
                'true', '1', 'yes', 'on' => true,
                'false', '0', 'no', 'off', '' => false,
                default => (bool) $value
            };
        }

        return (bool) $value;
    }

    /**
     * Convert value to array.
     */
    protected function convertToArray(mixed $value, string $parameterName): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            // Try to decode JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($decoded) ? $decoded : [$decoded];
            }

            // Split by comma
            return array_map('trim', explode(',', $value));
        }

        if (is_object($value)) {
            return (array) $value;
        }

        throw new InvalidArgumentException("Cannot convert parameter [{$parameterName}] to array");
    }

    /**
     * Convert value to object.
     */
    protected function convertToObject(mixed $value, string $parameterName): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE && is_object($decoded)) {
                return $decoded;
            }
        }

        throw new InvalidArgumentException("Cannot convert parameter [{$parameterName}] to object");
    }

    /**
     * Validate a single parameter against rules.
     */
    protected function validateParameter(string $parameter, array $rules): bool
    {
        $value = $this->getWithDefault($parameter, null);

        foreach ($rules as $rule => $constraint) {
            if (!$this->applyValidationRule($value, $rule, $constraint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply a validation rule.
     */
    protected function applyValidationRule(mixed $value, string $rule, mixed $constraint): bool
    {
        return match ($rule) {
            'required' => $constraint ? $value !== null : true,
            'type' => $this->validateType($value, $constraint),
            'min' => is_numeric($value) && $value >= $constraint,
            'max' => is_numeric($value) && $value <= $constraint,
            'in' => in_array($value, (array) $constraint, true),
            'regex' => is_string($value) && preg_match($constraint, $value),
            default => true
        };
    }

    /**
     * Validate value type.
     */
    protected function validateType(mixed $value, string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            default => gettype($value) === $expectedType
        };
    }
}