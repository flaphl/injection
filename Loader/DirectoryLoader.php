<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Loader;

use Flaphl\Element\Injection\ContainerBuilder;
use Flaphl\Element\Injection\Exception\ContainerException;

/**
 * Directory loader for loading multiple configuration files.
 * 
 * Recursively loads all configuration files from a directory
 * with filtering and ordering capabilities.
 * 
 * @package Flaphl\Element\Injection\Loader
 * @author Jade Phyressi <jade@flaphl.com>
 */
class DirectoryLoader
{
    /**
     * @var ContainerBuilder The container builder to configure.
     */
    protected ContainerBuilder $container;

    /**
     * @var array<string, FileLoader> File loaders by extension.
     */
    protected array $loaders = [];

    /**
     * @var UndefinedExtensionHandler Handler for unsupported extensions.
     */
    protected UndefinedExtensionHandler $undefinedHandler;

    /**
     * Create a new directory loader.
     * 
     * @param ContainerBuilder $container The container builder to configure.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->undefinedHandler = new UndefinedExtensionHandler();
        
        // Register default loaders
        $this->registerLoader('php', new PhpFileLoader($container));
        $this->registerLoader('xml', new XmlFileLoader($container));
    }

    /**
     * Register a file loader for a specific extension.
     * 
     * @param string $extension The file extension.
     * @param FileLoader $loader The loader instance.
     */
    public function registerLoader(string $extension, FileLoader $loader): void
    {
        $this->loaders[$extension] = $loader;
    }

    /**
     * Load all configuration files from a directory.
     * 
     * @param string $directory The directory path to load from.
     * @param array<string, mixed> $options Loading options.
     */
    public function load(string $directory, array $options = []): void
    {
        $options = array_merge([
            'recursive' => true,
            'pattern' => null,
            'exclude_patterns' => [
                '*/.*',        // Hidden files
                '*/vendor/*',  // Vendor directory
                '*/node_modules/*',
                '*/Tests/*',   // Test directories
                '*/tests/*',
                '*/Test/*',
                '*/test/*',
            ],
            'extensions' => ['php', 'xml'],
            'order_by' => 'name', // 'name', 'date', 'size'
            'sort_direction' => 'asc', // 'asc', 'desc'
            'ignore_errors' => false,
            'max_depth' => 10,
        ], $options);

        if (!is_dir($directory)) {
            if ($options['ignore_errors']) {
                return;
            }
            throw new ContainerException(sprintf('Directory "%s" not found.', $directory));
        }

        if (!is_readable($directory)) {
            throw new ContainerException(sprintf('Directory "%s" is not readable.', $directory));
        }

        $files = $this->findConfigurationFiles($directory, $options);
        $this->loadFiles($files, $options);
    }

    /**
     * Find configuration files in the directory.
     * 
     * @param string $directory The directory to search.
     * @param array<string, mixed> $options Search options.
     * @return array<string> List of file paths.
     */
    protected function findConfigurationFiles(string $directory, array $options): array
    {
        $files = [];
        $iterator = $this->createFileIterator($directory, $options);

        foreach ($iterator as $file) {
            if ($file->isFile() && $this->shouldLoadFile($file, $options)) {
                $files[] = $file->getPathname();
            }
        }

        return $this->sortFiles($files, $options);
    }

