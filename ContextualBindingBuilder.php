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
 * Contextual binding builder for dependency injection container.
 * 
 * Provides fluent interface for creating contextual bindings where
 * different implementations can be provided based on context.
 * 
 * @package Flaphl\Element\Injection
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContextualBindingBuilder
{
    /**
     * The container instance.
     */
    protected ContainerInterface $container;

    /**
     * The concrete class being contextually bound.
     */
    protected string $concrete;

    /**
     * The needs dependency.
     */
    protected ?string $needs = null;

    /**
     * Create a new contextual binding builder.
     *
     * @param ContainerInterface $container
     * @param string $concrete
     */
    public function __construct(ContainerInterface $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Define the abstract target of the contextual binding.
     *
     * @param string $needs
     * @return $this
     */
    public function needs(string $needs): static
    {
        $this->needs = $needs;
        return $this;
    }

    /**
     * Define the implementation for the contextual binding.
     *
     * @param mixed $implementation
     * @return ContainerInterface
     */
    public function give(mixed $implementation): ContainerInterface
    {
        return $this->giveTagged($implementation);
    }

    /**
     * Define the tagged services to assign to the contextual binding.
     *
     * @param mixed $implementation
     * @return ContainerInterface
     */
    public function giveTagged(mixed $implementation): ContainerInterface
    {
        if ($this->needs === null) {
            throw new \InvalidArgumentException('Contextual binding needs must be defined before giving implementation.');
        }

        // Store the contextual binding
        // This is a simplified implementation - in a full DI container,
        // you would store this in a more sophisticated way
        $bindingKey = $this->concrete . '::' . $this->needs;
        
        return $this->container->bind($bindingKey, $implementation);
    }

    /**
     * Define the implementation as a singleton for the contextual binding.
     *
     * @param mixed $implementation
     * @return ContainerInterface
     */
    public function giveSingleton(mixed $implementation): ContainerInterface
    {
        if ($this->needs === null) {
            throw new \InvalidArgumentException('Contextual binding needs must be defined before giving implementation.');
        }

        $bindingKey = $this->concrete . '::' . $this->needs;
        
        return $this->container->singleton($bindingKey, $implementation);
    }
}