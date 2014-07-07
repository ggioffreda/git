<?php

namespace Gioffreda\Component\Git;

/**
 * This class provides a wrapper for the Git-FLow extension.
 *
 * @package Gioffreda\Component\Git
 */
class GitFlow
{

    const CONTEXT_FEATURE = 'feature';
    const CONTEXT_RELEASE = 'release';
    const CONTEXT_HOTFIX = 'hotfix';
    const CONTEXT_SUPPORT = 'support';

    const OPERATION_LIST = 'list';
    const OPERATION_START = 'start';
    const OPERATION_FINISH  = 'finish';
    const OPERATION_PUBLISH = 'publish';
    const OPERATION_TRACK = 'track';
    const OPERATION_DIFF = 'diff';
    const OPERATION_REBASE = 'rebase';
    const OPERATION_CHECKOUT = 'checkout';
    const OPERATION_PULL = 'pull';

    /**
     * @var Git
     */
    protected $git;

    /**
     * Created a new instance for the given Git project.
     *
     * @param Git $git
     */
    protected function __construct(Git $git)
    {
        $this->git = $git;
    }

    // git-flow init

    /**
     * Initialize a new git repo with support for the branching model.
     *
     * @param bool $useDefaults use default branch names
     * @param bool $force force
     * @return GitFlow
     */
    public function init($useDefaults = true, $force = false)
    {
        return $this->run('init', array(
            $useDefaults ? '-d' : null,
            $force ? '-f' : null
        ));
    }

    // git-flow feature

