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
 * Exception thrown when a service definition is invalid.
 * 
 * This exception extends ContainerException and provides specific
 * handling for service definition errors during container building.
 * 
 * @package Flaphl\Element\Injection\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ServiceDefinitionException extends ContainerException
{
    /**
     * The service identifier that caused the exception.
     */
    protected string $serviceId;

    /**
     * Create a new service definition exception.
     *
     * @param string $serviceId Service identifier
     * @param string $message Exception message
     * @param int $code Exception code
     * @param \Throwable|null $previous Previous exception
     */
    public function __construct(string $serviceId, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->serviceId = $serviceId;
        
        if (empty($message)) {
            $message = sprintf(
                'Service definition error for service [%s]',
                $serviceId
            );
        }
        
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the service identifier that caused the exception.
     *
     * @return string
     */
    public function getServiceId(): string
    {
        return $this->serviceId;
    }
}