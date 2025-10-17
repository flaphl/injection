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
 * Service with method calls for testing method injection.
 * 
 * Demonstrates method-based dependency injection and configuration
 * patterns used in Flaphl service definitions.
 */
class ServiceWithMethods
{
    private array $data = [];
    private array $config = [];

    /**
     * Add data via method injection.
     */
    public function addData(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    /**
     * Set configuration via method injection.
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Get all data combined with configuration.
     */
    public function getData(): array
    {
        return array_merge($this->data, $this->config);
    }

    /**
     * Get the count of data items.
     */
    public function getDataCount(): int
    {
        return count($this->data);
    }

    /**
     * Get configuration only.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if specific data exists.
     */
    public function hasData(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Process data using current configuration.
     */
    public function processData(): array
    {
        $processed = [];
        foreach ($this->data as $key => $value) {
            $processed[$key] = [
                'original' => $value,
                'processed_at' => time(),
                'config_applied' => !empty($this->config)
            ];
        }
        return $processed;
    }
}