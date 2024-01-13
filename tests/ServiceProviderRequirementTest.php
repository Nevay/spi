<?php declare(strict_types=1);
namespace Nevay\SPI;

use Nevay\SPI\ServiceProviderDependency\ExtensionDependency;
use Nevay\SPI\ServiceProviderDependency\PackageDependency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PackageDependency::class)]
#[CoversClass(ExtensionDependency::class)]
final class ServiceProviderRequirementTest extends TestCase {

    public function testVersionIsSatisfied(): void {
        $this->assertTrue((new PackageDependency('phpunit/phpunit', '^10'))->isSatisfied());
        $this->assertFalse((new PackageDependency('phpunit/phpunit', '^9'))->isSatisfied());
        $this->assertFalse((new PackageDependency('not/available', '*'))->isSatisfied());
    }

    public function testExtensionIsSatisfied(): void {
        $this->assertTrue((new ExtensionDependency('Core', '^8'))->isSatisfied());
        $this->assertFalse((new ExtensionDependency('Core', '^7'))->isSatisfied());
        $this->assertFalse((new ExtensionDependency('not_available', '*'))->isSatisfied());
    }
}
