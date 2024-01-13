<?php declare(strict_types=1);
namespace Nevay\SPI\Fixtures;

use Nevay\SPI\ServiceProviderDependency\PackageDependency;

#[PackageDependency('not/available', '*')]
#[PackageDependency('phpunit/phpunit', '^9')]
final class UnmetDependencyImplementation implements Service {

}
