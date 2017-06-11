<?php

use Cheppers\Robo\TemplateTask\TemplateTaskLoader;
use Robo\Contract\TaskInterface;

// @codingStandardsIgnoreStart
class FooRoboFile extends \Robo\Tasks
{
    // @codingStandardsIgnoreEnd

    use TemplateTaskLoader;

    public function basic(): TaskInterface
    {
        return $this->taskFoo();
    }
}
