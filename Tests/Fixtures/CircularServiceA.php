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
 * Service A for testing circular dependency detection.
 * 
 * Demonstrates circular dependency scenarios that the container
 * should detect and handle appropriately to prevent infinite loops.
 */
class CircularServiceA
{
    public function __construct(private CircularServiceB $serviceB)
    {
    }

    /**
     * Get the injected service B.
     */
    public function getServiceB(): CircularServiceB
    {
        return $this->serviceB;
    }

    /**
     * Perform operation that depends on service B.
     */
    public function performOperation(): string
    {
        return 'ServiceA operation with: ' . $this->serviceB->getIdentifier();
    }

    /**
     * Get service identifier.
     */
    public function getIdentifier(): string
    {
        return 'ServiceA';
    }
}