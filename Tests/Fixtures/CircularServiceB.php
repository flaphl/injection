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
 * Service B for testing circular dependency detection.
 * 
 * Completes the circular dependency scenario with Service A,
 * allowing the container to test circular reference detection.
 */
class CircularServiceB
{
    public function __construct(private CircularServiceA $serviceA)
    {
    }

    /**
     * Get the injected service A.
     */
    public function getServiceA(): CircularServiceA
    {
        return $this->serviceA;
    }

    /**
     * Perform operation that depends on service A.
     */
    public function performOperation(): string
    {
        return 'ServiceB operation with: ' . $this->serviceA->getIdentifier();
    }

    /**
     * Get service identifier.
     */
    public function getIdentifier(): string
    {
        return 'ServiceB';
    }
}