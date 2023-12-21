<?php declare(strict_types=1);
namespace Nevay\SPI\Fixtures;

final class RequiredArgument implements Service {

    public function __construct(string $requiredArgument) {}
}
