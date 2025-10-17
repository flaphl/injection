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
 * Service configurator for fluent service definition building.
 * 
 * Provides a fluent interface for building service configurations
 * that can be loaded into containers with advanced options.
 * 
 * @package Flaphl\Element\Injection\Loader\Configurator
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ServiceConfigurator
{
    /**
     * @var ContainerBuilder The container builder.
     */
    protected ContainerBuilder $container;

    /**
     * @var array<string, mixed> Service configurations.
     */
    protected array $services = [];

    /**
     * @var string|null Current service ID being configured.
     */
    protected ?string $currentService = null;

    /**
     * Create a new service configurator.
     * 
     * @param ContainerBuilder $container The container builder.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
    }

    /**
     * Start configuring a service.
     * 
     * @param string $id The service ID.
     * @param string|null $class The service class.
     * @return static
     */
    public function service(string $id, ?string $class = null): static
    {
        $this->currentService = $id;
        $this->services[$id] = [
            'class' => $class ?? $id,
            'arguments' => [],
            'calls' => [],
            'properties' => [],
            'tags' => [],
            'public' => true,
            'shared' => true,
            'autowire' => false,
        ];

        return $this;
    }

    /**
     * Set the service class.
     * 
     * @param string $class The service class.
     * @return static
     */
    public function class(string $class): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['class'] = $class;
        return $this;
    }

    /**
     * Add service arguments.
     * 
     * @param mixed ...$arguments The arguments to add.
     * @return static
     */
    public function args(mixed ...$arguments): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['arguments'] = array_merge(
            $this->services[$this->currentService]['arguments'],
            $arguments
        );
        return $this;
    }

    /**
     * Add a single argument.
     * 
     * @param mixed $argument The argument to add.
     * @return static
     */
    public function arg(mixed $argument): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['arguments'][] = $argument;
        return $this;
    }

    /**
     * Reference another service as an argument.
     * 
     * @param string $serviceId The service ID to reference.
     * @return static
     */
    public function service_ref(string $serviceId): static
    {
        return $this->arg('@' . $serviceId);
    }

    /**
     * Reference a parameter as an argument.
     * 
     * @param string $parameterName The parameter name to reference.
     * @return static
     */
    public function param_ref(string $parameterName): static
    {
        return $this->arg('%' . $parameterName . '%');
    }

    /**
     * Add a method call.
     * 
     * @param string $method The method name.
     * @param array<mixed> $arguments The method arguments.
     * @return static
     */
    public function call(string $method, array $arguments = []): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['calls'][] = [
            'method' => $method,
            'arguments' => $arguments,
        ];
        return $this;
    }

    /**
     * Set a property value.
     * 
     * @param string $property The property name.
     * @param mixed $value The property value.
     * @return static
     */
    public function property(string $property, mixed $value): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['properties'][$property] = $value;
        return $this;
    }

    /**
     * Add a service tag.
     * 
     * @param string $name The tag name.
     * @param array<string, mixed> $attributes The tag attributes.
     * @return static
     */
    public function tag(string $name, array $attributes = []): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['tags'][] = [
            'name' => $name,
            ...$attributes,
        ];
        return $this;
    }

    /**
     * Set service visibility.
     * 
     * @param bool $public Whether the service is public.
     * @return static
     */
    public function public(bool $public = true): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['public'] = $public;
        return $this;
    }

    /**
     * Set service as private.
     * 
     * @return static
     */
    public function private(): static
    {
        return $this->public(false);
    }

    /**
     * Set service sharing mode.
     * 
     * @param bool $shared Whether the service is shared (singleton).
     * @return static
     */
    public function shared(bool $shared = true): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['shared'] = $shared;
        return $this;
    }

    /**
     * Set service as non-shared (prototype).
     * 
     * @return static
     */
    public function prototype(): static
    {
        return $this->shared(false);
    }

    /**
     * Enable or disable autowiring.
     * 
     * @param bool $autowire Whether to enable autowiring.
     * @return static
     */
    public function autowire(bool $autowire = true): static
    {
        $this->ensureCurrentService();
        $this->services[$this->currentService]['autowire'] = $autowire;
        return $this;
    }

    /**
     * Create an alias for the current service.
     * 
     * @param string $alias The alias name.
     * @return static
     */
    public function alias(string $alias): static
    {
        $this->ensureCurrentService();
        $this->service($alias, $this->currentService);
        return $this;
    }

    /**
     * Load all configured services into the container.
     */
    public function load(): void
    {
        foreach ($this->services as $id => $config) {
            $definition = $this->container->register($id, $config['class']);

            // Set arguments
            foreach ($config['arguments'] as $argument) {
                $definition->addArgument($argument);
            }

            // Set method calls
            foreach ($config['calls'] as $call) {
                $definition->addMethodCall($call['method'], $call['arguments']);
            }

            // Set properties
            foreach ($config['properties'] as $property => $value) {
                $definition->setProperty($property, $value);
            }

            // Set tags
            foreach ($config['tags'] as $tag) {
                $name = $tag['name'];
                $attributes = array_diff_key($tag, ['name' => null]);
                $definition->addTag($name, $attributes);
            }

            // Set flags
            $definition->setPublic($config['public']);
            $definition->setShared($config['shared']);
            $definition->setAutowired($config['autowire']);
        }
    }

    /**
     * Get the configured services array.
     * 
     * @return array<string, mixed> The services configuration.
     */
    public function getServices(): array
    {
        return $this->services;
    }

    /**
     * Export configuration as array suitable for file loaders.
     * 
     * @return array<string, mixed> The exportable configuration.
     */
    public function export(): array
    {
        $config = ['services' => []];

        foreach ($this->services as $id => $serviceConfig) {
            $service = ['class' => $serviceConfig['class']];

            if (!empty($serviceConfig['arguments'])) {
                $service['arguments'] = $serviceConfig['arguments'];
            }

            if (!empty($serviceConfig['calls'])) {
                $service['calls'] = $serviceConfig['calls'];
            }

            if (!empty($serviceConfig['properties'])) {
                $service['properties'] = $serviceConfig['properties'];
            }

            if (!empty($serviceConfig['tags'])) {
                $service['tags'] = $serviceConfig['tags'];
            }

            if (!$serviceConfig['public']) {
                $service['public'] = false;
            }

            if (!$serviceConfig['shared']) {
                $service['shared'] = false;
            }

            if ($serviceConfig['autowire']) {
                $service['autowire'] = true;
            }

            $config['services'][$id] = $service;
        }

        return $config;
    }

    /**
     * Ensure there's a current service being configured.
     * 
     * @throws \LogicException If no service is currently being configured.
     */
    protected function ensureCurrentService(): void
    {
        if ($this->currentService === null) {
            throw new \LogicException('No service is currently being configured. Call service() first.');
        }
    }
}