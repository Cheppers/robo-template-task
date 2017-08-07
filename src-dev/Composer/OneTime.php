<?php

namespace Sweetchuck\Robo\TemplateTask\Composer;

use Sweetchuck\GitHooks\Composer\Scripts as GitHooks;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Stringy\StaticStringy;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class OneTime
{
    /**
     * @var \Composer\Script\Event
     */
    protected static $event = null;

    /**
     * @var string
     */
    protected static $packageRootDir = '.';

    /**
     * @var string
     */
    protected static $packageFileName = 'composer.json';

    /**
     * @var array
     */
    protected static $composerInfo = [];

    /**
     * @var string
     */
    protected static $oldVendorMachine = '';

    /**
     * @var string
     */
    protected static $oldVendorNamespace = '';

    /**
     * @var string
     */
    protected static $oldNameMachine = '';

    /**
     * @var string
     */
    protected static $oldNameNamespace = '';

    /**
     * @var int
     */
    protected static $ioAttempts = 3;

    /**
     * @var string
     */
    protected static $inputNewVendorMachine = '';

    /**
     * @var string
     */
    protected static $inputNewVendorNamespace = '';

    /**
     * @var string
     */
    protected static $inputNewNameMachine = '';

    /**
     * @var string
     */
    protected static $inputNewNameNamespace = '';

    /**
     * @var string
     */
    protected static $envNamePrefix = '';

    public static function postCreateProjectCmd(Event $event): bool
    {
        static::$event = $event;
        static::oneTime();

        return true;
    }

    protected static function oneTime(): void
    {
        static::oneTimePre();
        static::oneTimeMain();
        static::oneTimePost();
    }

    protected static function oneTimePre(): void
    {
        static::packageRead();
    }

    protected static function oneTimeMain(): void
    {
        static::removeOneTimeScript();
        static::renamePackage();
        static::updateReadMe();
        static::gitInit();
    }

    protected static function oneTimePost(): void
    {
        static::packageDump();
        static::composerDumpAutoload();
        static::composerUpdate();
    }

    protected static function packageRead(): void
    {
        $composerJsonFileName = static::$packageRootDir . '/' . static::$packageFileName;
        static::$composerInfo = json_decode(file_get_contents($composerJsonFileName), true);
        list(static::$oldVendorMachine, static::$oldNameMachine) = explode('/', static::$composerInfo['name']);
        $oldNamespace = array_search('src/', static::$composerInfo['autoload']['psr-4']);
        list(static::$oldVendorNamespace, static::$oldNameNamespace) = explode('\\', $oldNamespace, 2);
        static::$oldNameNamespace = rtrim(static::$oldNameNamespace, '\\');
    }

    protected static function packageDump(): void
    {
        static::writeLineVerbose(
            'Update the "<comment>{fileName}</comment>"',
            [
                '{fileName}' => static::$packageFileName,
            ]
        );
        file_put_contents(
            $composerJsonFileName = static::$packageRootDir . '/' . static::$packageFileName,
            json_encode(
                static::$composerInfo,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );
    }

    protected static function removeOneTimeScript(): void
    {
        static::writeLineVerbose('Remove <comment>"composer.json/scripts/post-create-project-cmd"</comment>');
        unset(static::$composerInfo['scripts']['post-create-project-cmd']);


        $fileName = static::$packageRootDir . '/src-dev/Composer/OneTime.php';
        static::writeLineVerbose("Delete <comment>'$fileName'</comment>");
        unlink($fileName);
    }

    protected static function renamePackage(): void
    {
        static::renamePackageInput();
        static::renamePackageComposer();
        static::renamePackageSource();
        static::renamePackageSummary();
    }

    protected static function renamePackageInput(): void
    {
        $envVars = static::getEnvVars();

        $cwd = static::$packageRootDir === '.' ? getcwd() : static::$packageRootDir;
        $cwdParts = explode('/', $cwd);

        $cwdPartNameMachine = array_pop($cwdParts);
        $cwdPartVendorMachine = array_pop($cwdParts);
        $defaultNewVendorMachine = $envVars['vendorMachine'] ?: $cwdPartVendorMachine;
        $defaultNewNameMachine = $envVars['nameMachine'] ?: $cwdPartNameMachine;

        $questionPatternMachine = implode("\n", [
            '<question>Rename the package (%d/4) - %s:</question>',
            '<question>Only lower case letters, numbers and "-" are allowed</question>',
            'Default: "<info>%s</info>"',
            '',
        ]);

        $questionPatternNamespace = implode("\n", [
            '<question>Rename the package (%d/4) - %s:</question>',
            '<question>Capital camel case format is allowed</question>',
            'Default: "<info>%s</info>"',
            '',
        ]);

        static::$inputNewVendorMachine = static::$event->getIO()->askAndValidate(
            sprintf(
                $questionPatternMachine,
                1,
                'vendor as machine-name',
                $defaultNewVendorMachine
            ),
            function (?string $input) {
                return static::validatePackageNameMachine($input);
            },
            3,
            $defaultNewVendorMachine
        );

        $defaultNewVendorNamespace = $envVars['vendorNamespace']
            ?: StaticStringy::upperCamelize(static::$inputNewVendorMachine);
        static::$inputNewVendorNamespace = static::$event->getIO()->askAndValidate(
            sprintf(
                $questionPatternNamespace,
                2,
                'vendor as namespace',
                $defaultNewVendorNamespace
            ),
            function (?string $input) {
                return static::validatePackageNameNamespace($input);
            },
            3,
            $defaultNewVendorNamespace
        );

        static::$inputNewNameMachine = static::$event->getIO()->askAndValidate(
            sprintf(
                $questionPatternMachine,
                3,
                'name as machine-name',
                $defaultNewNameMachine
            ),
            function (?string $input) {
                return static::validatePackageNameMachine($input, 'robo-');
            },
            3,
            $defaultNewNameMachine
        );

        $defaultNewNameNamespace = $envVars['nameNamespace']
            ?: StaticStringy::upperCamelize(preg_replace(
                '/^robo-/',
                '',
                static::$inputNewNameMachine
            ));
        static::$inputNewNameNamespace = static::$event->getIO()->askAndValidate(
            sprintf(
                $questionPatternNamespace,
                4,
                'name as namespace',
                $defaultNewNameNamespace
            ),
            function (?string $input) {
                return static::validatePackageNameNamespace($input, 'Robo\\');
            },
            3,
            $defaultNewNameNamespace
        );
    }

    protected static function renamePackageComposer(): void
    {
        $oldNamespace = static::$oldVendorNamespace . '\\' . static::$oldNameNamespace;
        $newNamespace = static::$inputNewVendorNamespace . '\\' . static::$inputNewNameNamespace;

        static::writeLineNormal(
            'Replace "<comment>{old}</comment>" with "<comment>{new}</comment>" in composer.json',
            [
                '{old}' => $oldNamespace,
                '{new}' => $newNamespace,
            ]
        );

        static::$composerInfo['name'] = static::$inputNewVendorMachine . '/' . static::$inputNewNameMachine;

        foreach (['autoload', 'autoload-dev'] as $key) {
            if (!isset(static::$composerInfo[$key]['psr-4'])) {
                continue;
            }

            $psr4 = static::$composerInfo[$key]['psr-4'];
            static::$composerInfo[$key]['psr-4'] = [];
            foreach ($psr4 as $namespace => $dir) {
                $namespace = static::replaceNamespace($namespace, $oldNamespace, $newNamespace);
                static::$composerInfo[$key]['psr-4'][$namespace] = $dir;
            }
        }

        foreach (static::$composerInfo['scripts'] as $key => $scripts) {
            if (is_string($scripts)) {
                static::$composerInfo['scripts'][$key] = static::replaceNamespace(
                    $scripts,
                    $oldNamespace,
                    $newNamespace
                );
            } else {
                foreach ($scripts as $i => $script) {
                    static::$composerInfo['scripts'][$key][$i] = static::replaceNamespace(
                        $script,
                        $oldNamespace,
                        $newNamespace
                    );
                }
            }
        }
    }

    protected static function renamePackageSource(): void
    {
        $oldNamespace = static::$oldVendorNamespace . '\\' . static::$oldNameNamespace;
        $newNamespace = static::$inputNewVendorNamespace . '\\' . static::$inputNewNameNamespace;

        /** @var \Symfony\Component\Finder\Finder $files */
        $files = (new Finder())
            ->in([static::$packageRootDir . '/src'])
            ->in([static::$packageRootDir . '/src-dev'])
            ->in([static::$packageRootDir . '/tests'])
            ->files()
            ->name('/.+\.(php|yml)$/');
        foreach ($files as $file) {
            static::replaceNamespaceInFileContent($file, $oldNamespace, $newNamespace);
        }

        $fileNames = [
            static::$packageRootDir . '/codeception.yml',
        ];
        foreach ($fileNames as $fileName) {
            static::replaceNamespaceInFileContent($fileName, $oldNamespace, $newNamespace);
        }
    }

    protected static function renamePackageSummary(): void
    {
        static::writeLineNormal(
            'The new package name is "<comment>{name}</comment>"',
            [
                '{name}' => static::$composerInfo['name'],
            ]
        );

        $namespace = '\\' . static::$inputNewVendorNamespace . '\\' . static::$inputNewNameNamespace;
        static::writeLineNormal(
            'The new namespace name is "<comment>{name}</comment>"',
            [
                '{name}' => $namespace,
            ]
        );
    }

    protected static function updateReadMe(): void
    {
        $pattern = '/^' . preg_quote('Robo\\') . '/';
        $nameNamespaceShort = preg_replace($pattern, '', static::$inputNewNameNamespace);
        $nameNamespaceShort = StaticStringy::humanize($nameNamespaceShort);
        $travisBadge = static::getTravisBadgeMarkdown();
        $codeCovBadge = static::getCodeCovBadgeMarkdown();

        $content = <<< MARKDOWN
# Robo task wrapper for $nameNamespaceShort

$travisBadge
$codeCovBadge

@todo

MARKDOWN;

        // @todo Error handling.
        file_put_contents(static::$packageRootDir . '/README.md', $content);
    }

    /**
     * @param string|\Symfony\Component\Finder\SplFileInfo
     * @param string $old
     * @param string $new
     *
     * @return int|false
     */
    protected static function replaceNamespaceInFileContent($file, string $old, string $new)
    {
        $fileName = ($file instanceof SplFileInfo) ? $file->getPathname() : $file;

        // @todo Error handling.
        return file_put_contents(
            $fileName,
            static::replaceNamespace(file_get_contents($fileName), $old, $new)
        );
    }

    protected static function replaceNamespace(string $text, string $old, string $new): string
    {
        return preg_replace(
            '/(^|\W)' . preg_quote($old) . '(\W)/',
            "$1{$new}$2",
            $text
        );
    }

    protected static function gitInit(): void
    {
        if (!file_exists(static::$packageRootDir . '/.git')) {
            $command = sprintf('cd %s && git init', static::$packageRootDir);
            $output = [];
            $exit_code = 0;
            exec($command, $output, $exit_code);
            if ($exit_code !== 0) {
                // @todo Do something.
            }
        }

        GitHooks::deploy(static::$event);
    }

    /**
     * @todo Error handling.
     */
    protected static function composerDumpAutoload(): void
    {
        $cmdPattern = '%s dump-autoload';
        $cmdArgs = [
            escapeshellcmd($_SERVER['argv'][0]),
        ];

        $exitCode = 0;
        $files = [];
        exec(vsprintf($cmdPattern, $cmdArgs), $files, $exitCode);
    }

    /**
     * @todo Error handling.
     */
    protected static function composerUpdate(): void
    {
        $cmdPattern = '%s update nothing --lock';
        $cmdArgs = [
            escapeshellcmd($_SERVER['argv'][0]),
        ];

        $exitCode = 0;
        $files = [];
        exec(vsprintf($cmdPattern, $cmdArgs), $files, $exitCode);
    }

    protected static function validatePackageNameMachine(?string $input, string $prefix = ''): ?string
    {
        if ($input !== null) {
            if ($prefix && strpos($input, $prefix) !== 0) {
                $input = "{$prefix}{$input}";
            }

            if (!preg_match('/^' . preg_quote($prefix) . '[a-z][a-z0-9\-]*$/', $input)) {
                throw new \InvalidArgumentException('Invalid characters');
            }

            $input = preg_replace('/-{2,}/', '-', $input);
            $input = trim($input, '-');
        }

        return $input;
    }

    protected static function validatePackageNameNamespace(?string $input, string $prefix = ''): ?string
    {
        if ($input !== null) {
            $input = ltrim($input, '\\');

            if ($prefix && strpos($input, $prefix) !== 0) {
                $input = "{$prefix}{$input}";
            }

            if (!preg_match('/^[A-Z][\\a-zA-Z0-9]*$/', $input)) {
                throw new \InvalidArgumentException('Invalid characters');
            }
            // @todo \a.
        }

        return $input;
    }

    protected static function getTravisBadgeMarkdown(): string
    {
        $vendorMachine = static::$inputNewVendorMachine;
        $nameMachine = static::$inputNewNameMachine;
        $baseUrl = "https://travis-ci.org/{$vendorMachine}/{$nameMachine}";

        return "[![Build Status]({$baseUrl}.svg?branch=master)]({$baseUrl})";
    }

    protected static function getCodeCovBadgeMarkdown(): string
    {
        $vendorMachine = static::$inputNewVendorMachine;
        $nameMachine = static::$inputNewNameMachine;
        $baseUrl = "https://codecov.io/gh/{$vendorMachine}/{$nameMachine}";

        return "[![codecov]({$baseUrl}/branch/master/graph/badge.svg)]({$baseUrl})";
    }

    protected static function writeLineNormal(string $message, array $args = []): void
    {
        static::writeLine($message, $args, IOInterface::NORMAL);
    }

    protected static function writeLineVerbose(string $message, array $args = []): void
    {
        static::writeLine($message, $args, IOInterface::VERBOSE);
    }

    protected static function writeLine(string $message, array $args = [], int $verbosity = IOInterface::NORMAL): void
    {
        static::$event
            ->getIO()
            ->write(
                strtr($message, $args),
                true,
                $verbosity
            );
    }

    protected static function getEnvVars(): array
    {
        $options = [
            'vendorMachine' => null,
            'vendorNamespace' => null,
            'nameMachine' => null,
            'nameNamespace' => null,
        ];

        foreach (array_keys($options) as $name) {
            $options[$name] = static::getEnvVar($name);
        }

        return $options;
    }

    protected static function getEnvVar(string $name): string
    {
        return getenv(static::getEnvNamePrefix() . '_' . static::toEnvName($name));
    }

    protected static function getEnvNamePrefix(): string
    {
        if (!static::$envNamePrefix) {
            /** @var \Composer\Package\Package $package */
            $package = static::$event->getComposer()->getPackage();
            list(, $name) = explode('/', $package->getName());

            static::$envNamePrefix = static::toEnvName($name);
        }

        return static::$envNamePrefix;
    }

    protected static function toEnvName(string $name): string
    {
        return StaticStringy::toUpperCase(StaticStringy::underscored($name));
    }
}
