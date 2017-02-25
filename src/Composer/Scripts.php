<?php

namespace Cheppers\Robo\TemplateTask\Composer;

use Cheppers\GitHooks\Main as GitHooksComposerScripts;
use Composer\IO\IOInterface;
use Composer\Script\Event;
use Stringy\StaticStringy;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class Scripts
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
    protected static $package = [];

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

    public static function postInstallCmd(Event $event): bool
    {
        GitHooksComposerScripts::deploy($event);

        return true;
    }

    public static function postUpdateCmd(Event $event): bool
    {
        GitHooksComposerScripts::deploy($event);

        return true;
    }

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
        static::$package = json_decode(file_get_contents($composerJsonFileName), true);
        list(static::$oldVendorMachine, static::$oldNameMachine) = explode('/', static::$package['name']);
        $oldNamespace = array_search('src/', static::$package['autoload']['psr-4']);
        list(static::$oldVendorNamespace, static::$oldNameNamespace) = explode('\\', $oldNamespace, 2);
        static::$oldNameNamespace = rtrim(static::$oldNameNamespace, '\\');
    }

    protected static function packageDump(): void
    {
        file_put_contents(
            $composerJsonFileName = static::$packageRootDir . '/' . static::$packageFileName,
            json_encode(
                static::$package,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );
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
        if (static::$event->getIO()->isInteractive() === false) {
            // @todo Provide default values or use CLI arguments.
            return;
        }

        $cwd = static::$packageRootDir === '.' ? getcwd() : static::$packageRootDir;
        $cwdParts = explode('/', $cwd);
        $defaultNewNameMachine = array_pop($cwdParts);
        $defaultNewVendorMachine = array_pop($cwdParts);

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

        static::$inputNewVendorNamespace = static::$event->getIO()->askAndValidate(
            sprintf(
                $questionPatternNamespace,
                2,
                'vendor as namespace',
                StaticStringy::upperCamelize(static::$inputNewVendorMachine)
            ),
            function (?string $input) {
                return static::validatePackageNameNamespace($input);
            },
            3,
            StaticStringy::upperCamelize(static::$inputNewVendorMachine)
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

        $defaultNewNameNamespace = StaticStringy::upperCamelize(preg_replace(
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
        $io = static::$event->getIO();

        $oldNamespace = static::$oldVendorNamespace . '\\' . static::$oldNameNamespace;
        $newNamespace = static::$inputNewVendorNamespace . '\\' . static::$inputNewNameNamespace;

        $io->write(
            "Replace '$oldNamespace' with '$newNamespace' in composer.json",
            true,
            IOInterface::VERBOSE
        );

        static::$package['name'] = static::$inputNewVendorMachine . '/' . static::$inputNewNameMachine;

        foreach (['autoload', 'autoload-dev'] as $key) {
            if (!isset(static::$package[$key]['psr-4'])) {
                continue;
            }

            $psr4 = static::$package[$key]['psr-4'];
            static::$package[$key]['psr-4'] = [];
            foreach ($psr4 as $namespace => $dir) {
                $namespace = static::replaceNamespace($namespace, $oldNamespace, $newNamespace);
                static::$package[$key]['psr-4'][$namespace] = $dir;
            }
        }

        foreach (static::$package['scripts'] as $key => $scripts) {
            if (is_string($scripts)) {
                static::$package['scripts'][$key] = static::replaceNamespace(
                    $scripts,
                    $oldNamespace,
                    $newNamespace
                );
            } else {
                foreach ($scripts as $i => $script) {
                    static::$package['scripts'][$key][$i] = static::replaceNamespace(
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
        static::$event->getIO()->write(
            sprintf('The new package name is "%s"', static::$package['name']),
            true
        );

        $namespace = '\\' . static::$inputNewVendorNamespace . '\\' . static::$inputNewNameNamespace;
        static::$event->getIO()->write(
            sprintf('The new namespace name is "%s"', $namespace),
            true
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

        GitHooksComposerScripts::deploy(static::$event);
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
}
