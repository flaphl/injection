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

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use Flaphl\Element\Injection\Exception\{
    CircularReferenceException,
    NotFoundException
};

/**
 * Dependency injection container implementation.
 * 
 * Provides comprehensive dependency injection functionality with 
 * service registration, resolution, parameter management, and contextual binding.
 * 
 * @package Flaphl\Element\Injection
 * @author Jade Phyressi <jade@flaphl.com>
 */
class Container implements ContainerInterface
{
    /**
     * The container's bindings.
     */
    protected array $bindings = [];

    /**
     * The container's shared instances.
     */
    protected array $instances = [];

    /**
     * The container's parameters.
     */
    protected array $parameters = [];

    /**
     * The container's contextual bindings.
     */
    protected array $contextualBindings = [];

    /**
     * The container's aliases.
     */
    protected array $aliases = [];

    /**
     * Services that have been resolved.
     */
    protected array $resolved = [];

    /**
     * Stack of services being built to detect circular dependencies.
     */
    protected array $buildStack = [];

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        return $this->make($id);
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id) || $this->canMake($id);
    }

    /**
     * {@inheritdoc}
     */
    public function bind(string $id, mixed $concrete, bool $shared = false): static
    {
        // Remove any existing instances or aliases
        unset($this->instances[$id], $this->aliases[$id]);

        // Store the binding
        $this->bindings[$id] = [
            'concrete' => $concrete,
            'shared' => $shared,
        ];

        // Mark as resolved if it was previously resolved
        if (isset($this->resolved[$id])) {
            $this->resolved[$id] = false;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function singleton(string $id, mixed $concrete): static
    {
        return $this->bind($id, $concrete, true);
    }

    /**
     * {@inheritdoc}
     */
    public function instance(string $id, object $instance): static
    {
        unset($this->aliases[$id]);

        $this->instances[$id] = $instance;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function canMake(string $id): bool
    {
        return $this->bound($id) || class_exists($id) || interface_exists($id);
    }

    /**
     * {@inheritdoc}
     */
    public function make(string $id, array $parameters = []): mixed
    {
        // Check for circular dependencies
        if (in_array($id, $this->buildStack)) {
            $circularPath = [...$this->buildStack, $id];
            throw new CircularReferenceException($circularPath);
        }

        // Check if we already have an instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $this->buildStack[] = $id;

        try {
            $concrete = $this->getConcrete($id);
            $object = $this->build($concrete, $parameters);

            // If this was registered as shared, store the instance
            if ($this->isShared($id)) {
                $this->instances[$id] = $object;
            }

            $this->resolved[$id] = true;

            return $object;
        } finally {
            array_pop($this->buildStack);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function call(callable|array|string $callback, array $parameters = []): mixed
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            [$class, $method] = explode('@', $callback, 2);
            $callback = [$this->make($class), $method];
        }

        if (is_array($callback)) {
            [$object, $method] = $callback;
            if (is_string($object)) {
                $object = $this->make($object);
            }
            $reflector = new ReflectionMethod($object, $method);
        } else {
            $reflector = new ReflectionFunction($callback);
        }

        $dependencies = $this->getMethodDependencies($reflector, $parameters);

        if ($reflector instanceof ReflectionMethod) {
            return $reflector->invokeArgs($object, $dependencies);
        } else {
            return $reflector->invokeArgs($dependencies);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter(string $name, mixed $value): static
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter(string $name, array $options = []): mixed
    {
        if (!$this->hasParameter($name)) {
            return $options['default'] ?? null;
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function hasParameter(string $name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getBindings(): array
    {
        return array_keys($this->bindings);
    }

    /**
     * {@inheritdoc}
     */
    public function getParameterNames(): array
    {
        return array_keys($this->parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function unbind(string $id): static
    {
        unset($this->bindings[$id], $this->instances[$id], $this->resolved[$id], $this->aliases[$id]);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilder($this, $concrete);
    }

    /**
     * Check if the given service is bound.
     */
    protected function bound(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || isset($this->aliases[$id]);
    }

    /**
     * Get the concrete implementation for the given service.
     */
    protected function getConcrete(string $id): mixed
    {
        // Check for alias
        if (isset($this->aliases[$id])) {
            return $this->getConcrete($this->aliases[$id]);
        }

        // Check for binding
        if (isset($this->bindings[$id])) {
            return $this->bindings[$id]['concrete'];
        }

        return $id;
    }

    /**
     * Check if the given service is shared.
     */
    protected function isShared(string $id): bool
    {
        return isset($this->bindings[$id]) && $this->bindings[$id]['shared'] === true;
    }

    /**
     * Build the given concrete service.
     */
    protected function build(mixed $concrete, array $parameters = []): mixed
    {
        // If the concrete is a callable, execute it
        if (is_callable($concrete)) {
            return $concrete($this, $parameters);
        }

        // If the concrete is a string, build the class
        if (is_string($concrete)) {
            return $this->buildClass($concrete, $parameters);
        }

        // Return the concrete as-is
        return $concrete;
    }

    /**
     * Build a class instance with dependency injection.
     */
    protected function buildClass(string $className, array $parameters = []): object
    {
        try {
            $reflector = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new NotFoundException("Class [{$className}] does not exist", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            throw new NotFoundException("Class [{$className}] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $className;
        }

        $dependencies = $this->getMethodDependencies($constructor, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Get method dependencies for dependency injection.
     */
    protected function getMethodDependencies(ReflectionMethod|ReflectionFunction $reflector, array $parameters = []): array
    {
        $dependencies = [];

        foreach ($reflector->getParameters() as $parameter) {
            $dependency = $this->resolveParameter($parameter, $parameters);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a parameter dependency.
     */
    protected function resolveParameter(ReflectionParameter $parameter, array $parameters = []): mixed
    {
        $name = $parameter->getName();

        // Check if we have a specific parameter value
        if (array_key_exists($name, $parameters)) {
            return $parameters[$name];
        }

        // Check if the parameter has a type hint
        $type = $parameter->getType();

        if ($type && !$type->isBuiltin()) {
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
            return $this->make($typeName);
        }

        // Check if the parameter has a default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Check if the parameter is nullable
        if ($parameter->allowsNull()) {
            return null;
        }

        throw new \RuntimeException("Unable to resolve parameter [{$name}]");
    }
}