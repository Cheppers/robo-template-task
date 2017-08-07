<?php

namespace Sweetchuck\Robo\TemplateTask\Tests\Unit\Task;

use Codeception\Test\Unit;

class FooTaskTest extends Unit
{
    /**
     * @var \Sweetchuck\Robo\TemplateTask\Test\UnitTester
     */
    protected $tester;

    public function testRun()
    {
        $this->tester->assertTrue(true, 'Dummy assert');
    }
}
