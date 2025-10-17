<?php

/**
 * This file is part of the Flaphl package.
 * 
 * (c) Jade Phyressi <jade@flaphl.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flaphl\Element\Injection\Loader\Configurator;

use Flaphl\Element\Injection\ContainerBuilder;

/**
 * Container configurator for comprehensive container configuration.
 * 
 * Provides a unified interface for configuring all aspects of a container
 * including services, parameters, imports, and extensions.
 * 
 * @package Flaphl\Element\Injection\Loader\Configurator
 * @author Jade Phyressi <jade@flaphl.com>
 */
class ContainerConfigurator
{
    /**
     * @var ContainerBuilder The container builder.
     */
    protected ContainerBuilder $container;

    /**
     * @var ServiceConfigurator Service configurator instance.
     */
    protected ServiceConfigurator $serviceConfigurator;

    /**
     * @var ParameterConfigurator Parameter configurator instance.
     */
    protected ParameterConfigurator $parameterConfigurator;

    /**
     * @var array<string> List of imported files.
     */
    protected array $imports = [];

    /**
     * @var array<string, mixed> Extension configurations.
     */
    protected array $extensions = [];

    /**
     * Create a new container configurator.
     * 
     * @param ContainerBuilder $container The container builder.
     */
    public function __construct(ContainerBuilder $container)
    {
        $this->container = $container;
        $this->serviceConfigurator = new ServiceConfigurator($container);
        $this->parameterConfigurator = new ParameterConfigurator($container);
    }

    /**
     * Get the service configurator.
     * 
     * @return ServiceConfigurator
     */
    public function services(): ServiceConfigurator
    {
        return $this->serviceConfigurator;
    }

    /**
     * Get the parameter configurator.
     * 
     * @return ParameterConfigurator
     */
    public function parameters(): ParameterConfigurator
    {
        return $this->parameterConfigurator;
    }

    /**
     * Import configuration from another file.
     * 
     * @param string $file The file to import.
     * @param array<string, mixed> $options Import options.
     * @return static
     */
    public function import(string $file, array $options = []): static
    {
        $this->imports[] = array_merge(['resource' => $file], $options);
        return $this;
    }

    /**
     * Import multiple configuration files.
     * 
     * @param array<string> $files The files to import.
     * @param array<string, mixed> $options Import options.
     * @return static
     */
    public function imports(array $files, array $options = []): static
    {
        foreach ($files as $file) {
            $this->import($file, $options);
        }
        return $this;
    }

    /**
     * Configure an extension.
     * 
     * @param string $name The extension name.
     * @param array<string, mixed> $config The extension configuration.
     * @return static
     */
    public function extension(string $name, array $config = []): static
    {
        $this->extensions[$name] = $config;
        return $this;
    }

