<?php declare(strict_types=1);
namespace Nevay\SPI\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use function array_combine;

final class Plugin implements PluginInterface, EventSubscriberInterface {

    public static function getSubscribedEvents(): array {
        return [
            ScriptEvents::PRE_AUTOLOAD_DUMP => 'preAutoloadDump',
        ];
    }

    public function activate(Composer $composer, IOInterface $io): void {
        // no-op
    }

    public function deactivate(Composer $composer, IOInterface $io): void {
        // no-op
    }

    public function uninstall(Composer $composer, IOInterface $io): void {
        // no-op
    }

    public function preAutoloadDump(Event $event): void {
        $mappings = [];
        $extra = $event->getComposer()->getPackage()->getExtra();
        foreach ($extra['spi'] ?? [] as $interface => $providers) {
            $mappings[$interface] ??= [];
            $mappings[$interface] += array_combine($providers, $providers);
        }
        foreach ($event->getComposer()->getRepositoryManager()->getLocalRepository()->getPackages() as $package) {
            $extra = $package->getExtra();
            foreach ($extra['spi'] ?? [] as $interface => $providers) {
                $providers = (array) $providers;
                $mappings[$interface] ??= [];
                $mappings[$interface] += array_combine($providers, $providers);
            }
        }

        $match = '';
        foreach ($mappings as $interface => $providers) {
            $match .= "\n            \\$interface::class => [";
            foreach ($providers as $class) {
                $match .= "\n                \\$class::class,";
            }
            $match .= "\n            ],";
        }

        $code = <<<PHP
            <?php declare(strict_types=1);
            namespace Nevay\SPI;
            
            /**
             * @internal 
             */
            final class GeneratedServiceProviderData {
            
                public const VERSION = 1;
            
                /**
                 * @param class-string \$service
                 * @return list<class-string>
                 */
                public static function providers(string \$service): array {
                    return match (\$service) {
                        default => [],$match
                    };
                }
            }
            PHP;

        $filesystem = new Filesystem();
        $vendorDir = $filesystem->normalizePath($event->getComposer()->getConfig()->get('vendor-dir'));
        $filesystem->ensureDirectoryExists($vendorDir . '/composer');
        $filesystem->filePutContentsIfModified($vendorDir . '/composer/GeneratedServiceProviderData.php', $code);

        $package = $event->getComposer()->getPackage();
        $autoload = $package->getAutoload();
        $autoload['classmap'][] = $vendorDir . '/composer/GeneratedServiceProviderData.php';
        $package->setAutoload($autoload);
    }
}
