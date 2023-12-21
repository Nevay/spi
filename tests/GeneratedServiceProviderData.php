<?php declare(strict_types=1);
namespace Nevay\SPI;

final class GeneratedServiceProviderData {

    public const VERSION = 1;

    /** @var array<class-string, list<class-string>> */
    public static array $mappings = [];

    /**
     * @param class-string $service
     * @return list<class-string>
     */
    public static function providers(string $service): array {
        return self::$mappings[$service] ?? [];
    }
}
