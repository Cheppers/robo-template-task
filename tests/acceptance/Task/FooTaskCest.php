<?php

namespace Cheppers\Robo\TemplateTask\Test\Task;

use Cheppers\Robo\TemplateTask\Test\AcceptanceTester;
use Cheppers\Robo\TemplateTask\Test\Helper\RoboFiles\FooRoboFile;

class FooTaskCest
{
    public function runFoo(AcceptanceTester $I)
    {
        $id = 'basic';
        $I->runRoboTask($id, FooRoboFile::class, 'basic');
        $I->assertEquals(0, $I->getRoboTaskExitCode($id));
        $I->assertEquals('', $I->getRoboTaskStdOutput($id));
        $I->assertEquals(" [Foo] Okay\n", $I->getRoboTaskStdError($id));
    }
}
