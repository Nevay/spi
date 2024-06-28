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
composer config --json --merge extra.spi.Example\\Service '["Example\\Implementation"]'
```

#### Registering via php

```php
ServiceLoader::register(Example\Service::class, Example\Implementation::class);
```

###### Converting `ServiceLoader::register()` calls to precompiled map

`ServiceLoader::register()` calls can be converted to a precompiled map by setting `extra.spi-config.autoload-files` to
- `true` to process all `autoload.files` (should be used iff `autoload.files` is used exclusively for service
provider registration),
- or a list of files that register service providers.

```shell
composer config --json extra.spi-config.autoload-files true
```

###### Removing obsolete entries from `autoload.files`

By default, `extra.spi-config.autoload-files` files that register service providers are removed from
`autoload.files`. This behavior can be configured by setting `extra.spi-config.prune-autoload-files` to
- `true` to remove all `exra.spi-config.autoload-files` files from `autoload.files`,
- `false` to keep all `autoload.files` entries,
- or a list of files that should be removed from `autoload.files`.

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
