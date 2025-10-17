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

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Enhanced container interface extending PSR-11 with additional functionality.
 * 
 * Provides dependency injection container functionality with enhanced 
 * service registration, parameter management, and contextual binding support.
 * 
 * @package Flaphl\Element\Injection
 * @author Jade Phyressi <jade@flaphl.com>
 */
interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Register a service with the container.
     *
     * @param string $id Service identifier
     * @param mixed $concrete Service implementation (callable, class name, or instance)
     * @param bool $shared Whether the service should be shared (singleton)
     * @return static
     * @throws ContainerExceptionInterface
     */
    public function bind(string $id, mixed $concrete, bool $shared = false): static;

    /**
     * Register a shared service (singleton) with the container.
     *
     * @param string $id Service identifier
     * @param mixed $concrete Service implementation
     * @return static
     * @throws ContainerExceptionInterface
     */
    public function singleton(string $id, mixed $concrete): static;

    /**
     * Register an existing instance as a service.
     *
     * @param string $id Service identifier
     * @param object $instance Service instance
     * @return static
     * @throws ContainerExceptionInterface
     */
    public function instance(string $id, object $instance): static;

    /**
     * Check if the container can build/make a service.
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function canMake(string $id): bool;

    /**
     * Build and return a service instance.
     *
     * @param string $id Service identifier
     * @param array $parameters Additional parameters for construction
     * @return mixed
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function make(string $id, array $parameters = []): mixed;

    /**
     * Call a method and inject its dependencies.
     *
     * @param callable|array|string $callback Method to call
     * @param array $parameters Additional parameters
     * @return mixed
     * @throws ContainerExceptionInterface
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed;

    /**
     * Set a parameter value.
     *
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @return static
     */
    public function setParameter(string $name, mixed $value): static;

    /**
     * Get a parameter value.
     *
     * @param string $name Parameter name
     * @param array $options Options including 'default' key
     * @return mixed
     */
    public function getParameter(string $name, array $options = []): mixed;

    /**
     * Check if a parameter exists.
     *
     * @param string $name Parameter name
     * @return bool
     */
    public function hasParameter(string $name): bool;

    /**
     * Get all registered service identifiers.
     *
     * @return array
     */
    public function getBindings(): array;

    /**
     * Get all parameter names.
     *
     * @return array
     */
    public function getParameterNames(): array;

    /**
     * Remove a service binding.
     *
     * @param string $id Service identifier
     * @return static
     */
    public function unbind(string $id): static;

    /**
     * Create a contextual binding builder.
     *
     * @param string $concrete Target class
     * @return ContextualBindingBuilder
     */
    public function when(string $concrete): ContextualBindingBuilder;
}