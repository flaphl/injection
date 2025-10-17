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
 * Alternative implementation for testing service overrides.
 * 
 * Demonstrates service replacement patterns and multiple implementations
 * of the same interface for testing binding flexibility.
 */
class AlternativeTestService implements TestServiceInterface
{
    /**
     * {@inheritdoc}
     */
    public function getType(): string
    {
        return 'alternative';
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
            'status' => 'alternative_executed',
            'enhanced' => true
        ];
    }

    /**
     * Alternative-specific method with different behavior.
     */
    public function getAlternativeFeatures(): array
    {
        return [
            'enhanced_processing' => true,
            'alternative_algorithm' => 'optimized',
            'compatibility_mode' => false
        ];
    }
}