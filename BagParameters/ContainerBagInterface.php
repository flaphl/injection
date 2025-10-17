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

use Flaphl\Element\Injection\ContainerInterface;

/**
 * Container-specific parameter bag interface.
 * 
 * Extends the basic parameter bag with container-specific functionality
 * including service parameter binding and container awareness.
 * 
 * @package Flaphl\Element\Injection\BagParameters
 * @author Jade Phyressi <jade@flaphl.com>
 */
interface ContainerBagInterface extends ParameterBagInterface
{
    /**
     * Set the container instance.
     *
     * @param ContainerInterface $container
     * @return static
     */
    public function setContainer(ContainerInterface $container): static;

    /**
     * Get the container instance.
     *
     * @return ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface;

    /**
     * Bind a parameter to a service.
     *
     * @param string $serviceId Service identifier
     * @param string $parameterName Parameter name
     * @param mixed $value Parameter value
     * @return static
     */
    public function bindServiceParameter(string $serviceId, string $parameterName, mixed $value): static;

    /**
     * Get parameters for a specific service.
     *
     * @param string $serviceId Service identifier
     * @return array
     */
    public function getServiceParameters(string $serviceId): array;

    /**
     * Resolve parameters with container services.
     *
     * @param string $name Parameter name
     * @return mixed
     */
    public function resolveWithContainer(string $name): mixed;

    /**
     * Set environment-specific parameters.
     *
     * @param string $environment Environment name
     * @param array $parameters Parameters for the environment
     * @return static
     */
    public function setEnvironmentParameters(string $environment, array $parameters): static;

    /**
     * Get parameters for current environment.
     *
     * @param string $environment Environment name
     * @return array
     */
    public function getEnvironmentParameters(string $environment): array;

    /**
     * Import parameters from another bag.
     *
     * @param ParameterBagInterface $bag Source parameter bag
     * @param array $mapping Optional parameter name mapping
     * @return static
     */
    public function importFrom(ParameterBagInterface $bag, array $mapping = []): static;

    /**
     * Export parameters to another bag.
     *
     * @param ParameterBagInterface $bag Target parameter bag
     * @param array $keys Optional parameter keys to export
     * @return static
     */
    public function exportTo(ParameterBagInterface $bag, array $keys = []): static;
}