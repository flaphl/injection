<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection;

/**
 * Represents a single parameter with metadata and validation.
 * 
 * Encapsulates parameter value, type information, validation rules,
 * and provides type-safe access to parameter data.
 * 
 * @package Flaphl\Element\Injection
 * @author Jade Phyressi <jade@flaphl.com>
 */
class Parameter
{
    /**
     * Parameter name.
     */
    protected string $name;

    /**
     * Parameter value.
     */
    protected mixed $value;

    /**
     * Parameter type.
     */
    protected ?string $type;

    /**
     * Parameter description.
     */
    protected ?string $description;

    /**
     * Whether the parameter is required.
     */
    protected bool $required;

    /**
     * Default value.
     */
    protected mixed $default;

    /**
     * Validation rules.
     */
    protected array $rules;

    /**
     * Parameter metadata.
     */
    protected array $metadata;

    /**
     * Create a new parameter.
     *
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @param array $options Parameter options
     */
    public function __construct(string $name, mixed $value, array $options = [])
    {
        $this->name = $name;
        $this->value = $value;
        $this->type = $options['type'] ?? null;
        $this->description = $options['description'] ?? null;
        $this->required = $options['required'] ?? false;
        $this->default = $options['default'] ?? null;
        $this->rules = $options['rules'] ?? [];
        $this->metadata = $options['metadata'] ?? [];
    }

    /**
     * Get parameter name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get parameter value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set parameter value.
     */
    public function setValue(mixed $value): static
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Get parameter type.
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * Set parameter type.
     */
    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get parameter description.
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Set parameter description.
     */
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Check if parameter is required.
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Set parameter as required.
     */
    public function setRequired(bool $required = true): static
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Get default value.
     */
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Set default value.
     */
    public function setDefault(mixed $default): static
    {
        $this->default = $default;
        return $this;
    }

    /**
     * Get validation rules.
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Set validation rules.
     */
    public function setRules(array $rules): static
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Add a validation rule.
     */
    public function addRule(string $rule, mixed $constraint): static
    {
        $this->rules[$rule] = $constraint;
        return $this;
    }

    /**
     * Get metadata.
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Set metadata.
     */
    public function setMetadata(array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get specific metadata value.
     */
    public function getMetadataValue(string $key): mixed
    {
        if (!array_key_exists($key, $this->metadata)) {
            throw new \InvalidArgumentException("Metadata key [{$key}] not found");
        }

        return $this->metadata[$key];
    }

    /**
     * Get specific metadata value with default.
     */
    public function getMetadataValueWithDefault(string $key, mixed $default): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set specific metadata value.
     */
    public function setMetadataValue(string $key, mixed $value): static
    {
        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Validate parameter value against rules.
     */
    public function validate(): bool
    {
        if ($this->required && $this->value === null) {
            return false;
        }

        foreach ($this->rules as $rule => $constraint) {
            if (!$this->applyRule($rule, $constraint)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get parameter value with type conversion.
     */
    public function getTypedValue(): mixed
    {
        if ($this->type === null) {
            return $this->value;
        }

        return $this->convertToType($this->value, $this->type);
    }

    /**
     * Check if parameter has a value.
     */
    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * Get parameter as array representation.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'type' => $this->type,
            'description' => $this->description,
            'required' => $this->required,
            'default' => $this->default,
            'rules' => $this->rules,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Create parameter from array.
     */
    public static function fromArray(array $data): static
    {
        $name = $data['name'] ?? throw new \InvalidArgumentException('Parameter name is required');
        $value = $data['value'] ?? null;
        
        unset($data['name'], $data['value']);
        
        return new static($name, $value, $data);
    }

    /**
     * Apply a validation rule.
     */
    protected function applyRule(string $rule, mixed $constraint): bool
    {
        return match ($rule) {
            'type' => $this->validateType($constraint),
            'min' => is_numeric($this->value) && $this->value >= $constraint,
            'max' => is_numeric($this->value) && $this->value <= $constraint,
            'minLength' => is_string($this->value) && strlen($this->value) >= $constraint,
            'maxLength' => is_string($this->value) && strlen($this->value) <= $constraint,
            'in' => in_array($this->value, (array) $constraint, true),
            'regex' => is_string($this->value) && preg_match($constraint, $this->value),
            'email' => is_string($this->value) && filter_var($this->value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => is_string($this->value) && filter_var($this->value, FILTER_VALIDATE_URL) !== false,
            default => true
        };
    }

    /**
     * Validate parameter type.
     */
    protected function validateType(string $expectedType): bool
    {
        return match ($expectedType) {
            'string' => is_string($this->value),
            'int', 'integer' => is_int($this->value),
            'float', 'double' => is_float($this->value),
            'bool', 'boolean' => is_bool($this->value),
            'array' => is_array($this->value),
            'object' => is_object($this->value),
            'null' => is_null($this->value),
            default => gettype($this->value) === $expectedType
        };
    }

    /**
     * Convert value to specified type.
     */
    protected function convertToType(mixed $value, string $type): mixed
    {
        return match (strtolower($type)) {
            'string', 'str' => (string) $value,
            'int', 'integer' => (int) $value,
            'float', 'double' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'array' => (array) $value,
            'object' => (object) $value,
            default => $value
        };
    }
}