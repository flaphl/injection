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

use Psr\Container\NotFoundExceptionInterface as PsrNotFoundExceptionInterface;

/**
 * Exception thrown when a service or parameter is not found.
 * 
 * This exception extends PHP's InvalidArgumentException and implements
 * both PSR-11 NotFoundExceptionInterface and the Injection element's
 * ExceptionInterface for consistent exception handling.
 * 
 * @package Flaphl\Element\Injection\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
class NotFoundException extends \InvalidArgumentException implements PsrNotFoundExceptionInterface, ExceptionInterface
{
}