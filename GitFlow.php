<?php

namespace Gioffreda\Component\Git;

class GitFlow
{

    const OPERATION_START = 'start';
    const OPERATION_FINISH  = 'finish';
    const OPERATION_LIST = 'list';

    /**
     * @var Git
     */
    protected $git;

    protected function __construct(Git $git)
    {
        $this->git = $git;
    }

    public function init()
    {
        return $this->run('init', '-d');
    }

    public function feature($operation, $name = null)
    {
        return $this->run('feature', $operation, $name);
    }

    public function hotfix($operation, $name = null)
    {
        return $this->run('hotfix', $operation, $name);
    }

    public function release($operation, $name = null)
    {
        return $this->run('release', $operation, $name);
    }

    public function support($operation, $name = null)
    {
        return $this->run('support', $operation, $name);
    }

    public function version()
    {
        return $this->run('version')->output();
    }

    public function config()
    {
        return $this->run('config');
    }

    public function run($context, $operation = null, $name = null)
    {
        $this->git->run(array_filter(array(
            'flow', $context, $operation, $name
        )));

        return $this;
    }

    public function output()
    {
        return $this->git->output();
    }

    public static function extend(Git $git)
    {
        return new GitFlow($git);
    }

}