# Flaphl Injection Element

**Dependency injection container abstractions pulled from Flaphl elements.**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-^8.2-blue.svg)](https://php.net)

## Installation

```bash
composer require flaphl/injection
```

## Features

- **PSR-11 Container compliance** - Full compatibility with PSR Container interfaces
- **Advanced dependency injection** - Constructor injection, method injection, and property injection
- **Service configuration** - Multiple configuration formats (PHP, XML, Directory)
- **Container compilation** - Optimized container dumping and preloading
- **Parameter management** - Environment variable integration and parameter bags
- **Contextual binding** - Advanced service resolution with context awareness
- **Comprehensive testing** - 354 tests with 860 assertions ensuring reliability

## Basic Usage

```php
use Flaphl\Element\Injection\ContainerBuilder;

// Create and configure container
$builder = new ContainerBuilder();

// Register services
$builder->register('service_id', MyService::class)
    ->addArgument('%parameter%')
    ->addMethodCall('setDependency', ['@dependency']);

// Set parameters
$builder->setParameter('parameter', 'value');

// Build container
$container = $builder->getContainer();

// Retrieve services
$service = $container->get('service_id');
```

## Configuration Formats

### PHP Configuration
```php
// config/services.php
return [
    'parameters' => [
        'app.name' => 'My Application'
    ],
    'services' => [
        'my_service' => [
            'class' => MyService::class,
            'arguments' => ['%app.name%']
        ]
    ]
];
```

### XML Configuration
```xml
<!-- config/services.xml -->
<container>
    <parameters>
        <parameter key="app.name">My Application</parameter>
    </parameters>
    <services>
        <service id="my_service" class="MyService">
            <argument>%app.name%</argument>
        </service>
    </services>
</container>
```

## Highlighted Features

- **Container compilation** for production optimization
- **Service tagging** for discovery and grouping
- **Contextual binding** for complex dependency resolution
- **Parameter validation** and type casting
- **Environment variable integration**
- **Deprecation handling** with migration paths

## License

This library is released under the [MIT License](LICENSE).
