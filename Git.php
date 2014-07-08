<?php

namespace Gioffreda\Component\Git;

use Gioffreda\Component\Git\Exception\GitParsingOutputException;
use Gioffreda\Component\Git\Exception\GitProcessException;
use Symfony\Component\Process\ProcessBuilder;

/**
 * This class provides an easy interface to the Git command line tool.
 *
 * @package Gioffreda\Component\Git
 */
class Git
{

    /**
     * The default Git command line executable.
     */
    const GIT_BIN = 'git';

    /**
     * The Git command line executable.
     *
     * @var string
     */
    protected $git;

    /**
     * The path to the Git project.
     *
     * @var string
     */
    protected $path;

    /**
     * The git-flow extension wrapper, it's initialized on request the first time the extension is used.
     *
     * @var GitFlow
     */
    protected $flow;

    /**
     * The history of the commands and the related output messages.
     *
     * @var array
     */
    protected $outputs = array();

    /**
     * The default options for each of the base methods.
     *
     * List of the base methods: add, rm, commit, branchAdd, branchDelete, branchList, checkout, status, merge, log,
     * pull, push.
     *
     * @var array
     */
    protected $defaults = array(
        'add' => array(
            'strategy' => null
        ),
        'rm' => array(
            'strategy' => '--cached',
            'recursive' => '-r'
        ),
        'commit' => array(),
        'branchAdd' => array(),
        'branchDelete' => array(
            'strategy' => '-D'
        ),
        'branchList' => array(
            'verbosity' => '-vv',
            'color' => '--no-color',
            'abbreviation' => '--no-abbrev',
            'type' => '--all'
        ),
        'checkout' => array(),
        'status' => array(
            'output' => null
        ),
        'merge' => array(
            'commit' => '--no-commit',
            'strategy' => '--strategy=ours'
        ),
        'log' => array(
            'limit' => '-n10'
        ),
        'pull' => array(),
        'push' => array(),
        'fetch' => array(
            'remotes' => '--all'
        ),
        'diff' => array(
            'color' => '--no-color'
        ),
        'config' => array()
    );

    /**
     * Creates a new instance in the specified path that uses the provided Git command line executable.
     *
     * @param $path
     * @param $git
     */
    protected function __construct($path, $git)
    {
        $this->git = $git;
        $this->path = $path;
    }

    /**
     * Returns the path of this Git project.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Returns the default options for the specified command.
     *
     * @param $command
     * @return array
     */
    public function getDefaults($command)
    {
        return isset($this->defaults[$command]) ? $this->defaults[$command] : array();
    }

    /**
     * Sets the default options for the specified command.
     *
     * @param $command
     * @param array $defaults
     * @return $this
     */
    public function setDefaults($command, array $defaults)
    {
        $this->defaults[$command] = $defaults;

        return $this;
    }

    /**
     * Runs a specific Git command on the project.
     *
     * @param $command
     * @param null $callback
     * @return mixed
     * @throws Exception\GitProcessException
     */
    public function run($command, $callback = null)
    {
        $builder = ProcessBuilder::create(is_array($command) ? array_filter($command) : array($command));
        $builder->setPrefix($this->git);
        $builder->setWorkingDirectory($this->path);
        $builder->setTimeout(null);
        $process = $builder->getProcess();
        $process->run($callback);

        if (!$process->isSuccessful()) {
            throw new GitProcessException($process->getErrorOutput());
        }

        return $this->logCommand($command, $process->getOutput());
    }

    /**
     * Initializes the Git project if it has not been initialized already.
     *
     * @return $this
     */
    public function init()
    {
        if (!self::isInitialized($this->path)) {
            $this->run('init');
        }

        return $this;
    }

    /**
     * Sets or requests a configuration variable for Git. Can be set as global or only for the local project.
     *
     * @param $var
     * @param null $val
     * @param bool $global
     * @param array $options
     * @return $this
     */
    public function config($var, $val = null, $global = false, array $options = array())
    {
        $this->runWithDefaults('config', array_filter(array_merge($options, array(
            $global ? '--global' : null, $var, $val
        ))));

        return $this;
    }

