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
 * Exception thrown when a parameter validation fails.
 * 
 * This exception extends InvalidArgumentException and provides specific
 * handling for parameter validation issues.
 * 
 * @package Flaphl\Element\Injection\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ParameterException extends \InvalidArgumentException implements ExceptionInterface
{
    /**
     * The parameter name that caused the exception.
     */
    protected string $parameterName;

    /**
     * The parameter value that caused the exception.
     */
    protected mixed $parameterValue;

    /**
     * Create a new parameter exception.
     *
     * @param string $parameterName Parameter name
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $parameterName, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->parameterName = $parameterName;
        $this->parameterValue = null;
        
        if (empty($message)) {
            $message = sprintf(
                'Parameter validation failed for parameter [%s]',
                $parameterName
            );
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create a new parameter exception with value.
     *
     * @param string $parameterName Parameter name
     * @param mixed $parameterValue Parameter value
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     * @return static
     */
    public static function withValue(string $parameterName, mixed $parameterValue, string $message = '', int $code = 0, ?\Throwable $previous = null): static
    {
        $exception = new static($parameterName, $message, $code, $previous);
        $exception->parameterValue = $parameterValue;
        return $exception;
    }

    /**
     * Get the parameter name that caused the exception.
     *
     * @return string
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * Get the parameter value that caused the exception.
     *
     * @return mixed
     */
    public function getParameterValue(): mixed
    {
        return $this->parameterValue;
    }
}