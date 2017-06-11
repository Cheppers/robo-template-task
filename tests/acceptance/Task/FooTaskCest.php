<?php

namespace Cheppers\Robo\TemplateTask\Tests\Acceptance\Task;

use Cheppers\Robo\TemplateTask\Test\AcceptanceTester;

class FooTaskCest
{
    public function runFoo(AcceptanceTester $I)
    {
        $id = 'basic';
        $I->runRoboTask($id, \FooRoboFile::class, 'basic');
        $I->assertEquals(0, $I->getRoboTaskExitCode($id));
        $I->assertEquals('', $I->getRoboTaskStdOutput($id));
        $I->assertEquals(" [Foo] Okay\n", $I->getRoboTaskStdError($id));
    }
}
