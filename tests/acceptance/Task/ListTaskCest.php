<?php

namespace Sweetchuck\Robo\TemplateTask\Tests\Acceptance\Task;

use Sweetchuck\Robo\TemplateTask\Test\AcceptanceTester;
use Sweetchuck\Robo\TemplateTask\Test\Helper\RoboFiles\TemplateRoboFile;

class ListTaskCest
{
    public function runList(AcceptanceTester $I)
    {
        $id = 'list';
        $I->runRoboTask($id, TemplateRoboFile::class, 'list');
        $exitCode = $I->getRoboTaskExitCode($id);
        $stdOutput = $I->getRoboTaskStdOutput($id);
        $stdError = $I->getRoboTaskStdError($id);

        $I->assertEquals(0, $exitCode);
        $I->assertRegExp('/^help( )+Displays help for a command$/um', $stdOutput);
        $I->assertRegExp('/^list( )+Lists commands$/um', $stdOutput);
        $I->assertContains(" [RoboList] bin/robo list --raw\n", $stdError);
    }
}
