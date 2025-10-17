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

use Psr\Container\ContainerExceptionInterface as PsrContainerExceptionInterface;

/**
 * Exception thrown when the container encounters an error.
 * 
 * This exception extends PHP's RuntimeException and implements
 * both PSR-11 ContainerExceptionInterface and the Injection element's
 * ExceptionInterface for consistent exception handling.
 * 
 * @package Flaphl\Element\Injection\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerException extends \RuntimeException implements PsrContainerExceptionInterface, ExceptionInterface
{
}