<?php declare(strict_types=1);
namespace Nevay\SPI\Fixtures;

use Exception;

final class ThrowingConstructor implements Service {

    public function __construct() {
        throw new Exception();
    }
}
