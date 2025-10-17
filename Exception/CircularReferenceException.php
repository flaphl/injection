<?php

/**
 * This file is part of the Flaphl package.
 *
 * (c) Jade Phyressi <jade@flaphl.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Exception;

/**
 * Exception thrown when a circular dependency is detected.
 * 
 * This exception extends ContainerException and provides specific
 * handling for circular dependency issues during service resolution.
 * 
 * @package Flaphl\Element\Injection\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
class CircularReferenceException extends ContainerException
{
    /**
     * The service dependency path that caused the circular reference.
     */
    protected array $path;

    /**
     * Create a new circular reference exception.
     *
     * @param array $path Service dependency path
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(array $path, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->path = $path;
        
        if (empty($message)) {
            $message = sprintf(
                'Circular reference detected for service dependency: %s',
                implode(' -> ', $path)
            );
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the dependency path that caused the circular reference.
     *
     * @return array
     */
    public function getPath(): array
    {
        return $this->path;
    }
}