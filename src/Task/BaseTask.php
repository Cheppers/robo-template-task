<?php

namespace Sweetchuck\Robo\TemplateTask\Task;

use Robo\Common\OutputAwareTrait;
use Robo\Contract\CommandInterface;
use Robo\Contract\OutputAwareInterface;
use Robo\Result;
use Robo\Task\BaseTask as RoboBaseTask;
use Robo\TaskInfo;
use Sweetchuck\Robo\TemplateTask\Utils;
use Symfony\Component\Process\Process;

abstract class BaseTask extends RoboBaseTask implements CommandInterface, OutputAwareInterface
{
    use OutputAwareTrait;

    /**
     * @var string
     */
    protected $taskName = 'Robo';

    /**
     * @var string
     */
    protected $action = '';

    /**
     * @var string
     */
    protected $command = '';

    /**
     * @var string
     */
    protected $processClass = Process::class;

    /**
     * @var int
     */
    protected $processExitCode = 0;

    /**
     * @var string
     */
    protected $processStdOutput = '';

    /**
     * @var string
     */
    protected $processStdError = '';

    /**
     * @var array
     */
    protected $assets = [];

    // region Options

    // region Option - workingDirectory.
    /**
     * @var string
     */
    protected $workingDirectory = '';

    public function getWorkingDirectory(): string
    {
        return $this->workingDirectory;
    }

    /**
     * @return $this
     */
    public function setWorkingDirectory(string $value)
    {
        $this->workingDirectory = $value;

        return $this;
    }
    // endregion

    // region Option - roboExecutable.
    /**
     * @var string
     */
    protected $roboExecutable = 'bin/robo';

    public function getRoboExecutable(): string
    {
        return $this->roboExecutable;
    }

    /**
     * @return $this
     */
    public function setRoboExecutable(string $value)
    {
        $this->roboExecutable = $value;

        return $this;
    }
    // endregion

    // region Option - assetNamePrefix.
    /**
     * @var string
     */
    protected $assetNamePrefix = '';

    public function getAssetNamePrefix(): string
    {
        return $this->assetNamePrefix;
    }

    /**
     * @return $this
     */
    public function setAssetNamePrefix(string $value)
    {
        $this->assetNamePrefix = $value;

        return $this;
    }
    // endregion

    // endregion

    /**
     * {@inheritdoc}
     */
    public function __construct(array $options = [])
    {
        $this->setOptions($options);
    }

