<?php

namespace Sweetchuck\Robo\TemplateTask\Test\Helper\RoboFiles;

use Robo\Tasks;
use Sweetchuck\Robo\TemplateTask\TemplateTaskLoader;
use Robo\Contract\TaskInterface;

class TemplateRoboFile extends Tasks
{
    use TemplateTaskLoader;

    public function list(): TaskInterface
    {
        return $this
            ->taskTemplateList()
            ->setOutput($this->output())
            ->setRaw(true);
    }
}
