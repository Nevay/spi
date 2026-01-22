<?php declare(strict_types=1);
namespace Nevay\SPI\Composer;

use Composer\Command\BaseCommand;
use Composer\Filter\PlatformRequirementFilter\PlatformRequirementFilterFactory;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\VersionParser;
use Nevay\SPI\ServiceProviderDependency\PackageDependency;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_column;
use function array_sum;
use function count;
use function in_array;

/**
 * @internal
 */
final class ShowOutdatedCommand extends BaseCommand {

    protected function configure(): void {
        $this
            ->setName('spi:show-outdated-package-dependencies')
            ->setDescription('Shows service providers with outdated PackageDependency attributes')
            ->setHidden(true)
            ->addOption('ignore-package', 'i', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Package dependency to ignore')
            ->addOption('show-all', 'a', InputOption::VALUE_NONE, 'Includes service providers defined by dependencies')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        return Plugin::runWithAutoloader($this->requireComposer(), $this->_execute(...), $input, $output);
    }

    protected function _execute(InputInterface $input, OutputInterface $output): int {
        $ignoredPackages = $input->getOption('ignore-package');
        $showAll = $input->getOption('show-all');

        $composer = $this->requireComposer();
        $repositorySet = new RepositorySet();
        $repositorySet->addRepository(new CompositeRepository($composer->getRepositoryManager()->getRepositories()));
        $versionSelector = new VersionSelector($repositorySet);
        $versionParser = new VersionParser();

        $io = $this->getIO();
        $exitCode = 0;

        foreach (Plugin::serviceProviders($composer, $io, $showAll) as $providers) {
            foreach ($providers as $provider => $_) {
                try {
                    $reflection = new ReflectionClass($provider);
                } catch (ReflectionException) {
                    continue;
                }

                $dependencies = [];

                foreach ($reflection->getAttributes(PackageDependency::class) as $packageDependency) {
                    [
                        $package,
                        $version,
                        $sealed,
                    ] = (static fn(PackageDependency $packageDependency): array => [
                        $packageDependency->package,
                        $packageDependency->version,
                        $packageDependency->sealed,
                    ])->bindTo(null, PackageDependency::class)($packageDependency->newInstance());

                    $dependencies[] = $dependency = new class($package, $version, $sealed) {

                        public function __construct(
                            public readonly string $package,
                            public readonly string $version,
                            public readonly bool $sealed,
                            public bool $ignored = false,
                            public bool $notFound = false,
                            public bool $outdated = false,
                            public ?PackageInterface $availablePackage = null,
                        ) {}
                    };

                    if (in_array($package, $ignoredPackages, true)) {
                        $dependency->ignored = true;
                        continue;
                    }

                    $link = $composer->getPackage()->getRequires()[$package] ?? null;

                    $candidate = $versionSelector->findBestCandidate(
                        packageName: $package,
                        targetPackageVersion: $link?->getPrettyConstraint(),
                        platformRequirementFilter: PlatformRequirementFilterFactory::ignoreAll(),
                        io: $io,
                    );

                    if (!$candidate) {
                        $dependency->notFound = true;
                        continue;
                    }
                    if ($sealed) {
                        continue;
                    }

                    while ($candidate instanceof AliasPackage) {
                        $candidate = $candidate->getAliasOf();
                    }

                    $constraint = $versionParser->parseConstraints($version);
                    $candidateVersion = $versionParser->parseConstraints($candidate->getVersion());

                    $dependency->availablePackage = $candidate;
                    $dependency->outdated = !$candidateVersion->matches($constraint);
                }

                $notFound = array_sum(array_column($dependencies, 'notFound'));
                $outdated = array_sum(array_column($dependencies, 'outdated'));
                $ignored = array_sum(array_column($dependencies, 'ignored'));

                if ($notFound || $outdated) {
                    $exitCode = 1;
                }

                match (true) {
                    $ignored === count($dependencies) => $io->write("<info>$provider</info>", verbosity: IOInterface::VERY_VERBOSE),
                    $notFound > 0 => $io->write("<highlight>$provider</highlight>"),
                    $outdated > 0 => $io->write("<comment>$provider</comment>"),
                    default => $io->write("<info>$provider</info>", verbosity: IOInterface::VERBOSE),
                };

                foreach ($dependencies as $dependency) {
                    match (true) {
                        $dependency->notFound => $io->write("<highlight>- $dependency->package $dependency->version: package not found</highlight>"),
                        $dependency->outdated => $io->write("<comment>- $dependency->package $dependency->version: outdated, latest: {$dependency->availablePackage->getPrettyVersion()}</comment>"),
                        $dependency->ignored => $io->write("- $dependency->package $dependency->version: ignored", verbosity: IOInterface::VERY_VERBOSE),
                        $dependency->sealed => $io->write("<info>- $dependency->package $dependency->version: sealed</info>", verbosity: IOInterface::VERY_VERBOSE),
                        default => $io->write("<info>- $dependency->package $dependency->version: up-to-date, latest: {$dependency->availablePackage->getPrettyVersion()}</info>", verbosity: IOInterface::VERBOSE),
                    };
                }
            }
        }

        return $exitCode;
    }
}
