<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Dumper;

use Flaphl\Element\Injection\ContainerBuilder;

/**
 * Interface for dumping container configurations.
 * 
 * Dumpers are responsible for exporting compiled container configurations
 * to various formats for caching and performance optimization.
 * 
 * @package Flaphl\Element\Injection\Dumper
 * @author Jade Phyressi <jade@flaphl.com>
 */
interface DumperInterface
{
    /**
     * Dump the container configuration to a string.
     * 
     * @param ContainerBuilder $container The container to dump.
     * @param array<string, mixed> $options Dumper-specific options.
     * @return string The dumped container configuration.
     */
    public function dump(ContainerBuilder $container, array $options = []): string;

    /**
     * Check if dumping is supported for the given container.
     * 
     * @param ContainerBuilder $container The container to check.
     * @return bool True if dumping is supported.
     */
    public function isSupported(ContainerBuilder $container): bool;

    /**
     * Get the file extension for dumped files.
     * 
     * @return string The file extension without dot (e.g., 'php', 'xml').
     */
    public function getFileExtension(): string;

    /**
     * Get the MIME type for dumped content.
     * 
     * @return string The MIME type.
     */
    public function getMimeType(): string;
}