    /**
     * Create a file iterator for the directory.
     * 
     * @param string $directory The directory to iterate.
     * @param array<string, mixed> $options Iterator options.
     * @return \Iterator The file iterator.
     */
    protected function createFileIterator(string $directory, array $options): \Iterator
    {
        if ($options['recursive']) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            // Limit recursion depth
            if ($options['max_depth'] > 0) {
                $iterator->setMaxDepth($options['max_depth']);
            }

            return $iterator;
        } else {
            return new \DirectoryIterator($directory);
        }
    }

    /**
     * Check if a file should be loaded.
     * 
     * @param \SplFileInfo $file The file to check.
     * @param array<string, mixed> $options Loading options.
     * @return bool True if the file should be loaded.
     */
    protected function shouldLoadFile(\SplFileInfo $file, array $options): bool
    {
        $pathname = $file->getPathname();
        $extension = $file->getExtension();

        // Check extension filter
        if (!empty($options['extensions']) && !in_array($extension, $options['extensions'], true)) {
            return false;
        }

        // Check include pattern
        if ($options['pattern'] && !fnmatch($options['pattern'], $pathname)) {
            return false;
        }

        // Check exclude patterns
        foreach ($options['exclude_patterns'] as $pattern) {
            if (fnmatch($pattern, $pathname)) {
                return false;
            }
        }

        // Check if we have a loader for this extension
        if (!isset($this->loaders[$extension]) && !$this->undefinedHandler->canHandle($extension)) {
            return false;
        }

        return true;
    }

    /**
     * Sort files according to options.
     * 
     * @param array<string> $files List of file paths.
     * @param array<string, mixed> $options Sorting options.
     * @return array<string> Sorted file paths.
     */
    protected function sortFiles(array $files, array $options): array
    {
        $orderBy = $options['order_by'];
        $direction = $options['sort_direction'];

        usort($files, function (string $a, string $b) use ($orderBy, $direction): int {
            $result = match ($orderBy) {
                'name' => strcasecmp(basename($a), basename($b)),
                'date' => filemtime($a) <=> filemtime($b),
                'size' => filesize($a) <=> filesize($b),
                default => strcasecmp($a, $b),
            };

            return $direction === 'desc' ? -$result : $result;
        });

        return $files;
    }

    /**
     * Load multiple files.
     * 
     * @param array<string> $files List of file paths to load.
     * @param array<string, mixed> $options Loading options.
     */
    protected function loadFiles(array $files, array $options): void
    {
        foreach ($files as $file) {
            try {
                $this->loadFile($file, $options);
            } catch (\Exception $e) {
                if (!$options['ignore_errors']) {
                    throw new ContainerException(
                        sprintf('Error loading file "%s": %s', $file, $e->getMessage()),
                        0,
                        $e
                    );
                }
            }
        }
    }

    /**
     * Load a single file with the appropriate loader.
     * 
     * @param string $file The file path to load.
     * @param array<string, mixed> $options Loading options.
     */
    protected function loadFile(string $file, array $options): void
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        
        if (isset($this->loaders[$extension])) {
            $this->loaders[$extension]->load($file, $options);
        } elseif ($this->undefinedHandler->canHandle($extension)) {
            $this->undefinedHandler->handle($file, $this->container, $options);
        } else {
            throw new ContainerException(sprintf(
                'No loader available for file extension "%s" in file "%s".',
                $extension,
                $file
            ));
        }
    }

    /**
     * Get statistics about the loaded files.
     * 
     * @param string $directory The directory that was loaded.
     * @param array<string, mixed> $options Loading options.
     * @return array<string, mixed> Loading statistics.
     */
    public function getLoadStatistics(string $directory, array $options = []): array
    {
        $options = array_merge([
            'recursive' => true,
            'pattern' => null,
            'exclude_patterns' => [],
            'extensions' => ['php', 'xml'],
        ], $options);

        if (!is_dir($directory)) {
            return ['error' => 'Directory not found'];
        }

        $files = $this->findConfigurationFiles($directory, $options);
        $stats = [
            'directory' => $directory,
            'total_files' => count($files),
            'files_by_extension' => [],
            'total_size' => 0,
            'files' => [],
        ];

        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            $size = filesize($file);

            if (!isset($stats['files_by_extension'][$extension])) {
                $stats['files_by_extension'][$extension] = 0;
            }
            $stats['files_by_extension'][$extension]++;
            $stats['total_size'] += $size;

            $stats['files'][] = [
                'path' => $file,
                'extension' => $extension,
                'size' => $size,
                'modified' => filemtime($file),
            ];
        }

        return $stats;
    }
}
