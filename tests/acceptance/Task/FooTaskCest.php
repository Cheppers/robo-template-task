<?php

namespace Cheppers\Robo\TemplateTask\Test\Task;

use Cheppers\Robo\TemplateTask\Test\AcceptanceTester;
use Cheppers\Robo\TemplateTask\Test\Helper\RoboFiles\FooRoboFile;

class FooTaskCest
{
    public function runFoo(AcceptanceTester $I)
    {
        $I->runRoboTask(FooRoboFile::class, 'basic');
        $I->assertEquals(0, $I->getRoboTaskExitCode());
        $I->assertEquals('', $I->getRoboTaskStdOutput());
        $I->assertEquals(" [Foo] Okay\n", $I->getRoboTaskStdError());
    }
}
