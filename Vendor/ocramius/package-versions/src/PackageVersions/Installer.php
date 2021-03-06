<?php

declare(strict_types=1);

namespace PackageVersions;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\Locker;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Generator;
use RuntimeException;

use function array_key_exists;
use function array_merge;
use function chmod;
use function dirname;
use function file_exists;
use function file_put_contents;
use function is_writable;
use function iterator_to_array;
use function rename;
use function sprintf;
use function uniqid;

final class Installer implements ComposerV2Plugin, EventSubscriberInterface
{
    private static string $generatedClassTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace PackageVersions;

use Composer\InstalledVersions;
use OutOfBoundsException;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
%s
{
    /**
     * @deprecated please use {@see self::rootPackageName()} instead.
     *             This constant will be removed in version 2.0.0.
     */
    public const ROOT_PACKAGE_NAME = '%s';

    private function __construct()
    {
    }

    /**
     * @psalm-pure
     *
     * @psalm-suppress ImpureMethodCall we know that {@see InstalledVersions} interaction does not
     *                                  cause any side effects here.
     */
    public static function rootPackageName() : string
    {
        return InstalledVersions::getRootPackage()['name'];
    }

    /**
     * @throws OutOfBoundsException If a version cannot be located.
     *
     * @psalm-pure
     *
     * @psalm-suppress ImpureMethodCall we know that {@see InstalledVersions} interaction does not
     *                                  cause any side effects here.
     */
    public static function getVersion(string $packageName) : string
    {
        return InstalledVersions::getPrettyVersion($packageName)
            . '@' . InstalledVersions::getReference($packageName);
    }
}

PHP;

    public function activate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [ScriptEvents::POST_AUTOLOAD_DUMP => 'dumpVersionsClass'];
    }

    /**
     * @throws RuntimeException
     */
    public static function dumpVersionsClass(Event $composerEvent): void
    {
        $composer    = $composerEvent->getComposer();
        $rootPackage = $composer->getPackage();
        $versions    = iterator_to_array(self::getVersions($composer->getLocker(), $rootPackage));

        if (! array_key_exists('ocramius/package-versions', $versions)) {
            //plugin must be globally installed - we only want to generate versions for projects which specifically
            //require ocramius/package-versions
            return;
        }

        $versionClass = self::generateVersionsClass($rootPackage->getName());

        self::writeVersionClassToFile($versionClass, $composer, $composerEvent->getIO());
    }

    /**
     * @param string[] $versions
     */
    private static function generateVersionsClass(string $rootPackageName): string
    {
        return sprintf(
            self::$generatedClassTemplate,
            'fin' . 'al ' . 'cla' . 'ss ' . 'Versions', // note: workaround for regex-based code parsers :-(
            $rootPackageName
        );
    }

    /**
     * @throws RuntimeException
     */
    private static function writeVersionClassToFile(string $versionClassSource, Composer $composer, IOInterface $io): void
    {
        $installPath = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
            . '/src/PackageVersions/Versions.php';

        $installDir = dirname($installPath);
        if (! file_exists($installDir)) {
            $io->write('<info>ocramius/package-versions:</info> Package not found (probably scheduled for removal); generation of version class skipped.');

            return;
        }

        if (! is_writable($installDir)) {
            $io->write(
                sprintf(
                    '<info>ocramius/package-versions:</info> %s is not writable; generation of version class skipped.',
                    $installDir
                )
            );

            return;
        }

        $io->write('<info>ocramius/package-versions:</info> Generating version class...');

        $installPathTmp = $installPath . '_' . uniqid('tmp', true);
        file_put_contents($installPathTmp, $versionClassSource);
        chmod($installPathTmp, 0664);
        rename($installPathTmp, $installPath);

        $io->write('<info>ocramius/package-versions:</info> ...done generating version class');
    }

    /**
     * @throws RuntimeException
     */
    private static function locateRootPackageInstallPath(
        Config $composerConfig,
        RootPackageInterface $rootPackage
    ): string {
        if (self::getRootPackageAlias($rootPackage)->getName() === 'ocramius/package-versions') {
            return dirname($composerConfig->get('vendor-dir'));
        }

        return $composerConfig->get('vendor-dir') . '/ocramius/package-versions';
    }

    private static function getRootPackageAlias(RootPackageInterface $rootPackage): PackageInterface
    {
        $package = $rootPackage;

        while ($package instanceof AliasPackage) {
            $package = $package->getAliasOf();
        }

        return $package;
    }

    /**
     * @return Generator&string[]
     *
     * @psalm-return Generator<string, string>
     */
    private static function getVersions(Locker $locker, RootPackageInterface $rootPackage): Generator
    {
        $lockData = $locker->getLockData();

        $lockData['packages-dev'] ??= [];

        foreach (array_merge($lockData['packages'], $lockData['packages-dev']) as $package) {
            yield $package['name'] => $package['version'] . '@' . (
                $package['source']['reference'] ?? $package['dist']['reference'] ?? ''
            );
        }

        foreach ($rootPackage->getReplaces() as $replace) {
            $version = $replace->getPrettyConstraint();
            if ($version === 'self.version') {
                $version = $rootPackage->getPrettyVersion();
            }

            yield $replace->getTarget() => $version . '@' . $rootPackage->getSourceReference();
        }

        yield $rootPackage->getName() => $rootPackage->getPrettyVersion() . '@' . $rootPackage->getSourceReference();
    }
}
