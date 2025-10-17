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
 * Container-aware parameter bag implementation.
 * 
 * Extends basic parameter bag functionality with container integration,
 * service parameter binding, and environment-specific configuration.
 * 
 * @package Flaphl\Element\Injection\BagParameters
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerBag extends ParameterBag implements ContainerBagInterface
{
    /**
     * Container instance.
     */
    protected ?ContainerInterface $container = null;

    /**
     * Service-specific parameters.
     */
    protected array $serviceParameters = [];

    /**
     * Environment-specific parameters.
     */
    protected array $environmentParameters = [];

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container): static
    {
        $this->container = $container;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * {@inheritdoc}
     */
    public function bindServiceParameter(string $serviceId, string $parameterName, mixed $value): static
    {
        if (!isset($this->serviceParameters[$serviceId])) {
            $this->serviceParameters[$serviceId] = [];
        }

        $this->serviceParameters[$serviceId][$parameterName] = $value;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceParameters(string $serviceId): array
    {
        return $this->serviceParameters[$serviceId] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function resolveWithContainer(string $name): mixed
    {
        $value = $this->getWithDefault($name, null);

        // If value is a string starting with @, resolve as service
        if (is_string($value) && str_starts_with($value, '@')) {
            $serviceId = substr($value, 1);
            
            if ($this->container && $this->container->has($serviceId)) {
                return $this->container->get($serviceId);
            }
        }

        // If value is a string with %param% syntax, resolve parameter references
        if (is_string($value) && preg_match('/^%([^%]+)%$/', $value, $matches)) {
            $paramName = $matches[1];
            return $this->getWithDefault($paramName, $value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setEnvironmentParameters(string $environment, array $parameters): static
    {
        $this->environmentParameters[$environment] = $parameters;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironmentParameters(string $environment): array
    {
        return $this->environmentParameters[$environment] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function importFrom(ParameterBagInterface $bag, array $mapping = []): static
    {
        $parameters = $bag->all();

        if (empty($mapping)) {
            $this->setMultiple($parameters);
        } else {
            foreach ($mapping as $sourceKey => $targetKey) {
                if ($bag->has($sourceKey)) {
                    $this->set($targetKey, $bag->getWithDefault($sourceKey, null));
                }
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function exportTo(ParameterBagInterface $bag, array $keys = []): static
    {
        if (empty($keys)) {
            $keys = $this->keys();
        }

        foreach ($keys as $key) {
            if ($this->has($key)) {
                $value = $this->getWithDefault($key, null);
                if (method_exists($bag, 'set')) {
                    $bag->set($key, $value);
                }
            }
        }

        return $this;
    }

    /**
     * Get all parameters including service and environment parameters.
     */
    public function getAllExtended(): array
    {
        return [
            'parameters' => $this->all(),
            'serviceParameters' => $this->serviceParameters,
            'environmentParameters' => $this->environmentParameters,
        ];
    }

    /**
     * Load environment parameters into main parameters.
     */
    public function loadEnvironment(string $environment): static
    {
        $envParams = $this->getEnvironmentParameters($environment);
        $this->setMultiple($envParams);
        return $this;
    }

    /**
     * Process parameter value with container resolution.
     */
    protected function processValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        // Service reference (@service_id)
        if (str_starts_with($value, '@')) {
            $serviceId = substr($value, 1);
            if ($this->container && $this->container->has($serviceId)) {
                return $this->container->get($serviceId);
            }
        }

        // Parameter reference (%parameter_name%)
        if (preg_match('/^%([^%]+)%$/', $value, $matches)) {
            $paramName = $matches[1];
            return $this->getWithDefault($paramName, $value);
        }

        // Mixed parameter/service references in string
        $processed = preg_replace_callback(
            '/%([^%]+)%/',
            fn($matches) => (string) $this->getWithDefault($matches[1], $matches[0]),
            $value
        );

        return $processed;
    }

    /**
     * Override get methods to process values.
     */
    public function get(string $name): mixed
    {
        return $this->processValue(parent::get($name));
    }

    /**
     * Override getWithDefault to process values.
     */
    public function getWithDefault(string $name, mixed $default): mixed
    {
        if (!$this->has($name)) {
            return $default;
        }

        return $this->processValue($this->parameters[$name]);
    }
}