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

use Flaphl\Element\Injection\BagParameters\ContainerBag;
use Flaphl\Element\Injection\BagParameters\ParameterBagInterface;

/**
 * Fluent container builder for dependency injection configuration.
 * 
 * Provides a fluent interface for building and configuring containers
 * with services, parameters, and contextual bindings.
 * 
 * @package Flaphl\Element\Injection
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerBuilder
{
    /**
     * Container instance.
     */
    protected Container $container;

    /**
     * Parameter bag.
     */
    protected ParameterBagInterface $parameterBag;

    /**
     * Service definitions.
     */
    protected array $definitions = [];

    /**
     * Tagged services.
     */
    protected array $tags = [];

    /**
     * Compiler passes.
     */
    protected array $compilerPasses = [];

    /**
     * Build configuration.
     */
    protected array $buildConfig = [
        'compile' => true,
        'cache' => false,
        'debug' => false,
    ];

    /**
     * Create a new container builder.
     *
     * @param ParameterBagInterface|null $parameterBag Optional parameter bag
     */
    public function __construct(?ParameterBagInterface $parameterBag = null)
    {
        $this->container = new Container();
        $this->parameterBag = $parameterBag ?? new ContainerBag();
        
        if ($this->parameterBag instanceof ContainerBag) {
            $this->parameterBag->setContainer($this->container);
        }
    }

    /**
     * Register a service.
     *
     * @param string $id Service identifier
     * @param string|null $class Service class name
     * @return ServiceDefinition
     */
    public function register(string $id, ?string $class = null): ServiceDefinition
    {
        $definition = new ServiceDefinition($id, $class ?? $id);
        $this->definitions[$id] = $definition;
        
        return $definition;
    }

    /**
     * Autowire a service.
     *
     * @param string $id Service identifier
     * @param string|null $class Service class name
     * @return ServiceDefinition
     */
    public function autowire(string $id, ?string $class = null): ServiceDefinition
    {
        return $this->register($id, $class)->setAutowired(true);
    }

    /**
     * Set a parameter.
     *
     * @param string $name Parameter name
     * @param mixed $value Parameter value
     * @return static
     */
    public function setParameter(string $name, mixed $value): static
    {
        $this->parameterBag->set($name, $value);
        return $this;
    }

    /**
     * Get a parameter.
     *
     * @param string $name Parameter name
     * @return mixed
     */
    public function getParameter(string $name): mixed
    {
        return $this->parameterBag->get($name);
    }

    /**
     * Set multiple parameters.
     *
     * @param array $parameters Parameters array
     * @return static
     */
    public function setParameters(array $parameters): static
    {
        $this->parameterBag->setMultiple($parameters);
        return $this;
    }

    /**
     * Load services from configuration.
     *
     * @param array $config Service configuration
     * @return static
     */
    public function loadFromConfig(array $config): static
    {
        if (isset($config['parameters'])) {
            $this->setParameters($config['parameters']);
        }

        if (isset($config['services'])) {
            foreach ($config['services'] as $id => $serviceConfig) {
                $this->registerFromConfig($id, $serviceConfig);
            }
        }

        return $this;
    }

    /**
     * Load services from a file.
     *
     * @param string $file Configuration file path
     * @return static
     */
    public function loadFromFile(string $file): static
    {
        if (!file_exists($file)) {
            throw new \InvalidArgumentException("Configuration file [{$file}] not found");
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        $config = match ($extension) {
            'json' => json_decode(file_get_contents($file), true),
            'php' => require $file,
            default => throw new \InvalidArgumentException("Unsupported configuration file format [{$extension}]")
        };

        if ($config === null) {
            throw new \InvalidArgumentException("Invalid configuration file [{$file}]");
        }

        return $this->loadFromConfig($config);
    }

    /**
     * Add a compiler pass.
     *
     * @param callable $pass Compiler pass function
     * @param int $priority Priority (higher runs first)
     * @return static
     */
    public function addCompilerPass(callable $pass, int $priority = 0): static
    {
        $this->compilerPasses[] = ['pass' => $pass, 'priority' => $priority];
        
        // Sort by priority
        usort($this->compilerPasses, fn($a, $b) => $b['priority'] <=> $a['priority']);
        
        return $this;
    }

    /**
     * Set build configuration.
     *
     * @param array $config Build configuration
     * @return static
     */
    public function setBuildConfig(array $config): static
    {
        $this->buildConfig = array_merge($this->buildConfig, $config);
        return $this;
    }

    /**
     * Enable compilation.
     *
     * @param bool $compile Enable compilation
     * @return static
     */
    public function setCompile(bool $compile = true): static
    {
        $this->buildConfig['compile'] = $compile;
        return $this;
    }

    /**
     * Enable debug mode.
     *
     * @param bool $debug Enable debug
     * @return static
     */
    public function setDebug(bool $debug = true): static
    {
        $this->buildConfig['debug'] = $debug;
        return $this;
    }

    /**
     * Build the container.
     *
     * @return ContainerInterface
     */
    public function build(): ContainerInterface
    {
        // Copy parameters to container
        foreach ($this->parameterBag->all() as $name => $value) {
            $this->container->setParameter($name, $value);
        }

        // Register service definitions
        foreach ($this->definitions as $id => $definition) {
            $this->registerDefinition($id, $definition);
        }

        // Run compiler passes
        if ($this->buildConfig['compile']) {
            $this->compile();
        }

        return $this->container;
    }

    /**
     * Get the current container (for inspection).
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Get the parameter bag.
     *
     * @return ParameterBagInterface
     */
    public function getParameterBag(): ParameterBagInterface
    {
        return $this->parameterBag;
    }

    /**
     * Get service definitions.
     *
     * @return array
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * Find services by tag.
     *
     * @param string $tag Tag name
     * @return array
     */
    public function findTaggedServiceIds(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * Register a service from configuration.
     */
    protected function registerFromConfig(string $id, array $config): ServiceDefinition
    {
        $class = $config['class'] ?? $id;
        $definition = $this->register($id, $class);

        if (isset($config['arguments'])) {
            $definition->setArguments($config['arguments']);
        }

        if (isset($config['calls'])) {
            foreach ($config['calls'] as $call) {
                $method = $call[0] ?? $call['method'];
                $arguments = $call[1] ?? $call['arguments'] ?? [];
                $definition->addMethodCall($method, $arguments);
            }
        }

        if (isset($config['properties'])) {
            foreach ($config['properties'] as $property => $value) {
                $definition->setProperty($property, $value);
            }
        }

        if (isset($config['tags'])) {
            foreach ($config['tags'] as $tag) {
                $tagName = is_string($tag) ? $tag : $tag['name'];
                $attributes = is_array($tag) ? $tag : [];
                $definition->addTag($tagName, $attributes);
            }
        }

        if (isset($config['public'])) {
            $definition->setPublic($config['public']);
        }

        if (isset($config['shared'])) {
            $definition->setShared($config['shared']);
        }

        if (isset($config['autowire'])) {
            $definition->setAutowired($config['autowire']);
        }

        return $definition;
    }

    /**
     * Register a service definition with the container.
     */
    protected function registerDefinition(string $id, ServiceDefinition $definition): void
    {
        $factory = function (ContainerInterface $container) use ($definition) {
            return $this->createService($container, $definition);
        };

        if ($definition->isShared()) {
            $this->container->singleton($id, $factory);
        } else {
            $this->container->bind($id, $factory);
        }

        // Store tags
        foreach ($definition->getTags() as $tag => $attributes) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }
            $this->tags[$tag][$id] = $attributes;
        }
    }

    /**
     * Create a service instance from definition.
     */
    protected function createService(ContainerInterface $container, ServiceDefinition $definition): object
    {
        $class = $this->resolveValue($container, $definition->getClass());
        $arguments = $this->resolveArguments($container, $definition->getArguments());

        $instance = new $class(...$arguments);

        // Set properties
        foreach ($definition->getProperties() as $property => $value) {
            $resolvedValue = $this->resolveValue($container, $value);
            $instance->$property = $resolvedValue;
        }

        // Call methods
        foreach ($definition->getMethodCalls() as $call) {
            [$method, $methodArgs] = $call;
            $resolvedArgs = $this->resolveArguments($container, $methodArgs);
            $instance->$method(...$resolvedArgs);
        }

        return $instance;
    }

    /**
     * Resolve service arguments.
     */
    protected function resolveArguments(ContainerInterface $container, array $arguments): array
    {
        return array_map(
            fn($arg) => $this->resolveValue($container, $arg),
            $arguments
        );
    }

    /**
     * Resolve a value (service references, parameters, etc.).
     */
    protected function resolveValue(ContainerInterface $container, mixed $value): mixed
    {
        // Handle arrays recursively
        if (is_array($value)) {
            return array_map(
                fn($item) => $this->resolveValue($container, $item),
                $value
            );
        }

        if (!is_string($value)) {
            return $value;
        }

        // Service reference
        if (str_starts_with($value, '@')) {
            $serviceId = substr($value, 1);
            return $container->get($serviceId);
        }

        // Multiple parameter references in a single string (must be checked before single parameter)
        if (str_contains($value, '%') && preg_match_all('/%([^%]+)%/', $value, $matches, PREG_SET_ORDER) > 1) {
            return preg_replace_callback(
                '/%([^%]+)%/',
                function ($matches) use ($container) {
                    $paramName = $matches[1];
                    return $container->getParameter($paramName, ['default' => $matches[0]]);
                },
                $value
            );
        }

        // Single parameter reference
        if (str_starts_with($value, '%') && str_ends_with($value, '%')) {
            $paramName = substr($value, 1, -1);
            return $container->getParameter($paramName, ['default' => $value]);
        }

        return $value;
    }

    /**
     * Run compiler passes.
     */
    protected function compile(): void
    {
        foreach ($this->compilerPasses as $passData) {
            $pass = $passData['pass'];
            $pass($this->container, $this);
        }
    }
}

