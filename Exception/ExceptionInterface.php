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

use Throwable;

/**
 * Base exception interface for all Injection element exceptions.
 * 
 * This interface should be implemented by all exceptions thrown
 * within the Flaphl Injection element to provide a common contract
 * for exception handling.
 * 
 * @package Flaphl\Element\Injection\Exception
 * @author Jade Phyressi <jade@flaphl.com>
 */
interface ExceptionInterface extends Throwable
{
}