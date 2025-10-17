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
 * Service with constructor parameters for testing parameter injection.
 * 
 * Demonstrates parameter-based dependency injection patterns and
 * configuration management within Flaphl applications.
 */
class ParameterizedService
{
    public function __construct(
        private string $stringParam,
        private int $intParam = 0,
        private array $arrayParam = []
    ) {
    }

    /**
     * Get the string parameter.
     */
    public function getStringParam(): string
    {
        return $this->stringParam;
    }

    /**
     * Get the integer parameter.
     */
    public function getIntParam(): int
    {
        return $this->intParam;
    }

    /**
     * Get the array parameter.
     */
    public function getArrayParam(): array
    {
        return $this->arrayParam;
    }

    /**
     * Get all parameters as a combined array.
     */
    public function getAllParameters(): array
    {
        return [
            'string' => $this->stringParam,
            'int' => $this->intParam,
            'array' => $this->arrayParam
        ];
    }

    /**
     * Process parameters with optional transformation.
     */
    public function processParameters(?callable $transform = null): array
    {
        $params = $this->getAllParameters();
        
        if ($transform !== null) {
            return $transform($params);
        }
        
        return [
            'processed' => true,
            'parameters' => $params,
            'timestamp' => time()
        ];
    }

    /**
     * Validate parameter integrity.
     */
    public function validateParameters(): bool
    {
        return !empty($this->stringParam) && 
               $this->intParam >= 0 && 
               is_array($this->arrayParam);
    }
}