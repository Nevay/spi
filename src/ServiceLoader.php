<?php declare(strict_types=1);
namespace Nevay\SPI;

use Iterator;
use IteratorAggregate;
use function class_exists;
use function in_array;

/**
 * Service provider loading facility.
 *
 * @template-covariant S of object service type
 * @implements IteratorAggregate<class-string, S>
 */
final class ServiceLoader implements IteratorAggregate {

    /** @var array<class-string, list<class-string>> */
    private static array $mappings = [];

    /** @var class-string<S> */
    private readonly string $service;
    /** @var list<class-string> */
    private array $providers;
    /** @var array<int, S|false> */
    private array $cache = [];

    /**
     * @param class-string<S> $service
     * @param list<class-string> $providers
     */
    private function __construct(string $service, array $providers) {
        $this->service = $service;
        $this->providers = $providers;
    }

    /**
     * Registers a service provider implementation for the given service type.
     *
     * Equivalent to `composer.json` configuration:
     * ```
     * "extra": {
     *     "spi": {
     *         $service: [
     *             $provider
     *         ]
     *     }
     * }
     * ```
     *
     * @template S_ of object service type
     * @template P_ of S_ service provider
     * @param class-string<S_> $service service to provide
     * @param class-string<P_> $provider provider class, must have a public
     *        zero-argument constructor
     */
    public static function register(string $service, string $provider): void {
        $providers = self::providers($service);
        if (in_array($provider, $providers, true)) {
            return;
        }

        self::$mappings[$service] = $providers;
        unset($providers);
        self::$mappings[$service][] = $provider;
    }

    /**
     * Lazy loads service providers for the given service.
     *
     * @template S_ of object service type
     * @param class-string<S_> $service service to load
     * @return ServiceLoader<S_> service loader for the given service
     */
    public static function load(string $service): ServiceLoader {
        return new self($service, self::providers($service));
    }

    public function getIterator(): Iterator {
        return new ServiceLoaderIterator($this->service, $this->providers, $this->cache);
    }

    /**
     * Reloads this service loader, clearing all cached instances.
     */
    public function reload(): void {
        unset($this->cache);
        $this->cache = [];
        $this->providers = self::providers($this->service);
    }

    /**
     * @param class-string $service
     * @return list<class-string>
     */
    private static function providers(string $service): array {
        if (!isset(self::$mappings[$service])
            && class_exists(GeneratedServiceProviderData::class)
            && GeneratedServiceProviderData::VERSION === 1) {
            return GeneratedServiceProviderData::providers($service);
        }

        return self::$mappings[$service] ?? [];
    }
}
