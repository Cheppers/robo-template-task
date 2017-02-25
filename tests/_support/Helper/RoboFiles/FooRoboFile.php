<?php

namespace Cheppers\Robo\TemplateTask\Test\Helper\RoboFiles;

use Cheppers\Robo\TemplateTask\TemplateTaskLoader;
use Robo\Contract\TaskInterface;
use Robo\Tasks;

class FooRoboFile extends Tasks
{
    use TemplateTaskLoader;

    public function basic(): TaskInterface
    {
        return $this->taskFoo(['my01' =>  'dummy']);
    }
}
