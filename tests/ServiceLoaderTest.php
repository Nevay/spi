<?php declare(strict_types=1);
namespace Nevay\SPI;

use Nevay\SPI\Fixtures\Implementation1;
use Nevay\SPI\Fixtures\Implementation2;
use Nevay\SPI\Fixtures\ImplementationForUnavailableService;
use Nevay\SPI\Fixtures\RequiredArgument;
use Nevay\SPI\Fixtures\Service;
use Nevay\SPI\Fixtures\ThrowingConstructor;
use Nevay\SPI\Fixtures\UnavailableImplementation;
use Nevay\SPI\Fixtures\UnavailableService;
use Nevay\SPI\Fixtures\UnmetDependencyImplementation;
use Nevay\SPI\Fixtures\UnrelatedImplementation;
use Nevay\SPI\Fixtures\UnrelatedService;
use Nevay\SPI\ServiceProviderDependency\PackageDependency;
use PHPUnit\Framework\Attributes\BackupStaticProperties;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use function iterator_to_array;

#[CoversClass(ServiceLoader::class)]
#[CoversClass(ServiceLoaderIterator::class)]
#[CoversClass(ServiceConfigurationError::class)]
#[CoversClass(PackageDependency::class)]
#[BackupStaticProperties(true)]
final class ServiceLoaderTest extends TestCase {

    public function testServiceLoaderEmpty(): void {
        $loader = ServiceLoader::load(Service::class);
        $this->assertCount(0, $loader);
        $this->assertFalse($loader->getIterator()->valid());
        $this->assertNull($loader->getIterator()->current());
        $this->assertNull($loader->getIterator()->key());
    }

    public function testServiceLoaderLoadsRegisteredImplementations(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        $this->assertCount(2, $loader);
        $this->assertContainsEquals(new Implementation1(), $loader);
        $this->assertContainsEquals(new Implementation2(), $loader);
    }

    public function testServiceLoaderMergesGeneratedAndRegisteredImplementations(): void {
        GeneratedServiceProviderData::$mappings[Service::class][] = Implementation1::class;
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        $this->assertCount(2, $loader);
        $this->assertContainsEquals(new Implementation1(), $loader);
        $this->assertContainsEquals(new Implementation2(), $loader);
    }

    public function testServiceLoaderDoesNotLoadUnrelatedServiceImplementations(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, Implementation2::class);
        ServiceLoader::register(UnrelatedService::class, UnrelatedImplementation::class);

        $loader = ServiceLoader::load(Service::class);
        $this->assertCount(2, $loader);
    }

    public function testServiceLoaderLoadsUniqueRegisteredImplementations(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, Implementation2::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        $this->assertCount(2, $loader);
    }

    public function testServiceLoaderCachesImplementations(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        $providers1 = iterator_to_array($loader);
        $providers2 = iterator_to_array($loader);
        $this->assertSame($providers1, $providers2);
    }

    public function testServiceLoaderClearsCacheOnReload(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        $providers1 = iterator_to_array($loader);
        $loader->reload();
        $providers2 = iterator_to_array($loader);
        $this->assertNotSame($providers1, $providers2);
    }

    public function testServiceLoaderSeesNewlyRegisteredProvidersOnReload(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        $loader = ServiceLoader::load(Service::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $this->assertCount(1, $loader);
        $loader->reload();
        $this->assertCount(2, $loader);
    }

    public function testServiceLoaderSkipsInvalidImplementationsAfterFirstIteration(): void {
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, RequiredArgument::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        try {
            foreach ($loader as $provider) {}
        } catch (ServiceConfigurationError) {}
        $this->assertCount(2, $loader);
    }

    public function testServiceLoaderSkipsInvalidImplementationsAfterFirstIterationRewind(): void {
        ServiceLoader::register(Service::class, RequiredArgument::class);
        ServiceLoader::register(Service::class, Implementation1::class);
        ServiceLoader::register(Service::class, Implementation2::class);

        $loader = ServiceLoader::load(Service::class);
        try {
            foreach ($loader as $provider) {
                // load providers
            }
        } catch (ServiceConfigurationError) {}
        $this->assertCount(2, $loader);
    }

    public function testServiceLoaderThrowsOnConstructorWithRequiredArguments(): void {
        ServiceLoader::register(Service::class, RequiredArgument::class);

        $this->expectException(ServiceConfigurationError::class);
        foreach (ServiceLoader::load(Service::class) as $provider) {
            // load providers
        }
    }

    public function testServiceLoaderThrowsOnThrowingConstructor(): void {
        ServiceLoader::register(Service::class, ThrowingConstructor::class);

        $this->expectException(ServiceConfigurationError::class);
        foreach (ServiceLoader::load(Service::class) as $provider) {
            // load providers
        }
    }

    public function testServiceLoaderThrowsOnUnrelatedClass(): void {
        ServiceLoader::register(Service::class, UnrelatedImplementation::class);

        $this->expectException(ServiceConfigurationError::class);
        foreach (ServiceLoader::load(Service::class) as $provider) {
            // load providers
        }
    }

    public function testServiceLoaderSucceedsOnValidProvider(): void {
        $this->assertTrue(ServiceLoader::register(Service::class, Implementation1::class));
    }

    public function testServiceLoaderSucceedsOnRepeatedValidProvider(): void {
        $this->assertTrue(ServiceLoader::register(Service::class, Implementation1::class));
        $this->assertTrue(ServiceLoader::register(Service::class, Implementation1::class));
    }

    public function testServiceLoaderFailsOnUnavailableService(): void {
        $this->assertFalse(ServiceLoader::serviceAvailable(UnavailableService::class));
        $this->assertFalse(ServiceLoader::register(UnavailableService::class, ImplementationForUnavailableService::class));
    }

    public function testServiceLoaderFailsOnUnavailableProvider(): void {
        $this->assertFalse(ServiceLoader::providerAvailable(UnavailableImplementation::class));
        $this->assertFalse(ServiceLoader::register(Service::class, UnavailableImplementation::class));
    }

    public function testServiceLoaderFailsOnUnmetPackageDependency(): void {
        $this->assertFalse(ServiceLoader::providerAvailable(UnmetDependencyImplementation::class));
        $this->assertFalse(ServiceLoader::register(Service::class, UnmetDependencyImplementation::class));
    }
}