    /**
     * When environment matches, execute callback.
     * 
     * @param string|array<string> $environments The environment(s) to match.
     * @param callable $callback The callback to execute.
     * @return static
     */
    public function when(string|array $environments, callable $callback): static
    {
        $currentEnv = $_ENV['APP_ENV'] ?? 'prod';
        $environments = (array) $environments;

        if (in_array($currentEnv, $environments, true)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * When environment is development, execute callback.
     * 
     * @param callable $callback The callback to execute.
     * @return static
     */
    public function whenDev(callable $callback): static
    {
        return $this->when(['dev', 'development'], $callback);
    }

    /**
     * When environment is production, execute callback.
     * 
     * @param callable $callback The callback to execute.
     * @return static
     */
    public function whenProd(callable $callback): static
    {
        return $this->when(['prod', 'production'], $callback);
    }

    /**
     * When environment is test, execute callback.
     * 
     * @param callable $callback The callback to execute.
     * @return static
     */
    public function whenTest(callable $callback): static
    {
        return $this->when(['test', 'testing'], $callback);
    }

    /**
     * When not in environment, execute callback.
     * 
     * @param string|array<string> $environments The environment(s) to exclude.
     * @param callable $callback The callback to execute.
     * @return static
     */
    public function whenNot(string|array $environments, callable $callback): static
    {
        $currentEnv = $_ENV['APP_ENV'] ?? 'prod';
        $environments = (array) $environments;

        if (!in_array($currentEnv, $environments, true)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Set container build options.
     * 
     * @param array<string, mixed> $options Build options.
     * @return static
     */
    public function buildOptions(array $options): static
    {
        $this->container->setBuildConfig($options);
        return $this;
    }

    /**
     * Enable compilation.
     * 
     * @param bool $compile Whether to enable compilation.
     * @return static
     */
    public function compile(bool $compile = true): static
    {
        $this->container->setCompile($compile);
        return $this;
    }

    /**
     * Enable debug mode.
     * 
     * @param bool $debug Whether to enable debug mode.
     * @return static
     */
    public function debug(bool $debug = true): static
    {
        $this->container->setDebug($debug);
        return $this;
    }

    /**
     * Add a compiler pass.
     * 
     * @param callable $pass The compiler pass.
     * @param int $priority The priority.
     * @return static
     */
    public function compilerPass(callable $pass, int $priority = 0): static
    {
        $this->container->addCompilerPass($pass, $priority);
        return $this;
    }

    /**
     * Load all configurations and build the container.
     * 
     * @return ContainerBuilder The configured container.
     */
    public function load(): ContainerBuilder
    {
        // Load parameters first
        $this->parameterConfigurator->load();

        // Load services
        $this->serviceConfigurator->load();

        // Process imports (would need to be implemented with actual file loaders)
        foreach ($this->imports as $import) {
            // This would be handled by the file loaders in a real implementation
        }

        return $this->container;
    }

    /**
     * Export the complete configuration as an array.
     * 
     * @return array<string, mixed> The configuration array.
     */
    public function export(): array
    {
        $config = [];

        // Export imports
        if (!empty($this->imports)) {
            $config['imports'] = $this->imports;
        }

        // Export parameters
        $parameterConfig = $this->parameterConfigurator->export();
        if (!empty($parameterConfig['parameters'])) {
            $config['parameters'] = $parameterConfig['parameters'];
        }

        // Export services
        $serviceConfig = $this->serviceConfigurator->export();
        if (!empty($serviceConfig['services'])) {
            $config['services'] = $serviceConfig['services'];
        }

        // Export extensions
        if (!empty($this->extensions)) {
            $config['extensions'] = $this->extensions;
        }

        return $config;
    }

    /**
     * Create a quick service configuration.
     * 
     * @param string $id The service ID.
     * @param string|null $class The service class.
     * @return ServiceConfigurator
     */
    public function service(string $id, ?string $class = null): ServiceConfigurator
    {
        return $this->serviceConfigurator->service($id, $class);
    }

    /**
     * Quick parameter setting.
     * 
     * @param string $name The parameter name.
     * @param mixed $value The parameter value.
     * @return static
     */
    public function parameter(string $name, mixed $value): static
    {
        $this->parameterConfigurator->set($name, $value);
        return $this;
    }

    /**
     * Load parameters from environment variables.
     * 
     * @param string $prefix The environment variable prefix.
     * @param string|null $parameterPrefix The parameter name prefix.
     * @return static
     */
    public function envParameters(string $prefix, ?string $parameterPrefix = null): static
    {
        $this->parameterConfigurator->envPrefix($prefix, $parameterPrefix);
        return $this;
    }

    /**
     * Load parameters from a .env file.
     * 
     * @param string $file The .env file path.
     * @param string|null $prefix Parameter name prefix.
     * @return static
     */
    public function envFile(string $file, ?string $prefix = null): static
    {
        $this->parameterConfigurator->envFile($file, $prefix);
        return $this;
    }

    /**
     * Get the underlying container builder.
     * 
     * @return ContainerBuilder
     */
    public function getContainer(): ContainerBuilder
    {
        return $this->container;
    }
}