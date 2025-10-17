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
 * Primary implementation of test service interface.
 * 
 * Provides concrete functionality for testing interface binding,
 * service resolution, and polymorphic behavior in the container.
 */
class TestServiceImplementation implements TestServiceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'implementation';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $parameters = []): mixed
    {
        return [
            'type' => $this->getType(),
            'parameters' => $parameters,
            'timestamp' => time(),
            'status' => 'executed'
        ];
    }

    /**
     * Implementation-specific method for extended functionality.
     */
    public function getImplementationDetails(): array
    {
        return [
            'class' => self::class,
            'features' => ['basic', 'extended'],
            'version' => '1.0.0'
        ];
    }
}