    /**
     * @return $this
     */
    public function setOptions(array $option)
    {
        foreach ($option as $name => $value) {
            switch ($name) {
                case 'workingDirectory':
                    $this->setWorkingDirectory($value);
                    break;

                case 'roboExecutable':
                    $this->setRoboExecutable($value);
                    break;

                case 'assetNamePrefix':
                    $this->setAssetNamePrefix($value);
                    break;
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getCommand()
    {
        $envPattern = [];
        $envArgs = [];

        $cmdPattern = [];
        $cmdArgs = [];

        $cmdAsIs = [];

        $cmdPattern[] = '%s';
        $cmdArgs[] = escapeshellcmd($this->getRoboExecutable());

        if ($this->action) {
            $cmdPattern[] = '%s';
            $cmdArgs[] = $this->action;
        }

        foreach ($this->getCommandOptions() as $optionName => $option) {
            switch ($option['type']) {
                case 'environment':
                    if ($option['value'] !== null) {
                        $envPattern[] = "{$optionName}=%s";
                        $envArgs[] = escapeshellarg($option['value']);
                    }
                    break;

                case 'value':
                    if ($option['value']) {
                        $cmdPattern[] = "--$optionName=%s";
                        $cmdArgs[] = escapeshellarg($option['value']);
                    }
                    break;

                case 'value-optional':
                    if ($option['value'] !== null) {
                        $value = (string) $option['value'];
                        if ($value === '') {
                            $cmdPattern[] = "--{$optionName}";
                        } else {
                            $cmdPattern[] = "--{$optionName}=%s";
                            $cmdArgs[] = escapeshellarg($value);
                        }
                    }
                    break;

                case 'flag':
                    if ($option['value']) {
                        $cmdPattern[] = "--$optionName";
                    }
                    break;

                case 'tri-state':
                    if ($option['value'] !== null) {
                        $cmdPattern[] = $option['value'] ? "--$optionName" : "--no-$optionName";
                    }
                    break;

                case 'true|false':
                    $nameFilter = array_combine(
                        explode('|', $optionName),
                        [true, false]
                    );

                    foreach ($nameFilter as $name => $filter) {
                        $items = array_keys($option['value'], $filter, true);
                        if ($items) {
                            $cmdPattern[] = "--$name=%s";
                            $cmdArgs[] = escapeshellarg(implode(' ', $items));
                        }
                    }
                    break;

                case 'space-separated':
                    $items = Utils::filterEnabled($option['value']);
                    if ($items) {
                        $cmdPattern[] = "--$optionName=%s";
                        $cmdArgs[] = escapeshellarg(implode(' ', $items));
                    }
                    break;

                case 'as-is':
                    if ($option['value'] instanceof CommandInterface) {
                        $cmd = $option['value']->getCommand();
                    } else {
                        $cmd = (string) $option['value'];
                    }

                    if ($cmd) {
                        $cmdAsIs[] = $cmd;
                    }
                    break;
            }
        }

        $wd = $this->getWorkingDirectory();

        $chDir = $wd ? sprintf('cd %s &&', escapeshellarg($wd)) : '';
        $env = vsprintf(implode(' ', $envPattern), $envArgs);
        $cmd = vsprintf(implode(' ', $cmdPattern), $cmdArgs);
        $asIs = implode(' ', $cmdAsIs);

        return implode(' ', array_filter([$chDir, $env, $cmd, $asIs]));
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->command = $this->getCommand();

        return $this
            ->runHeader()
            ->runDoIt()
            ->runProcessOutputs()
            ->runReturn();
    }

    /**
     * @return $this
     */
    protected function runHeader()
    {
        $this->printTaskInfo($this->command);

        return $this;
    }

    /**
     * @return $this
     */
    protected function runDoIt()
    {
        /** @var \Symfony\Component\Process\Process $process */
        $process = new $this->processClass($this->command);

        $this->processExitCode = $process->run(function ($type, $data) {
            $this->processRunCallback($type, $data);
        });
        $this->processStdOutput = $process->getOutput();
        $this->processStdError = $process->getErrorOutput();

        return $this;
    }

    /**
     * @return $this
     */
    protected function runProcessOutputs()
    {
        return $this;
    }

    protected function runReturn(): Result
    {
        return new Result(
            $this,
            $this->getTaskResultCode(),
            $this->getTaskResultMessage(),
            $this->getAssetsWithPrefixedNames()
        );
    }

    protected function processRunCallback(string $type, string $data): void
    {
        switch ($type) {
            case Process::OUT:
                $this->output()->write($data);
                break;

            case Process::ERR:
                $this->printTaskError($data);
                break;
        }
    }

    protected function getTaskResultCode(): int
    {
        return $this->processExitCode;
    }

    protected function getTaskResultMessage(): string
    {
        return $this->processStdError;
    }

    protected function getAssetsWithPrefixedNames(): array
    {
        $prefix = $this->getAssetNamePrefix();
        if (!$prefix) {
            return $this->assets;
        }

        $assets = [];
        foreach ($this->assets as $key => $value) {
            $assets["{$prefix}{$key}"] = $value;
        }

        return $assets;
    }

    protected function getCommandOptions(): array
    {
        return [
            'workingDirectory' => [
                'type' => 'other',
                'value' => $this->getWorkingDirectory(),
            ],
            'roboExecutable' => [
                'type' => 'other',
                'value' => $this->getRoboExecutable(),
            ],
        ];
    }

    public function getTaskName(): string
    {
        return $this->taskName ?: TaskInfo::formatTaskName($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTaskContext($context = null)
    {
        if (!$context) {
            $context = [];
        }

        if (empty($context['name'])) {
            $context['name'] = $this->getTaskName();
        }

        return parent::getTaskContext($context);
    }
}