    /**
     * Lists existing features.
     *
     * @param bool $verbose verbose (more) output
     * @return GitFlow
     */
    public function featureList($verbose = false)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_LIST,
            $verbose ? '-v' : null
        ));
    }

    /**
     * Start new feature $name, optionally basing it on $base instead of "develop"
     *
     * @param string $name
     * @param bool $fetch fetch from $ORIGIN before performing local operation
     * @param null $base
     * @return GitFlow
     */
    public function featureStart($name, $fetch = false, $base = null)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_START,
            $fetch ? '-F' : null,
            $name,
            $base ? $base : null
        ));
    }

    /**
     * Finish feature $name
     *
     * @param string $name
     * @param bool $fetch fetch from $ORIGIN before performing finish
     * @param bool $keep keep branch after performing finish
     * @param bool $rebase rebase instead of merge
     * @return GitFlow
     */
    public function featureFinish($name, $fetch = false, $keep = false, $rebase = false)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_FINISH,
            $fetch ? '-F' : null,
            $keep ? '-k' : null,
            $rebase ? '-r' : null,
            $name
        ));
    }

    /**
     * Start sharing feature $name on $ORIGIN
     *
     * @param string $name
     * @return $this
     */
    public function featurePublish($name)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_PUBLISH,
            $name
        ));
    }

    /**
     * Start tracking feature $name that is shared on $ORIGIN
     *
     * @param string $name
     * @return $this
     */
    public function featureTrack($name)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_TRACK,
            $name
        ));
    }

    /**
     * Show all changes in $name that are not in "develop"
     *
     * @param string $name
     * @return $this
     */
    public function featureDiff($name)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_DIFF,
            $name
        ));
    }

    /**
     * Rebase $name on "develop"
     *
     * @param string $name
     * @param bool $interactive do an interactive rebase
     * @return $this
     */
    public function featureRebase($name, $interactive = false)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_REBASE,
            $interactive ? '-i' : null,
            $name
        ));
    }

    /**
     * Switch to feature branch $name
     *
     * @param string $name
     * @return $this
     */
    public function featureCheckout($name)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_CHECKOUT,
            $name
        ));
    }

    /**
     * Pull feature $name from the remote repository
     *
     * @param string $name
     * @param string|null $remote
     * @return $this
     */
    public function featurePull($name, $remote = null)
    {
        return $this->run(self::CONTEXT_FEATURE, array(
            self::OPERATION_PULL,
            $name,
            $remote
        ));
    }

    // git-flow release

    /**
     * Lists existing releases
     *
     * @param bool $verbose verbose (more) output
     * @return $this
     */
    public function releaseList($verbose = false)
    {
        return $this->run(self::CONTEXT_RELEASE, array(
            self::OPERATION_LIST,
            $verbose ? '-v' : null
        ));
    }

    /**
     * Start new release named $version
     *
     * @param string $version
     * @param bool $fetch fetch from $ORIGIN before performing local operation
     * @return $this
     */
    public function releaseStart($version, $fetch = false)
    {
        return $this->run(self::CONTEXT_RELEASE, array(
            self::OPERATION_START,
            $version,
            $fetch ? '-F' : null
        ));
    }

    /**
     * Finish release $version
     *
     * @param string $version
     * @param null $message use the given tag message
     * @param bool $notag don't tag this release
     * @param bool $fetch fetch from $ORIGIN before performing finish
     * @param bool $keep keep branch after performing finish
     * @param bool $push push to $ORIGIN after performing finish
     * @param bool $sign sign the release tag cryptographically
     * @param null $key use the given GPG-key for the digital signature (implies $sign is true)
     * @return $this
     */
    public function releaseFinish($version, $message, $notag = true, $fetch = false, $keep = false, $push = false, $sign = false, $key = null)
    {
        return $this->run(self::CONTEXT_RELEASE, array(
            self::OPERATION_FINISH,
            $version,
            $fetch ? '-F' : null,
            $keep ? '-k' : null,
            $push ? '-p' : null,
            $sign ? '-s' : null,
            $key ? '-u' : null,
            $key,
            $message ? '-m' : null,
            $message,
            $notag ? '-n' : null
        ));
    }

    /**
     * Start sharing release $name on $ORIGIN
     *
     * @param string $name
     * @return $this
     */
    public function releasePublish($name)
    {
        return $this->run(self::CONTEXT_RELEASE, array(
            self::OPERATION_PUBLISH,
            $name
        ));
    }

    /**
     * Start tracking release $name that is shared on $ORIGIN
     *
     * @param string $name
     * @return $this
     */
    public function releaseTrack($name)
    {
        return $this->run(self::CONTEXT_RELEASE, array(
            self::OPERATION_TRACK,
            $name
        ));
    }

    // git-flow hotfix

    /**
     * Lists existing hotfixes
     *
     * @param bool $verbose verbose (more) output
     * @return $this
     */
    public function hotfixList($verbose = false)
    {
        return $this->run(self::CONTEXT_HOTFIX, array(
            self::OPERATION_LIST,
            $verbose ? '-v' : null
        ));
    }

    /**
     * Start new hotfix named $name, optionally base it on $base instead of "master"
     *
     * @param string $name
     * @param bool $fetch fetch from $ORIGIN before performing local operation
     * @param null $base
     * @return $this
     */
    public function hotfixStart($name, $fetch = false, $base = null)
    {
        return $this->run(self::CONTEXT_HOTFIX, array(
            self::OPERATION_START,
            $fetch ? '-F' : null,
            $name,
            $base ? $base : null
        ));
    }

    /**
     * Finish hotfix $name
     *
     * @param string $name
     * @param string $message
     * @param bool $notag don't tag this release
     * @param bool $fetch fetch from $ORIGIN before performing finish
     * @param bool $keep keep branch after performing finish
     * @param bool $push push to $ORIGIN after performing finish
     * @param bool $sign sign the release tag cryptographically
     * @param null $key use the given GPG-key for the digital signature (implies $sign is true)
     * @return $this
     */
    public function hotfixFinish($name, $message, $notag = true, $fetch = false, $keep = false, $push = false, $sign = false, $key = null)
    {
        return $this->run(self::CONTEXT_HOTFIX, array(
            self::OPERATION_FINISH,
            $name,
            $fetch ? '-F' : null,
            $keep ? '-k' : null,
            $push ? '-p' : null,
            $sign ? '-s' : null,
            $key ? '-u' : null,
            $key,
            $message ? '-m' : null,
            $message,
            $notag ? '-n' : null
        ));
    }

    // git-flow support

    /**
     * Lists existing support branches
     *
     * @param bool $verbose verbose (more) output
     * @return $this
     */
    public function supportList($verbose = false)
    {
        return $this->run(self::CONTEXT_SUPPORT, array(
            self::OPERATION_LIST,
            $verbose ? '-v' : null
        ));
    }

    /**
     * Start new support branch named $version based on $base
     *
     * @param string $version
     * @param null $base
     * @param bool $fetch fetch from $ORIGIN before performing local operation
     * @return $this
     */
    public function supportStart($version, $base, $fetch = false)
    {
        return $this->run(self::CONTEXT_SUPPORT, array(
            self::OPERATION_START,
            $fetch ? '-F' : null,
            $version,
            $base
        ));
    }

    /**
     * Returns the output of the git-flow version command.
     *
     * @return mixed
     */
    public function getVersion()
    {
        return $this->run('version')->output();
    }

    /**
     * Returns the output of the git-flow config command.
     *
     * @return $this
     */
    public function getConfiguration()
    {
        return $this->run('config')->output();
    }

    /**
     * Executes the given git-flow command.
     *
     * @param $context
     * @param array $options
     * @return $this
     */
    public function run($context, array $options = array())
    {
        $this->git->run(array_filter(array_merge(array(
            'flow',
            $context
        ), $options)));

        return $this;
    }

    /**
     * Returns the output for the last executed command.
     *
     * @return null|string
     */
    public function output()
    {
        return $this->git->output();
    }

    /**
     * Returns a new instance of the git-flow wrapper for the given Git project.
     *
     * @param Git $git
     * @return GitFlow
     */
    public static function extend(Git $git)
    {
        return new GitFlow($git);
    }

    /**
     * Returns the Git project for this extension.
     *
     * @return Git
     */
    public function end()
    {
        return $this->git;
    }

}