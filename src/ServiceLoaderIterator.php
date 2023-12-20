<?php declare(strict_types=1);
namespace Nevay\SPI;

use Iterator;
use Throwable;
use function count;
use function is_subclass_of;
use function sprintf;

/**
 * @template S
 * @implements Iterator<class-string, S>
 *
 * @internal
 */
final class ServiceLoaderIterator implements Iterator {

    private readonly string $service;
    private readonly array $providers;
    private array $cache;

    private int $index = 0;

    /**
     * @param class-string<S> $service
     * @param list<class-string> $providers
     * @param list<S|false> $cache
     */
    public function __construct(string $service, array $providers, array &$cache) {
        $this->service = $service;
        $this->providers = $providers;
        $this->cache = &$cache;
    }

    public function current(): ?object {
        $index = $this->index;
        if ($instance = $this->cache[$index] ?? null) {
            return $instance;
        }
        if (($class = $this->providers[$index] ?? null) === null) {
            return null;
        }

        $this->cache[$index] = false;

        if (!is_subclass_of($class, $this->service)) {
            throw new ServiceConfigurationError(sprintf(
                'Invalid service provider, expected implementation of "%s", got "%s"',
                $this->service, $class,
            ));
        }

        try {
            return $this->cache[$index] = new $class();
        } catch (Throwable $e) {
            throw new ServiceConfigurationError(sprintf(
                'Invalid service provider, failed to instantiate "%s" provider "%s": %s',
                $this->service, $class, $e->getMessage(),
            ), previous: $e);
        }
    }

    public function next(): void {
        $this->index++;
        $this->skipInvalid();
    }

    public function key(): ?string {
        return $this->providers[$this->index] ?? null;
    }

    public function valid(): bool {
        return $this->index < count($this->providers);
    }

    public function rewind(): void {
        $this->index = 0;
        $this->skipInvalid();
    }

    private function skipInvalid(): void {
        while (($this->cache[$this->index] ?? null) === false) {
            $this->index++;
        }
    }
}
