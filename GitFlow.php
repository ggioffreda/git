<?php

namespace Gioffreda\Component\Git;

/**
 * This class provides a wrapper for the Git-FLow extension.
 *
 * @package Gioffreda\Component\Git
 */
class GitFlow
{

    const OPERATION_START = 'start';
    const OPERATION_FINISH  = 'finish';
    const OPERATION_LIST = 'list';

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

    /**
     * Initializes a git-flow project.
     *
     * @return $this
     */
    public function init()
    {
        return $this->run('init', '-d');
    }

    /**
     * Provides access to the git-flow feature functionality.
     *
     * @param $operation
     * @param string|null $name
     * @return $this
     */
    public function feature($operation, $name = null)
    {
        return $this->run('feature', $operation, $name);
    }

    /**
     * Provides access to the git-flow hotfix functionality.
     *
     * @param $operation
     * @param string|null $name
     * @return $this
     */
    public function hotfix($operation, $name = null)
    {
        return $this->run('hotfix', $operation, $name);
    }

    /**
     * Provides access to the git-flow release functionality.
     *
     * @param $operation
     * @param string|null $name
     * @return $this
     */
    public function release($operation, $name = null)
    {
        return $this->run('release', $operation, $name);
    }

    /**
     * Provides access to the git-flow support functionality.
     *
     * @param $operation
     * @param string|null $name
     * @return $this
     */
    public function support($operation, $name = null)
    {
        return $this->run('support', $operation, $name);
    }

    /**
     * Returns the output of the git-flow version command.
     *
     * @return mixed
     */
    public function version()
    {
        return $this->run('version')->output();
    }

    /**
     * Returns the output of the git-flow config command.
     *
     * @return $this
     */
    public function config()
    {
        return $this->run('config');
    }

    /**
     * Executes the given git-flow command.
     *
     * @param $context
     * @param string|null $operation
     * @param string|null $name
     * @return $this
     */
    public function run($context, $operation = null, $name = null)
    {
        $this->git->run(array_filter(array(
            'flow', $context, $operation, $name
        )));

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