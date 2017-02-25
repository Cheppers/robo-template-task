<?php

namespace Cheppers\Robo\TemplateTask;

use Robo\Collection\CollectionBuilder;

trait TemplateTaskLoader
{
    /**
     * @return \Cheppers\Robo\TemplateTask\Task\FooTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskFoo(array $options = []): CollectionBuilder
    {
        return $this->task(Task\FooTask::class, $options);
    }
}
