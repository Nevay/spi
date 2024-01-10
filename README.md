# Service Provider Interface

Service provider loading facility, inspired by Javas `ServiceLoader`.

## Install

```shell
composer require tbachert/spi
```

## Usage

### Registering service providers

Service provider implementations must provide a public zero-arguments constructor.

#### Registering via composer.json `extra.spi`

```shell
composer config --json --merge extra.spi.Namespace\\Service '["Namespace\\Implementation"]'
```

#### Registering via php

```php
ServiceLoader::register('Namespace\Service', 'Namespace\Implementation');
```

### Application authors

Make sure to allow the composer plugin to be able to load service providers.

```shell
composer config allow-plugins.tbachert/spi true
```

### Loading service providers

```php
foreach (ServiceLoader::load('Namespace\Service') as $provider) {
    // ...
}
```

#### Handling invalid service configurations

```php
$loader = ServiceLoader::load('Namespace\Service');
for ($it = $loader->getIterator(); $it->valid(); $it->next()) {
    try {
        $provider = $it->current();
    } catch (ServiceConfigurationError) {}
}
```
