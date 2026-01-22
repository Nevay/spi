<?php declare(strict_types=1);
namespace Nevay\SPI\Composer;

use Composer\Plugin\Capability\CommandProvider;

/**
 * @internal
 */
final class PluginCommandProvider implements CommandProvider {

    public function getCommands(): array {
        return [
            new ShowOutdatedCommand(),
        ];
    }
}
