<?php

namespace Gioffreda\Component\Git\Tests;

use Gioffreda\Component\Git\Exception\GitException;
use Gioffreda\Component\Git\Exception\GitProcessException;
use Gioffreda\Component\Git\Git;
use Gioffreda\Component\Git\GitFlow;
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
        $path = self::buildPath();
        self::getFilesystem()->mkdir($path);
        self::$git = Git::create($path);
        $this->assertEquals('Gioffreda\\Component\\Git\\Git', get_class(self::$git));
        $this->assertEquals($path, self::$git->getPath());
    }

    /**
     * @covers ::cloneRemote
     * @dataProvider remotesProvider
     */
    public function testCloning($url)
    {
        $path = self::buildPath();
        self::getFilesystem()->mkdir($path);
        $git = Git::cloneRemote($url, $path);
        $this->assertEquals('Gioffreda\\Component\\Git\\Git', get_class($git));
        $this->assertEquals($path, $git->getPath());
    }

    /**
     * @covers ::remoteAdd
     * @covers ::remoteRename
     * @covers ::remoteRemove
     * @covers ::remoteSetUrl
     * @covers ::remoteGetUrl
     * @covers ::remoteShow
     * @depends testCloning
     * @expectedException \Gioffreda\Component\Git\Exception\GitProcessException
     * @dataProvider remotesProvider
     */
    public function testRemote($url, $name)
    {
        $path = self::buildPath();
        self::getFilesystem()->mkdir($path);
        $git = Git::cloneRemote($url, $path);
        $this->assertStringStartsWith('origin', $git->remoteShow());
        $this->assertContains($name, $git->remoteShow('origin'));
        $this->assertContains('origin', $git->remoteShow('origin'));
        $this->assertStringStartsWith($url, $git->remoteGetUrl('origin'));

        $brokenUrl = "$url.test";
        $git->remoteAdd('broken', $brokenUrl);
        $this->assertStringStartsWith($brokenUrl, $git->remoteGetUrl('broken'));
        $git->remoteSetUrl('broken', $url);
        $this->assertStringStartsWith($url, $git->remoteGetUrl('broken'));
        $git->remoteRename('broken', 'fixed');
        $this->assertContains($name, $git->remoteShow('fixed'));
        $this->assertContains('fixed', $git->remoteShow('fixed'));
        $git->remoteRemove('fixed');
        // the following must throw an exception because the 'fixed' origin is gone
        $git->remoteShow('fixed');
    }

    /**
     * @covers ::init
     * @covers ::output
     * @covers ::isInitialized
     * @covers ::config
     * @covers ::getConfiguration
     * @depends testCreation
     */
    public function testInitialization()
    {
        $this->assertFalse(Git::isInitialized(self::$git->getPath()));
        $this->assertRegExp('/^Initiali(s|z)ed empty Git repository in/', self::$git->init()->output());
        $this->assertTrue(Git::isInitialized(self::$git->getPath()));
        $this->assertCount(1, $history = self::$git->history());

        // reinitialization should have no effect
        $this->assertRegExp('/^Initiali(s|z)ed empty Git repository in/', self::$git->init()->output());
        $this->assertCount(1, $newHistory = self::$git->history());
        $this->assertEquals($history, $newHistory);

        // configuring the user.email and user.name
        self::$git->config('user.email', $email = 'giovanni@example.com');
        self::$git->config('user.name', $name = 'Giovanni Gioffreda');
        $this->assertEquals($email, trim(self::$git->config('user.email')->output()));
        $this->assertEquals($name, trim(self::$git->config('user.name')->output()));
        $configuration = self::$git->getConfiguration();
        $this->assertArrayHasKey('user.email', $configuration);
        $this->assertArrayHasKey('user.name', $configuration);
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
        $this->assertEmpty(self::$git->add($file)->output());
        $this->assertCount(++$counter, self::$git->history());
        if ($remove) {
            $this->assertStringStartsWith(sprintf("rm '%s'", $file), self::$git->rm($file)->output());
            $this->assertCount(++$counter, self::$git->history());
        }
    }

    /**
     * @covers ::mv
     * @covers ::history
     * @covers ::status
     * @expectedException \Gioffreda\Component\Git\Exception\GitProcessException
     * @depends testAddingAndRemoving
     */
    public function testMovingFailed()
    {
        $origin = 'originfile';
        $destination = 'detinationfile';
        self::getFilesystem()->touch(sprintf('%s/%s', self::$git->getPath(), $origin));
        $this->assertNotEmpty(self::$git->status());
        $this->assertContains($origin, self::$git->status());
        $this->assertContains($destination, self::$git->mv($origin, $destination)->status());
    }

    /**
     * @covers ::mv
     * @depends testMovingFailed
     */
    public function testActuallyMoving()
    {
        $origin = 'originfile';
        $destination = 'detinationfile';
        $this->assertContains('Untracked files', self::$git->add($origin)->status());
        $this->assertNotEmpty(self::$git->status());
        $this->assertContains($origin, self::$git->status());
        $this->assertContains($destination, self::$git->mv($origin, $destination)->status());
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
        $this->assertEmpty(self::$git->branchAdd($branch = 'develop')->output());
        $this->assertStringStartsWith('Deleted branch', self::$git->branchDelete($branch)->output());

        // Adding another
        $this->assertEmpty(self::$git->branchAdd($branch = 'v1.2.3')->output());
        $this->assertStringStartsWith('Deleted branch', self::$git->branchDelete($branch)->output());

        // Adding another with a slash
        $this->assertEmpty(self::$git->branchAdd($branch = 'feature/a-new-one')->output());
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
        $this->assertEmpty(self::$git->branchAdd($branch)->output());
    }

    /**
     * @covers ::checkout
     * @covers ::output
     * @depends testAddBranch
     * @dataProvider branchesProvider
     */
    public function testCheckingOut($branch)
    {
        $this->assertEmpty(self::$git->checkout($branch)->output());
        $this->assertEmpty(self::$git->checkout($branch)->output());
    }

    /**
     * @covers ::status
     * @depends testCheckingOut
     */
    public function testDumpingStatus()
    {
        $this->assertContains('On branch', self::$git->status());
    }

    /**
     * @covers ::show
     * @depends testDumpingStatus
     */
    public function testShowing()
    {
        $this->assertContains('author', self::$git->show('master'));
        $this->assertContains('committer', self::$git->output());
        $this->assertContains('Initial commit.', self::$git->output());
        $this->assertContains('Giovanni Gioffreda', self::$git->output());
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
            $this->assertEmpty(self::$git->diff());
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
        $this->assertEquals(self::$git->log(), self::$git->run(array_merge(['log'], self::$git->getDefaults('log'))));
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

    /**
     * @covers ::flow
     * @covers \Gioffreda\Component\Git\GitFlow::init
     * @covers \Gioffreda\Component\Git\GitFlow::output
     */
    public function testInitializingGitFLow()
    {
        try {
            self::$git->run(['flow']);
        } catch (GitProcessException $gpe) {
            // if git flow is not installed, don't run this test
            return false;
        }

        $this->assertContains('master', self::$git->flow()->init()->output());
        $this->assertContains('develop', self::$git->flow()->output());
        $this->assertContains('feature/', self::$git->flow()->output());
        $this->assertContains('release/', self::$git->flow()->output());
        $this->assertContains('hotfix/', self::$git->flow()->output());
        $this->assertContains('support/', self::$git->flow()->output());

        // clearing test branches
        foreach ($this->branchesProvider() as $branch) try {
            self::$git->branchDelete($branch[0]);
        } catch (GitException $e) {
            // do nothing
        }

        return true;
    }

    /**
     * @covers \Gioffreda\Component\Git\GitFlow::featureStart
     * @covers \Gioffreda\Component\Git\GitFlow::featureList
     * @covers \Gioffreda\Component\Git\GitFlow::featureFinish
     * @depends testInitializingGitFLow
     */
    public function testGitFlowFeature($flowInstalled)
    {
        if (!$flowInstalled) {
            return false;
        }

        $this->assertContains('feature/test1', self::$git->flow()->featureStart('test1')->output());
        $this->assertContains('feature/test1', self::$git->status());
        self::$filesystem->touch(sprintf('%s/%s', self::$git->getPath(), sha1('feature/test1')));
        $this->assertContains('* test1', self::$git->flow()->featureList()->output());
        $this->assertContains('Summary of actions', self::$git->add('.')->commit('Lorem ipsum feature updated')->flow()->featureFinish('test1')->output());
        $this->assertContains('develop', self::$git->status());

        return true;
    }

    /**
     * @covers \Gioffreda\Component\Git\GitFlow::hotfixStart
     * @covers \Gioffreda\Component\Git\GitFlow::hotfixList
     * @covers \Gioffreda\Component\Git\GitFlow::hotfixFinish
     * @depends testGitFlowFeature
     */
    public function testGitFlowHotfix($flowInstalled)
    {
        if (!$flowInstalled) {
            return false;
        }

        $this->assertContains('hotfix/test1', self::$git->flow()->hotfixStart('test1')->output());
        $this->assertContains('hotfix/test1', self::$git->status());
        self::$filesystem->touch(sprintf('%s/%s', self::$git->getPath(), sha1('hotfix/test1')));
        $this->assertContains('* test1', self::$git->flow()->hotfixList()->output());
        $this->assertContains('Summary of actions', self::$git->add('.')->commit('Lorem ipsum hotfix updated')->flow()->hotfixFinish('test1', 'tag')->output());
        $this->assertContains('develop', self::$git->status());

        return true;
    }

    /**
     * @covers \Gioffreda\Component\Git\GitFlow::releaseStart
     * @covers \Gioffreda\Component\Git\GitFlow::releaseList
     * @covers \Gioffreda\Component\Git\GitFlow::releaseFinish
     * @depends testGitFlowHotfix
     */
    public function testGitFlowRelease($flowInstalled)
    {
        if (!$flowInstalled) {
            return false;
        }

        $this->assertContains('release/t1', self::$git->flow()->releaseStart('t1')->output());
        $this->assertContains('release/t1', self::$git->status());
        self::$filesystem->touch(sprintf('%s/%s', self::$git->getPath(), sha1('release/t1')));
        $this->assertContains('* t1', self::$git->flow()->releaseList()->output());
        $this->assertContains('Summary of actions', self::$git->add('.')->commit('Lorem ipsum release updated')->flow()->releaseFinish('t1', 'tag')->output());
        $this->assertContains('develop', self::$git->status());

        return true;
    }

    /**
     * @covers \Gioffreda\Component\Git\GitFlow::supportStart
     * @covers \Gioffreda\Component\Git\GitFlow::supportList
     * @depends testGitFlowHotfix
     */
    public function testGitFlowSupport($flowInstalled)
    {
        if (!$flowInstalled) {
            return false;
        }

        $this->assertContains('support/t1', self::$git->flow()->supportStart('t1', 'master')->output());
        $this->assertContains('support/t1', self::$git->status());
        self::$filesystem->touch(sprintf('%s/%s', self::$git->getPath(), sha1('support/t1')));
        $this->assertContains('* t1', self::$git->flow()->supportList()->output());

        return true;
    }

    /**
     * @covers \Gioffreda\Component\Git\Exception\GitProcessException
     * @expectedException \Gioffreda\Component\Git\Exception\GitProcessException
     * @depends testGitFlowSupport
     */
    public function testGitFlowRunException()
    {
        self::$git->flow()->run('wrong-command');
    }

    /* PROVIDERS */

    /**
     * @return array
     */
    public function branchesProvider()
    {
        return [
            ['feature/a_new_one'],
            ['feature/v1.2.3'],
            ['hotfix/bug-fixed-123'],
            ['hotfix/456']
        ];
    }

    /**
     * @return array
     */
    public function filesProvider()
    {
        return [
            ['a', true],
            ['b with spaces', true],
            ['c with-symbols._', true],
            ['d', false],
            ['e with spaces', false],
            ['f with_symbols.-', false]
        ];
    }

    /**
     * @return array
     */
    public function remotesProvider()
    {
        return [
            ['https://github.com/ggioffreda/git.git', 'ggioffreda/git.git']
        ];
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

    /**
     * @return string
     */
    public static function buildPath()
    {
        return sprintf('%s/%s%s', sys_get_temp_dir(), 'git-client-test-', sha1(mt_rand().mt_rand().time()));
    }

    public static function tearDownAfterClass()
    {
        self::getFilesystem()->remove(self::$git->getPath());
    }

}