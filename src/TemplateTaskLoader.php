<?php

namespace Sweetchuck\Robo\TemplateTask;

use Robo\Collection\CollectionBuilder;

trait TemplateTaskLoader
{
    /**
     * @return \Sweetchuck\Robo\TemplateTask\Task\ListTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskTemplateList(array $options = []): CollectionBuilder
    {
        return $this->task(Task\ListTask::class, $options);
    }
}