    /**
     * Returns an array of Git configuration variables.
     *
     * @return array
     * @throws Exception\GitParsingOutputException
     */
    public function getConfiguration()
    {
        $output = trim($this->config('-l')->output());
        $return = array();

        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line)
            {
                $matches = array();
                if (preg_match('/^(?P<name>[^=]+)=(?P<value>.*)$/', $line, $matches)) {
                    $return[$matches['name']] = $matches['value'];
                } else {
                    throw new GitParsingOutputException(sprintf('Unable to parse configuration "%s" from output "%s".', $line, $output));
                }
            }
        }

        return $return;
    }

    /**
     * Adds a file to be included in the next commit.
     *
     * @param $match
     * @param array $options
     * @return $this
     */
    public function add($match, array $options = array())
    {
        $this->runWithDefaults('add', $options, $match);

        return $this;
    }

    /**
     * Removes a file not to be included in the next commit.
     *
     * @param $match
     * @param array $options
     * @return $this
     */
    public function rm($match, array $options = array())
    {

        $this->runWithDefaults('rm', $options, $match);

        return $this;
    }

    /**
     * Commits the changes.
     *
     * @param $message
     * @param array $options
     * @return $this
     */
    public function commit($message, array $options = array())
    {
        $this->runWithDefaults('commit', array_merge(array('-m', $message), $options));

        return $this;
    }

    /**
     * Adds a new branch.
     *
     * @param $branch
     * @param array $options
     * @return $this
     */
    public function branchAdd($branch, array $options = array())
    {
        $this->runWithDefaults('branchAdd', $options, $branch);

        return $this;
    }

    /**
     * Deletes an existing branch.
     *
     * @param $branch
     * @param array $options
     * @return $this
     */
    public function branchDelete($branch, array $options = array())
    {
        $this->runWithDefaults('branchDelete', $options, $branch);

        return $this;
    }

    /**
     * Checks out a branch, a file or anything else allowed by Git.
     *
     * @param $id
     * @param array $options
     * @return $this
     */
    public function checkout($id, array $options = array())
    {
        $this->runWithDefaults('checkout', $options, $id);

        return $this;
    }

    /**
     * Lists the branches.
     *
     * @param array $options
     * @return mixed
     */
    public function branchList(array $options = array())
    {
        return $this->runWithDefaults('branchList', $options);
    }

    /**
     * Returns an array of branches including last commit hash and message for each one of them.
     *
     * @return array
     * @throws Exception\GitParsingOutputException
     */
    public function getBranches()
    {
        $output = trim($this->branchList(array(
            'verbosity' => '-vv',
            'color' => '--no-color',
            'abbreviation' => '--no-abbrev',
            'type' => '--all'
        )));
        $return = array();

        if ($output) {
            $lines = explode("\n", $output);

            foreach ($lines as $line)
            {
                $matches = array();
                if (preg_match('/^(?P<branch>[^\s]+)\s+(?:->\s+)?(?P<hash>[^\s]+)\s*(?P<message>.*)?$/', ltrim($line, " *\n"), $matches)) {
                    $return[$matches['branch']] = array(
                        'hash' => $matches['hash'],
                        'message' => isset($matches['message']) ? $matches['message'] : ''
                    );
                } else {
                    throw new GitParsingOutputException(sprintf('Unable to parse branch description "%s" from output "%s".', $line, $output));
                }
            }
        }

        return $return;
    }

    /**
     * Returns the current status of the Git project.
     *
     * @param array $options
     * @return mixed
     */
    public function status(array $options = array())
    {
        return $this->runWithDefaults('status', $options);
    }

    /**
     * Returns an array of statuses where the filename (including the relative path to the project) is the key and the
     * value is the porcelain Git status for that particular file. See the Git documentation for possible values.
     *
     * @return array
     * @throws Exception\GitParsingOutputException
     */
    public function getStatuses()
    {
        $output = trim($this->status(array(
            'output' => '--porcelain'
        )));
        $return = array();

        if ($output) {
            $lines = explode("\n", $output);

            foreach ($lines as $line)
            {
                $matches = array();
                if (preg_match('/^(?P<status>[^\s]+)\s+(?P<file>.+)$/', ltrim($line), $matches)) {
                    if (preg_match('/^".*"$/', $matches['file'])) {
                        $matches['file'] = json_decode($matches['file']);
                    }
                    $return[$matches['file']] = $matches['status'];
                } else {
                    throw new GitParsingOutputException(sprintf('Unable to parse status description "%s" from output "%s".', $line, $output));
                }
            }
        }

        return $return;
    }

    /**
     * Merges the specified branch in the current branch but does not commits by default.
     *
     * @param $branch
     * @param array $options
     * @return $this
     */
    public function merge($branch, array $options = array())
    {
        $this->runWithDefaults('merge', $options, $branch);

        return $this;
    }

    /**
     * Returns a log of the last commits. The default limit is st to the last 10 commits, to change this provide the
     * "limit" option and set it to your desired value (ie: "-n25").
     *
     * @param array $options
     * @return mixed
     */
    public function log(array $options = array())
    {
        return $this->runWithDefaults('log', $options);
    }

    /**
     * Returns the last commits. The key will be the hash of the commit and the value is the commit message.
     *
     * @param int $limit
     * @return array
     * @throws Exception\GitParsingOutputException
     */
    public function getLogs($limit = 10)
    {
        try {
            $output = trim($this->log(array(
                'limit' => sprintf('-n%s', (int) $limit),
                '--oneline',
                '--no-abbrev'
            )));
        } catch (GitProcessException $e) {
            // there are no logs
            return array();
        }

        $return = array();

        if ($output) {
            $lines = explode("\n", $output);

            foreach ($lines as $line)
            {
                $matches = array();
                if (preg_match('/^(?P<hash>[^\s]+)\s+(?P<message>.*)$/', ltrim($line), $matches)) {
                    $return[$matches['hash']] = $matches['message'];
                } else {
                    throw new GitParsingOutputException(sprintf('Unable to parse log "%s".', $line));
                }
            }
        }

        return $return;
    }

    /**
     * Returns the output of the diff command on the whole directory or on the specified pattern.
     *
     * @param string|null $match
     * @param array $options
     * @return mixed
     */
    public function diff($match = null, array $options = array())
    {
        return $this->runWithDefaults('diff', array_merge($options, array(
            $match
        )));
    }

    /**
     * Pulls the changes from the remote repository.
     *
     * @param array $options
     * @return $this
     */
    public function pull(array $options = array())
    {
        $this->runWithDefaults('pull', $options);

        return $this;
    }

    /**
     * Pushes the changes to the remote repository.
     *
     * @param array $options
     * @return $this
     */
    public function push(array $options = array())
    {
        $this->runWithDefaults('push', $options);

        return $this;
    }

    /**
     * Fetches the remotes.
     *
     * @param array $options
     * @return $this
     */
    public function fetch(array $options = array())
    {
        $this->runWithDefaults('fetch', $options);

        return $this;
    }

    /**
     * Returns the output of the last command executed, if available. It can be null.
     *
     * @return string|null
     */
    public function output()
    {
        return count($this->outputs) ? $this->outputs[count($this->outputs) - 1]['output'] : null;
    }

    /**
     * Returns the history of commands executed and related outputs.
     *
     * @return array
     */
    public function history()
    {
        return $this->outputs;
    }

    /**
     * Return the git-flow extension wrapper.
     *
     * @return GitFlow
     */
    public function flow()
    {
        if (!$this->flow) {
            $this->flow = GitFlow::extend($this);
        }

        return $this->flow;
    }

    /**
     * Create a Git project in the specified path, using the provided executable.
     *
     * @param $path
     * @param string $gitBin
     * @return Git
     */
    public static function create($path, $gitBin = self::GIT_BIN)
    {
        return new Git($path, $gitBin);
    }

    /**
     * Clone provided remote repository in a local Git project using the specified executable.
     *
     * @param $remote
     * @param $path
     * @param string $gitBin
     * @return Git
     */
    public static function cloneRemote($remote, $path, $gitBin = self::GIT_BIN)
    {
        $git = new Git($path, $gitBin);

        $git->run(array('clone', $remote, '.'));

        return $git;
    }

    /**
     * Checks if the repository has been initialized, basically looks for a folder called ".git".
     *
     * @param $path
     * @return bool
     */
    public static function isInitialized($path)
    {
        $gitPath = "$path/.git";

        return file_exists($gitPath) && is_dir($gitPath);
    }

    /**
     * Adds the command and related output to the history.
     *
     * @param $command
     * @param $output
     * @return mixed
     */
    protected function logCommand($command, $output)
    {
        $this->outputs[] = array(
            'command' => $command,
            'output' => $output
        );

        return $output;
    }

    /**
     * Runs the specified method with the provided options and argument.
     *
     * @param $command
     * @param $options
     * @param null $argument
     * @return mixed
     */
    protected function runWithDefaults($command, $options, $argument = null)
    {
        return $this->run(array_merge(
            $argument === null ? array(self::$commands[$command]) : array(self::$commands[$command], $argument),
            $this->defaults[$command],
            $options
        ));
    }

    /**
     * Mapping of the basic methods with the Git commands.
     *
     * @var array
     */
    protected static $commands = array(
        'add'          => 'add',
        'rm'           => 'rm',
        'commit'       => 'commit',
        'branchAdd'    => 'branch',
        'branchDelete' => 'branch',
        'branchList'   => 'branch',
        'checkout'     => 'checkout',
        'status'       => 'status',
        'merge'        => 'merge',
        'log'          => 'log',
        'pull'         => 'pull',
        'push'         => 'push',
        'fetch'        => 'fetch',
        'diff'         => 'diff',
        'config'       => 'config'
    );

}