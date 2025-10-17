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
 * Service with dependencies for testing dependency injection.
 * 
 * Demonstrates constructor injection patterns and service composition
 * commonly used in Flaphl applications.
 */
class DependentService
{
    public function __construct(
        private SimpleService $simpleService,
        private string $message = 'default'
    ) {
    }

    /**
     * Get the injected simple service.
     */
    public function getSimpleService(): SimpleService
    {
        return $this->simpleService;
    }

    /**
     * Get the configured message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Get a combined message from dependencies.
     */
    public function getFullMessage(): string
    {
        return $this->message . ' from ' . $this->simpleService->getName();
    }

    /**
     * Demonstrate method chaining and service delegation.
     */
    public function processWithDependency(string $input): string
    {
        return $this->simpleService->performOperation($this->message . ': ' . $input);
    }
}