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

/**
 * Parameter bag interface for managing configuration parameters.
 * 
 * Provides a flexible interface for storing, retrieving, and managing
 * configuration parameters with type-safe operations and validation.
 * 
 * @package Flaphl\Element\Injection\BagParameters
 * @author Jade Phyressi <jade@flaphl.com>
 */
interface ParameterBagInterface
{
    /**
     * Set a parameter value.
     *
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @return static
     */
    public function set(string $name, mixed $value): static;

    /**
     * Get a parameter value.
     *
     * @param string $name Parameter name
     * @return mixed
     */
    public function get(string $name): mixed;

    /**
     * Get a parameter value with default.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if parameter not found
     * @return mixed
     */
    public function getWithDefault(string $name, mixed $default): mixed;

    /**
     * Check if a parameter exists.
     *
     * @param string $name Parameter name
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Remove a parameter.
     *
     * @param string $name Parameter name
     * @return static
     */
    public function remove(string $name): static;

    /**
     * Get all parameters.
     *
     * @return array
     */
    public function all(): array;

    /**
     * Get parameter names.
     *
     * @return array
     */
    public function keys(): array;

    /**
     * Clear all parameters.
     *
     * @return static
     */
    public function clear(): static;

    /**
     * Count the number of parameters.
     *
     * @return int
     */
    public function count(): int;

    /**
     * Resolve parameter with type conversion.
     *
     * @param string $name Parameter name
     * @param string $type Expected type (string, int, bool, float, array)
     * @return mixed
     * @throws \InvalidArgumentException If type conversion fails
     */
    public function resolve(string $name, string $type): mixed;

    /**
     * Resolve parameter with type conversion and default.
     *
     * @param string $name Parameter name
     * @param string $type Expected type (string, int, bool, float, array)
     * @param mixed $default Default value
     * @return mixed
     * @throws \InvalidArgumentException If type conversion fails
     */
    public function resolveWithDefault(string $name, string $type, mixed $default): mixed;

    /**
     * Set multiple parameters at once.
     *
     * @param array $parameters Associative array of parameters
     * @param bool $replace Whether to replace existing parameters
     * @return static
     */
    public function setMultiple(array $parameters, bool $replace = true): static;

    /**
     * Get parameters matching a pattern.
     *
     * @param string $pattern Regular expression pattern
     * @return array
     */
    public function getMatching(string $pattern): array;

    /**
     * Validate parameters against schema.
     *
     * @param array $schema Validation schema
     * @return bool
     * @throws \InvalidArgumentException If validation fails
     */
    public function validate(array $schema): bool;
}