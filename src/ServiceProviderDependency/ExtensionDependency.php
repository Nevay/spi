<?php declare(strict_types=1);
namespace Nevay\SPI\ServiceProviderDependency;

use Attribute;
use Composer\Semver\VersionParser;
use Nevay\SPI\ServiceProviderRequirement;
use function phpversion;

/**
 * Specifies extensions required by a service provider.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class ExtensionDependency implements ServiceProviderRequirement {

    public function __construct(
        private readonly string $extension,
        private readonly string $version,
    ) {}

    public function isSatisfied(): bool {
        if (($version = phpversion($this->extension)) === false) {
            return false;
        }

        $parser = new VersionParser();
        $constraint = $parser->parseConstraints($this->version);
        $provided = $parser->parseConstraints($version);

        return $provided->matches($constraint);
    }
}
