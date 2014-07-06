<?php

namespace Gioffreda\Component\Git\Tests;

use Gioffreda\Component\Git\Git;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class GitTest
 * @package Gioffreda\Component\Git\Tests
 * @coversDefaultClass \Gioffreda\Component\Git\Git
 */
class GitTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    protected static $filesystem;

    /**
     * @var \Gioffreda\Component\Git\Git
     */
    protected static $git;

    /**
     * @covers ::__construct
     * @covers ::create
     * @covers ::getPath
     */
    public function testCreation()
    {
        $path = sprintf('%s/%s%s', sys_get_temp_dir(), 'git-client-test-', sha1(mt_rand().mt_rand().time()));
        self::getFilesystem()->mkdir($path);
        self::$git = Git::create($path);
        $this->assertEquals('Gioffreda\\Component\\Git\\Git', get_class(self::$git));
        $this->assertEquals($path, self::$git->getPath());
    }

    /**
     * @covers ::init
     * @covers ::output
     * @covers ::isInitialized
     * @depends testCreation
     */
    public function testInitialization()
    {
        $this->assertFalse(Git::isInitialized(self::$git->getPath()));
        $this->assertStringStartsWith('Initialized empty Git repository in', self::$git->init()->output());
        $this->assertTrue(Git::isInitialized(self::$git->getPath()));
        $this->assertCount(1, $history = self::$git->history());

        // reinitialization should have no effect
        $this->assertStringStartsWith('Initialized empty Git repository in', self::$git->init()->output());
        $this->assertCount(1, $newHistory = self::$git->history());
        $this->assertEquals($history, $newHistory);

        // configuring the user.email and user.name
        self::$git->config('user.email', $email = 'giovanni@example.com');
        self::$git->config('user.name', $name = 'Giovanni Gioffreda');
        $this->assertEquals($email, trim(self::$git->config('user.email')->output()));
        $this->assertEquals($name, trim(self::$git->config('user.name')->output()));
    }

    /**
     * @covers ::getPath
     * @covers ::output
     * @covers ::history
     * @depends testInitialization
     * @dataProvider filesProvider
     */
    public function testCreatingFIles($file, $remove)
    {
        $counter = count(self::$git->history());
        self::getFilesystem()->touch(sprintf('%s/%s', self::$git->getPath(), $file));
        $this->assertNotEmpty(self::$git->status());
        $this->assertCount(++$counter, self::$git->history());
    }

    /**
     * @covers ::add
     * @covers ::rm
     * @covers ::getPath
     * @covers ::output
     * @covers ::history
     * @depends testCreatingFIles
     * @dataProvider filesProvider
     */
    public function testAddingAndRemoving($file, $remove)
    {
        $counter = count(self::$git->history());
        $this->assertNull(self::$git->add($file)->output());
        $this->assertCount(++$counter, self::$git->history());
        if ($remove) {
            $this->assertStringStartsWith(sprintf("rm '%s'", $file), self::$git->rm($file)->output());
            $this->assertCount(++$counter, self::$git->history());
        }
    }

    /**
     * @covers ::commit
     * @covers ::output
     * @covers ::history
     * @depends testAddingAndRemoving
     */
    public function testCommitting()
    {
        $counter = count(self::$git->history());
        $this->assertContains($message = 'Initial commit.', self::$git->commit($message)->output());
        $this->assertCount(++$counter, self::$git->history());
    }

    /**
     * @covers ::branchAdd
     * @covers ::output
     * @covers ::branchDelete
     * @depends testCommitting
     */
    public function testBranching()
    {
        $this->assertNull(self::$git->branchAdd($branch = 'develop')->output());
        $this->assertStringStartsWith('Deleted branch', self::$git->branchDelete($branch)->output());

        // Adding another
        $this->assertNull(self::$git->branchAdd($branch = 'v1.2.3')->output());
        $this->assertStringStartsWith('Deleted branch', self::$git->branchDelete($branch)->output());

        // Adding another with a slash
        $this->assertNull(self::$git->branchAdd($branch = 'feature/a-new-one')->output());
        $this->assertStringStartsWith('Deleted branch', self::$git->branchDelete($branch)->output());
    }

    /**
     * @covers ::branchAdd
     * @covers ::output
     * @depends testBranching
     * @dataProvider branchesProvider
     */
    public function testAddBranch($branch)
    {
        $this->assertNull(self::$git->branchAdd($branch)->output());
    }

    /**
     * @covers ::checkout
     * @covers ::output
     * @depends testAddBranch
     * @dataProvider branchesProvider
     */
    public function testCheckingOut($branch)
    {
        $this->assertNull(self::$git->checkout($branch)->output());
        $this->assertNull(self::$git->checkout($branch)->output());
    }

    /**
     * @covers ::status
     * @depends testCheckingOut
     */
    public function testDumpingStatus()
    {
        $this->assertStringStartsWith('On branch', self::$git->status());
    }

    /**
     * @covers ::getStatuses
     * @depends testDumpingStatus
     * @dataProvider filesProvider
     */
    public function testFindingStatusInList($file, $remove)
    {
        if (!$remove) {
            $this->assertArrayNotHasKey($file, self::$git->getStatuses());
        } else {
            $this->assertArrayHasKey($file, self::$git->getStatuses());
        }
    }

    /**
     * @covers ::branchList
     * @covers ::getBranches
     * @depends testFindingStatusInList
     */
    public function testListingBranches()
    {
        $this->assertContains('master', self::$git->branchList());
        $this->assertCount(count($this->branchesProvider()) + 1, self::$git->getBranches());
    }

    /**
     * @covers ::branchList
     * @covers ::getBranches
     * @depends testListingBranches
     * @dataProvider branchesProvider
     */
    public function testFindingBranchInList($branch)
    {
        $this->assertContains($branch, self::$git->branchList());
        $this->assertArrayHasKey($branch, $branches = self::$git->getBranches());
        foreach ($branches as $b => $info) if ($branch != $b)
        {
            $this->assertEquals($branches[$branch]['hash'], $info['hash']);
            $this->assertEquals($branches[$branch]['message'], $info['message']);
        }
    }

    /**
     * @covers ::getStatuses
     * @covers ::getPath
     * @covers ::diff
     * @depends testFindingBranchInList
     * @dataProvider filesProvider
     */
    public function testEditingFiles($file, $remove)
    {
        if (!$remove) {
            $this->assertArrayNotHasKey($file, self::$git->getStatuses());
        } else {
            $this->assertArrayHasKey($file, self::$git->getStatuses());
        }
        $counter = count(self::$git->getStatuses());
        file_put_contents(sprintf('%s/%s', self::$git->getPath(), $file), json_encode($file));
        if (!$remove) {
            $counter++;
            $this->assertContains('diff', self::$git->diff());
            $this->assertContains('diff', self::$git->diff($file));
            $this->assertArrayHasKey($file, self::$git->getStatuses());
        } else {
            $this->assertNull(self::$git->diff());
        }
        $this->assertCount($counter, self::$git->getStatuses());
    }

    /**
     * @covers ::log
     * @covers ::getLogs
     * @covers ::merge
     * @depends testEditingFiles
     */
    public function testMerging()
    {
        $branches = $this->branchesProvider();
        self::$git->checkout($branches[0][0]);
        self::$git->add('.');
        self::$git->commit($message = 'Some changes.');
        $this->assertContains($message, self::$git->log());
        $counter = count(self::$git->getLogs());
        self::$git->checkout('master');
        self::$git->merge($branches[0][0]);
        self::$git->commit($mergeMessage = 'Merging');
        $this->assertContains($message, self::$git->log());
        $this->assertContains($mergeMessage, self::$git->log());
        $this->assertCount(++$counter, self::$git->getLogs());
    }

    /**
     * @covers ::run
     * @covers ::getDefaults
     * @covers ::logCommand
     * @depends testMerging
     */
    public function testRun()
    {
        $this->assertEquals(self::$git->log(), self::$git->run(array_merge(array(
            'log'
        ), self::$git->getDefaults('log'))));
        $history = self::$git->history();
        $historyLast = array_pop($history);
        $historySecondLast = array_pop($history);
        $this->assertEquals($historyLast, $historySecondLast);
    }

    /**
     * @covers \Gioffreda\Component\Git\Exception\GitProcessException
     * @expectedException \Gioffreda\Component\Git\Exception\GitProcessException
     * @depends testRun
     */
    public function testRunException()
    {
        self::$git->run('wrong-command');
    }

    /* PROVIDERS */

    /**
     * @return array
     */
    public function branchesProvider()
    {
        return array(
            array('feature/a_new_one'),
            array('feature/v1.2.3'),
            array('hotfix/bug-fixed-123'),
            array('hotfix/456')
        );
    }

    /**
     * @return array
     */
    public function filesProvider()
    {
        return array(
            array('a', true),
            array('b with spaces', true),
            array('c with-symbols._', true),
            array('d', false),
            array('e with spaces', false),
            array('f with_symbols.-', false),
        );
    }

    /* STATIC METHODS */

    /**
     * @return Filesystem
     */
    public static function getFilesystem()
    {
        if (!self::$filesystem) {
            self::$filesystem = new Filesystem();
        }

        return self::$filesystem;
    }

}
 