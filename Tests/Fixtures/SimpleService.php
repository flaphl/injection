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
 * Simple test service for dependency injection testing.
 * 
 * This service provides basic functionality for testing container resolution
 * and service instantiation patterns within the Flaphl framework.
 */
class SimpleService
{
    /**
     * Get the service name identifier.
     */
    public function getName(): string
    {
        return 'SimpleService';
    }

    /**
     * Perform a simple operation for testing method calls.
     */
    public function performOperation(string $input = 'default'): string
    {
        return "Processed: {$input}";
    }
}