<?php declare(strict_types=1);
namespace Nevay\SPI;

use Iterator;
use IteratorAggregate;
use function class_exists;
use function in_array;

/**
 * Service provider loading facility.
 *
 * @template S service type
 * @implements IteratorAggregate<class-string, S>
 *
 * @see https://docs.oracle.com/javase/8/docs/api/java/util/ServiceLoader.html
 */
final class ServiceLoader implements IteratorAggregate {

    /** @var array<class-string, list<class-string>> */
    private static array $mappings = [];

    /** @var class-string<S> */
    private readonly string $service;
    /** @var list<class-string>|null */
    private ?array $providers = null;
    /** @var list<S> */
    private array $cache = [];

    /**
     * @param class-string<S> $service
     */
    private function __construct(string $service) {
        $this->service = $service;
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
     * @template S_ service type
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

        self::$mappings[$service] ??= $providers;
        self::$mappings[$service][] = $provider;
    }

    /**
     * Lazy loads service providers for the given service.
     *
     * @template S_ service type
     * @param class-string<S_> $service service to load
     * @return ServiceLoader<S_> service loader for the given service
     */
    public static function load(string $service): ServiceLoader {
        return new self($service);
    }

    public function getIterator(): Iterator {
        $this->providers ??= self::providers($this->service);
        return new ServiceLoaderIterator($this->service, $this->providers, $this->cache);
    }

    /**
     * Reloads this service loader, clearing all cached instances.
     */
    public function reload(): void {
        unset($this->cache);
        $this->cache = [];
        $this->providers = null;
    }

    private static function providers(string $type): array {
        $providers = self::$mappings[$type] ?? [];
        if (!$providers && class_exists(GeneratedServiceProviderData::class) && GeneratedServiceProviderData::VERSION === 1) {
            $providers = GeneratedServiceProviderData::providers($type);
        }

        return $providers;
    }
}
