<?php

namespace Cheppers\Robo\TemplateTask\Tests\Unit\Task;

use Codeception\Test\Unit;

class FooTaskTest extends Unit
{
    /**
     * @var \Cheppers\Robo\TemplateTask\Test\UnitTester
     */
    protected $tester;

    public function testRun()
    {
        $this->tester->assertTrue(true, 'Dummy assert');
    }
}
