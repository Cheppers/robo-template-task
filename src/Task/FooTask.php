<?php

namespace Cheppers\Robo\TemplateTask\Task;

use Robo\Result;

class FooTask extends BaseTask
{
    /**
     * {@inheritdoc}
     */
    protected $taskName = 'Foo';

    /**
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $name => $value) {
            switch ($name) {
                case 'my01':
                    // @todo Do something.
                    break;
            }
        }

        return $this;
    }

    public function run()
    {
        $this->printTaskInfo('Okay');

        return Result::success($this);
    }
}
