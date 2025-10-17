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
 * Preloader for generating optimized class loading lists.
 * 
 * Analyzes container dependencies to generate preload scripts
 * for PHP OPcache preloading functionality.
 * 
 * @package Flaphl\Element\Injection\Dumper
 * @author Jade Phyressi <jade@flaphl.com>
 */
class Preloader
{
    /**
     * @var array<string, bool> Already processed classes to avoid duplicates.
     */
    private array $processed = [];

    /**
     * @var array<string> List of classes to preload.
     */
    private array $preloadClasses = [];

    /**
     * @var array<string> List of files to preload.
     */
    private array $preloadFiles = [];

    /**
     * Generate a preload script for the container.
     * 
     * @param ContainerBuilder $container The container to analyze.
     * @param array<string, mixed> $options Preloader options.
     * @return string The preload script content.
     */
    public function generatePreloadScript(ContainerBuilder $container, array $options = []): string
    {
        $options = array_merge([
            'skip_files_with_syntax_errors' => true,
            'exclude_patterns' => [
                '*/tests/*',
                '*/test/*',
                '*/Tests/*',
                '*/Test/*',
                '*/vendor/*/tests/*',
                '*/vendor/*/test/*',
            ],
            'include_dev_dependencies' => false,
            'memory_limit' => '256M',
        ], $options);

        $this->reset();
        $this->analyzeContainer($container);

        return $this->generateScript($options);
    }

    /**
     * Get the list of classes that should be preloaded.
     * 
     * @param ContainerBuilder $container The container to analyze.
     * @return array<string> List of class names.
     */
    public function getPreloadClasses(ContainerBuilder $container): array
    {
        $this->reset();
        $this->analyzeContainer($container);

        return $this->preloadClasses;
    }

    /**
     * Get the list of files that should be preloaded.
     * 
     * @param ContainerBuilder $container The container to analyze.
     * @return array<string> List of file paths.
     */
    public function getPreloadFiles(ContainerBuilder $container): array
    {
        $this->reset();
        $this->analyzeContainer($container);

        return $this->preloadFiles;
    }

    /**
     * Reset the internal state.
     */
    protected function reset(): void
    {
        $this->processed = [];
        $this->preloadClasses = [];
        $this->preloadFiles = [];
    }

    /**
     * Analyze the container for preloadable classes.
     * 
     * @param ContainerBuilder $container The container to analyze.
     */
    protected function analyzeContainer(ContainerBuilder $container): void
    {
        // Add the container class itself
        $this->addClass('Flaphl\\Element\\Injection\\Container');
        $this->addClass('Flaphl\\Element\\Injection\\ContainerInterface');

        // Analyze service definitions
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            
            if (class_exists($class)) {
                $this->analyzeClass($class);
            }
        }

