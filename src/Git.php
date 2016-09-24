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
    protected $outputs = [];

    const OPERATION_INIT = 'init';
    const OPERATION_ADD = 'add';
    const OPERATION_REMOVE = 'rm';
    const OPERATION_MOVE = 'mv';
    const OPERATION_COMMIT = 'commit';
    const OPERATION_BRANCH_ADD = 'branchAdd';
    const OPERATION_BRANCH_DELETE = 'branchDelete';
    const OPERATION_BRANCH_LIST = 'branchList';
    const OPERATION_CHECKOUT = 'checkout';
    const OPERATION_STATUS = 'status';
    const OPERATION_MERGE = 'merge';
    const OPERATION_LOG = 'log';
    const OPERATION_PULL = 'pull';
    const OPERATION_PUSH = 'push';
    const OPERATION_FETCH = 'fetch';
    const OPERATION_DIFF = 'diff';
    const OPERATION_SHOW = 'show';
    const OPERATION_CONFIG = 'config';
    const OPERATION_REMOTE_ADD = 'remoteAdd';
    const OPERATION_REMOTE_RENAME = 'remoteRename';
    const OPERATION_REMOTE_REMOVE = 'remoteRemove';
    const OPERATION_REMOTE_SET_HEAD = 'remoteSetHead';
    const OPERATION_REMOTE_SET_BRANCHES = 'remoteSetBranches';
    const OPERATION_REMOTE_GET_URL = 'remoteGetUrl';
    const OPERATION_REMOTE_SET_URL = 'remoteSetUrl';
    const OPERATION_REMOTE_SHOW = 'remoteShow';
    const OPERATION_REMOTE_PRUNE = 'remotePrune';

    /**
     * The default options for each of the base methods.
     *
     * List of the base methods: add, rm, commit, branchAdd, branchDelete, branchList, checkout, status, merge, log,
     * pull, push.
     *
     * @var array
     */
    protected $defaults = [
        self::OPERATION_ADD => [
            'strategy' => null
        ],
        self::OPERATION_REMOVE => [
            'strategy' => '--cached',
            'recursive' => '-r'
        ],
        self::OPERATION_BRANCH_DELETE => [
            'strategy' => '-D'
        ],
        self::OPERATION_BRANCH_LIST => [
            'verbosity' => '-vv',
            'color' => '--no-color',
            'abbreviation' => '--no-abbrev',
            'type' => '--all'
        ],
        self::OPERATION_STATUS => [
            'output' => null
        ],
        self::OPERATION_MERGE => [
            'commit' => '--no-commit',
            'strategy' => '--strategy=ours'
        ],
        self::OPERATION_LOG => [
            'limit' => '-n10'
        ],
        self::OPERATION_FETCH => [
            'remotes' => '--all'
        ],
        self::OPERATION_DIFF => [
            'color' => '--no-color'
        ],
        self::OPERATION_SHOW => [
            'format' => '--format=raw',
            'color' => '--no-color',
            'abbreviation' => '--no-abbrev-commit'
        ],
        self::OPERATION_REMOTE_SET_HEAD => [
            'auto' => '--auto'
        ],
    ];

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
        return isset($this->defaults[$command]) ? $this->defaults[$command] : [];
    }

    /**
     * Sets the default options for the specified command.
     *
     * @param $command
     * @param array $defaults
     * @return Git
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
        $builder = ProcessBuilder::create(is_array($command) ? array_filter($command) : [$command]);
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
     * @return Git
     */
    public function init()
    {
        if (!self::isInitialized($this->path)) {
            $this->runWithDefaults(self::OPERATION_INIT, []);
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
     * @return Git
     */
    public function config($var, $val = null, $global = false, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_CONFIG, array_filter(array_merge($options, [
            $global ? '--global' : null, $var, $val
        ])));

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
        $return = [];

        if ($output) {
            $lines = explode("\n", $output);
            foreach ($lines as $line) {
                $matches = [];
                if (preg_match('/^(?P<name>[^=]+)=(?P<value>.*)$/', $line, $matches)) {
                    $return[$matches['name']] = $matches['value'];
                } else {
                    throw new GitParsingOutputException(
                        sprintf('Unable to parse configuration "%s" from output "%s".', $line, $output)
                    );
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
     * @return Git
     */
    public function add($match, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_ADD, $options, $match);

        return $this;
    }

    /**
     * Removes a file not to be included in the next commit.
     *
     * @param $match
     * @param array $options
     * @return Git
     */
    public function rm($match, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_REMOVE, $options, $match);

        return $this;
    }

    /**
     * Move $origin to $destination
     *
     * @param string $origin
     * @param string $destination
     * @param array $options
     * @return Git
     */
    public function mv($origin, $destination, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_MOVE, array_merge($options, [
            $origin,
            $destination
        ]));

        return $this;
    }

    /**
     * Commits the changes.
     *
     * @param $message
     * @param array $options
     * @return Git
     */
    public function commit($message, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_COMMIT, array_merge(['-m', $message], $options));

        return $this;
    }

    /**
     * Adds a new branch.
     *
     * @param $branch
     * @param array $options
     * @return Git
     */
    public function branchAdd($branch, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_BRANCH_ADD, $options, $branch);

        return $this;
    }

    /**
     * Deletes an existing branch.
     *
     * @param $branch
     * @param array $options
     * @return Git
     */
    public function branchDelete($branch, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_BRANCH_DELETE, $options, $branch);

        return $this;
    }

    /**
     * Checks out a branch, a file or anything else allowed by Git.
     *
     * @param $id
     * @param array $options
     * @return Git
     */
    public function checkout($id, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_CHECKOUT, $options, $id);

        return $this;
    }

    /**
     * Lists the branches.
     *
     * @param array $options
     * @return mixed
     */
    public function branchList(array $options = [])
    {
        return $this->runWithDefaults(self::OPERATION_BRANCH_LIST, $options);
    }

    /**
     * Returns an array of branches including last commit hash and message for each one of them.
     *
     * @return array
     * @throws Exception\GitParsingOutputException
     */
    public function getBranches()
    {
        $output = trim($this->branchList([
            'verbosity' => '-vv',
            'color' => '--no-color',
            'abbreviation' => '--no-abbrev',
            'type' => '--all'
        ]));
        $return = [];

        if ($output) {
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                $matches = [];
                if (preg_match(
                    '/^(?P<branch>[^\s]+)\s+(?:->\s+)?(?P<hash>[^\s]+)\s*(?P<message>.*)?$/',
                    ltrim($line, " *\n"),
                    $matches
                )) {
                    $return[$matches['branch']] = [
                        'hash' => $matches['hash'],
                        'message' => isset($matches['message']) ? $matches['message'] : ''
                    ];
                } else {
                    throw new GitParsingOutputException(
                        sprintf('Unable to parse branch description "%s" from output "%s".', $line, $output)
                    );
                }
            }
        }

        return $return;
    }

    /**
     * Returns the output of "git show".
     *
     * @param string $what
     * @param array $options
     * @return string
     */
    public function show($what, array $options = [])
    {
        return $this->runWithDefaults(self::OPERATION_SHOW, array_merge(
            $options,
            is_array($what) ? $what : [$what]
        ));
    }

    /**
     * Returns the current status of the Git project.
     *
     * @param array $options
     * @return mixed
     */
    public function status(array $options = [])
    {
        return $this->runWithDefaults(self::OPERATION_STATUS, $options);
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
        $output = trim($this->status([
            'output' => '--porcelain'
        ]));
        $return = [];

        if ($output) {
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                $matches = [];
                if (preg_match('/^(?P<status>[^\s]+)\s+(?P<file>.+)$/', ltrim($line), $matches)) {
                    if (preg_match('/^".*"$/', $matches['file'])) {
                        $matches['file'] = json_decode($matches['file']);
                    }
                    $return[$matches['file']] = $matches['status'];
                } else {
                    throw new GitParsingOutputException(
                        sprintf('Unable to parse status description "%s" from output "%s".', $line, $output)
                    );
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
     * @return Git
     */
    public function merge($branch, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_MERGE, $options, $branch);

        return $this;
    }

    /**
     * Returns a log of the last commits. The default limit is st to the last 10 commits, to change this provide the
     * "limit" option and set it to your desired value (ie: "-n25").
     *
     * @param array $options
     * @return mixed
     */
    public function log(array $options = [])
    {
        return $this->runWithDefaults(self::OPERATION_LOG, $options);
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
            $output = trim($this->log([
                'limit' => sprintf('-n%s', (int) $limit),
                '--oneline',
                '--no-abbrev'
            ]));
        } catch (GitProcessException $e) {
            // there are no logs
            return [];
        }

        $return = [];

        if ($output) {
            $lines = explode("\n", $output);

            foreach ($lines as $line) {
                $matches = [];
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
    public function diff($match = null, array $options = [])
    {
        return $this->runWithDefaults(self::OPERATION_DIFF, array_merge($options, [
            $match
        ]));
    }

    /**
     * Pulls the changes from the remote repository.
     *
     * @param array $options
     * @return Git
     */
    public function pull(array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_PULL, $options);

        return $this;
    }

    /**
     * Pushes the changes to the remote repository.
     *
     * @param array $options
     * @return Git
     */
    public function push(array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_PUSH, $options);

        return $this;
    }

    /**
     * Fetches the remotes.
     *
     * @param array $options
     * @return Git
     */
    public function fetch(array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_FETCH, $options);

        return $this;
    }

    /**
     * Adds the given remote
     *
     * @param string $name
     * @param string $url
     * @param array $options
     * @return Git
     */
    public function remoteAdd($name, $url, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_REMOTE_ADD, $options, ['add', $name, $url]);

        return $this;
    }

    /**
     * Rename the given remote.
     *
     * @param string $oldName
     * @param string $newName
     * @return Git
     */
    public function remoteRename($oldName, $newName)
    {
        $this->runWithDefaults(self::OPERATION_REMOTE_RENAME, [], ['rename', $oldName, $newName]);

        return $this;
    }

    /**
     * Removes the remote.
     *
     * @param string $name
     * @return Git
     */
    public function remoteRemove($name)
    {
        $this->runWithDefaults(self::OPERATION_REMOTE_REMOVE, [], ['remove', $name]);

        return $this;
    }

    /**
     * Set the HEAD for the given repository.
     *
     * @param string $remoteName
     * @param array $options
     * @return Git
     */
    public function remoteSetHead($remoteName, array $options = [])
    {
        $this->runWithDefaults(self::OPERATION_REMOTE_SET_HEAD, $options, ['set-head', $remoteName]);

        return $this;
    }

    /**
     * Set the remote branches or adds them is the last parameter is set to `true`.
     *
     * @param string $name
     * @param string $branch
     * @param bool $add
     * @return Git
     */
    public function remoteSetBranches($name, $branch, $add = false)
    {
        $this->runWithDefaults(
            self::OPERATION_REMOTE_SET_BRANCHES,
            $add ? ['--add'] : [],
            array_merge(['set-branches', $name], (array) $branch)
        );

        return $this;
    }

    /**
     * Gets the remote URL or all of them if the last parameter is set to `true`. Setting the second parameter to `true`
     * will return the push URL instead of the fetch (default behaviour).
     *
     * @param string $name
     * @param bool $push
     * @param bool $all
     * @return string
     */
    public function remoteGetUrl($name, $push = false, $all = false)
    {
        $arguments = ['get-url', $name];
        if ($push) {
            array_push($arguments, '--push');
        }
        if ($all) {
            array_push($arguments, '--all');
        }

        return $this->runWithDefaults(self::OPERATION_REMOTE_GET_URL, [], $arguments);
    }

    /**
     * Set the remote URL, the push one if the last parameter is set to `true`.
     *
     * @param string $name
     * @param string $url
     * @param bool $push
     * @return Git
     */
    public function remoteSetUrl($name, $url, $push = false)
    {
        $arguments = ['set-url', $name, $url];
        if ($push) {
            array_push($arguments, '--push');
        }

        $this->runWithDefaults(self::OPERATION_REMOTE_SET_URL, [], $arguments);

        return $this;
    }

    /**
     * Show information about the given remote.
     *
     * @param string|null $name
     * @param bool $queryRemote
     * @return string
     */
    public function remoteShow($name = null, $queryRemote = false)
    {
        return $this->runWithDefaults(
            self::OPERATION_REMOTE_SHOW,
            [],
            array_merge(['show'], $name ? [$name] : [], $queryRemote ? ['-n'] : [])
        );
    }

    /**
     * Prune the remote.
     *
     * @param string $name
     * @param bool $dryRun
     * @return Git
     */
    public function remotePrune($name, $dryRun = false)
    {
        $this->runWithDefaults(
            self::OPERATION_REMOTE_PRUNE,
            [],
            array_merge(['prune', $name], $dryRun ? ['--dry-run'] : [])
        );

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

        $git->run(['clone', $remote, '.']);

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
        $this->outputs[] = [
            'command' => $command,
            'output' => $output
        ];

        return $output;
    }

    /**
     * Runs the specified method with the provided options and argument.
     *
     * @param $command
     * @param $options
     * @param string|array|null $argument
     * @return mixed
     */
    protected function runWithDefaults($command, $options, $argument = null)
    {
        return $this->run(array_merge(
            [self::$commands[$command]],
            $argument === null ? [] : (array) $argument,
            $this->getDefaults($command),
            $options
        ));
    }

    /**
     * Mapping of the basic methods with the Git commands.
     *
     * @var array
     */
    protected static $commands = [
        self::OPERATION_INIT => 'init',
        self::OPERATION_ADD => 'add',
        self::OPERATION_REMOVE => 'rm',
        self::OPERATION_MOVE => 'mv',
        self::OPERATION_COMMIT => 'commit',
        self::OPERATION_BRANCH_ADD => 'branch',
        self::OPERATION_BRANCH_DELETE => 'branch',
        self::OPERATION_BRANCH_LIST => 'branch',
        self::OPERATION_CHECKOUT => 'checkout',
        self::OPERATION_STATUS => 'status',
        self::OPERATION_MERGE => 'merge',
        self::OPERATION_LOG => 'log',
        self::OPERATION_PULL => 'pull',
        self::OPERATION_PUSH => 'push',
        self::OPERATION_FETCH => 'fetch',
        self::OPERATION_DIFF => 'diff',
        self::OPERATION_SHOW => 'show',
        self::OPERATION_CONFIG => 'config',
        self::OPERATION_REMOTE_ADD => 'remote',
        self::OPERATION_REMOTE_RENAME => 'remote',
        self::OPERATION_REMOTE_REMOVE => 'remote',
        self::OPERATION_REMOTE_SET_HEAD => 'remote',
        self::OPERATION_REMOTE_SET_BRANCHES => 'remote',
        self::OPERATION_REMOTE_GET_URL => 'remote',
        self::OPERATION_REMOTE_SET_URL => 'remote',
        self::OPERATION_REMOTE_SHOW => 'remote',
        self::OPERATION_REMOTE_PRUNE => 'remote'
    ];
}