/**
 * Service definition for fluent service configuration.
 */
class ServiceDefinition
{
    protected string $id;
    protected string $class;
    protected array $arguments = [];
    protected array $methodCalls = [];
    protected array $properties = [];
    protected array $tags = [];
    protected bool $public = true;
    protected bool $shared = true;
    protected bool $autowired = false;

    public function __construct(string $id, string $class)
    {
        $this->id = $id;
        $this->class = $class;
    }

    public function setClass(string $class): static
    {
        $this->class = $class;
        return $this;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function setArguments(array $arguments): static
    {
        $this->arguments = $arguments;
        return $this;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function addArgument(mixed $argument): static
    {
        $this->arguments[] = $argument;
        return $this;
    }

    public function addMethodCall(string $method, array $arguments = []): static
    {
        $this->methodCalls[] = [$method, $arguments];
        return $this;
    }

    public function getMethodCalls(): array
    {
        return $this->methodCalls;
    }

    public function setProperty(string $property, mixed $value): static
    {
        $this->properties[$property] = $value;
        return $this;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addTag(string $name, array $attributes = []): static
    {
        $this->tags[$name] = $attributes;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setPublic(bool $public): static
    {
        $this->public = $public;
        return $this;
    }

    public function isPublic(): bool
    {
        return $this->public;
    }

    public function setShared(bool $shared): static
    {
        $this->shared = $shared;
        return $this;
    }

    public function isShared(): bool
    {
        return $this->shared;
    }

    public function setAutowired(bool $autowired): static
    {
        $this->autowired = $autowired;
        return $this;
    }

    public function isAutowired(): bool
    {
        return $this->autowired;
    }
}