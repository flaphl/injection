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
 * Interface for testing interface binding and polymorphism.
 * 
 * Demonstrates contract-driven architecture patterns within Flaphl,
 * supporting interface-to-implementation binding scenarios.
 */
interface TestServiceInterface
{
    /**
     * Get the service type identifier.
     */
    public function getType(): string;

    /**
     * Execute a test operation.
     */
    public function execute(array $parameters = []): mixed;
}