        // Add common framework classes
        $this->addFrameworkClasses();
    }

    /**
     * Analyze a specific class for dependencies.
     * 
     * @param string $className The class to analyze.
     */
    protected function analyzeClass(string $className): void
    {
        if (isset($this->processed[$className])) {
            return;
        }

        $this->processed[$className] = true;

        try {
            $reflection = new \ReflectionClass($className);
            
            // Add the class itself
            $this->addClass($className);

            // Add parent class
            $parent = $reflection->getParentClass();
            if ($parent) {
                $this->analyzeClass($parent->getName());
            }

            // Add interfaces
            foreach ($reflection->getInterfaces() as $interface) {
                $this->addClass($interface->getName());
            }

            // Add traits
            foreach ($reflection->getTraits() as $trait) {
                $this->addClass($trait->getName());
            }

            // Analyze constructor dependencies
            $constructor = $reflection->getConstructor();
            if ($constructor) {
                $this->analyzeMethod($constructor);
            }

            // Analyze method dependencies (public methods only for performance)
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if (!$method->isStatic() && $method->getDeclaringClass()->getName() === $className) {
                    $this->analyzeMethod($method);
                }
            }

        } catch (\ReflectionException $e) {
            // Skip classes that can't be analyzed
        }
    }

    /**
     * Analyze a method for type dependencies.
     * 
     * @param \ReflectionMethod $method The method to analyze.
     */
    protected function analyzeMethod(\ReflectionMethod $method): void
    {
        try {
            // Analyze parameters
            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                
                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    if (class_exists($typeName) || interface_exists($typeName)) {
                        $this->analyzeClass($typeName);
                    }
                } elseif ($type instanceof \ReflectionUnionType) {
                    foreach ($type->getTypes() as $unionType) {
                        if ($unionType instanceof \ReflectionNamedType && !$unionType->isBuiltin()) {
                            $typeName = $unionType->getName();
                            if (class_exists($typeName) || interface_exists($typeName)) {
                                $this->analyzeClass($typeName);
                            }
                        }
                    }
                }
            }

            // Analyze return type
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionNamedType && !$returnType->isBuiltin()) {
                $typeName = $returnType->getName();
                if (class_exists($typeName) || interface_exists($typeName)) {
                    $this->analyzeClass($typeName);
                }
            }

        } catch (\ReflectionException $e) {
            // Skip methods that can't be analyzed
        }
    }

    /**
     * Add a class to the preload list.
     * 
     * @param string $className The class name to add.
     */
    protected function addClass(string $className): void
    {
        if (in_array($className, $this->preloadClasses, true)) {
            return;
        }

        try {
            $reflection = new \ReflectionClass($className);
            $file = $reflection->getFileName();
            
            if ($file && is_readable($file)) {
                $this->preloadClasses[] = $className;
                $this->preloadFiles[] = $file;
            }
        } catch (\ReflectionException $e) {
            // Skip classes without files (built-in classes)
        }
    }

    /**
     * Add common framework classes that are likely to be used.
     */
    protected function addFrameworkClasses(): void
    {
        $frameworkClasses = [
            'Flaphl\\Element\\Injection\\Exception\\ExceptionInterface',
            'Flaphl\\Element\\Injection\\Exception\\ContainerException',
            'Flaphl\\Element\\Injection\\Exception\\NotFoundException',
            'Flaphl\\Element\\Injection\\BagParameters\\ParameterBagInterface',
            'Flaphl\\Element\\Injection\\BagParameters\\ContainerBag',
            'Flaphl\\Element\\Injection\\Parameter',
        ];

        foreach ($frameworkClasses as $class) {
            if (class_exists($class) || interface_exists($class)) {
                $this->addClass($class);
            }
        }
    }

    /**
     * Generate the actual preload script.
     * 
     * @param array<string, mixed> $options Script generation options.
     * @return string The preload script content.
     */
    protected function generateScript(array $options): string
    {
        $files = array_unique($this->preloadFiles);
        
        // Filter files based on exclude patterns
        if (!empty($options['exclude_patterns'])) {
            $files = array_filter($files, function (string $file) use ($options) {
                foreach ($options['exclude_patterns'] as $pattern) {
                    if (fnmatch($pattern, $file)) {
                        return false;
                    }
                }
                return true;
            });
        }

        $script = "<?php\n\n";
        $script .= "/**\n";
        $script .= " * OPcache preload script generated by Flaphl Dependency Injection.\n";
        $script .= " * \n";
        $script .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $script .= " * Files to preload: " . count($files) . "\n";
        $script .= " * Classes analyzed: " . count($this->preloadClasses) . "\n";
        $script .= " */\n\n";

        if (!empty($options['memory_limit'])) {
            $script .= "ini_set('memory_limit', '{$options['memory_limit']}');\n\n";
        }

        $script .= "if (!function_exists('opcache_compile_file')) {\n";
        $script .= "    return;\n";
        $script .= "}\n\n";

        $script .= "\$files = [\n";
        
        foreach ($files as $file) {
            $script .= "    " . var_export($file, true) . ",\n";
        }
        
        $script .= "];\n\n";

        $script .= "foreach (\$files as \$file) {\n";
        $script .= "    if (is_file(\$file) && is_readable(\$file)) {\n";
        
        if ($options['skip_files_with_syntax_errors']) {
            $script .= "        // Check for syntax errors before preloading\n";
            $script .= "        \$output = [];\n";
            $script .= "        \$return = 0;\n";
            $script .= "        exec('php -l ' . escapeshellarg(\$file) . ' 2>&1', \$output, \$return);\n";
            $script .= "        \n";
            $script .= "        if (\$return === 0) {\n";
            $script .= "            opcache_compile_file(\$file);\n";
            $script .= "        }\n";
        } else {
            $script .= "        opcache_compile_file(\$file);\n";
        }
        
        $script .= "    }\n";
        $script .= "}\n";

        return $script;
    }
}
