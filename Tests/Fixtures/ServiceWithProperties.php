<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Tests\Fixtures;

/**
 * Service with properties for testing property injection.
 * 
 * Demonstrates property-based dependency injection and state management
 * patterns within Flaphl service configurations.
 */
class ServiceWithProperties
{
    public string $property1 = '';
    public int $property2 = 0;
    public bool $property3 = false;
    
    private array $config = [];
    private array $metadata = [];

    /**
     * Set configuration array.
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Get current configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set metadata information.
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get all properties as array.
     */
    public function getProperties(): array
    {
        return [
            'property1' => $this->property1,
            'property2' => $this->property2,
            'property3' => $this->property3,
            'config' => $this->config,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Validate property states.
     */
    public function validate(): bool
    {
        return !empty($this->property1) && 
               $this->property2 >= 0 && 
               is_bool($this->property3);
    }

    /**
     * Get a summary of the service state.
     */
    public function getSummary(): array
    {
        return [
            'properties_set' => !empty($this->property1),
            'config_loaded' => !empty($this->config),
            'metadata_available' => !empty($this->metadata),
            'validation_passed' => $this->validate()
        ];
    }
}