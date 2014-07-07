Git Component
=============

This component helps interacting with the Git command line tool and the Git-Flow extension.

This tool is built using the Symfony Process Component (https://github.com/symfony/Process) but can be used in any
PHP projects. The requirements are specified in the composer.json file:

 * [PHP](http://www.php.net/) version >=5.3.3
 * [symfony/Process](http://symfony.com/doc/current/components/process.html) version >= 2.4
 * [symfony/Filesystem](http://symfony.com/doc/current/components/filesystem.html) version >= 2.2 (only for running the tests in development environment)
 * [git](http://git-scm.com/)
 * [git-flow](https://github.com/nvie/gitflow) (required only for using the Git-Flow wrapper extension)

[![Build Status](https://travis-ci.org/ggioffreda/git.svg?branch=master)](https://travis-ci.org/ggioffreda/git)

Installation
------------

This component is available for installation through [composer](https://getcomposer.org/). From command line run:

    $ composer.phar require "ggioffreda/git" "dev-master"

Or add the following to your composer.json in the require section:

    "require": {
        ... other requirements ...,
        "ggioffreda/git": "dev-master"
    }

For more information check the project page on [Packagist](https://packagist.org/packages/ggioffreda/git).

Usage - Git
-----------

The list of shortcut and self-explanatory methods implemented for *Git*:

 * **init**, initializes the Git repository if not already initialized;
 * **config**, sets or requests a configuration variable, when getting it you need to call the **output** method afterward to get the value;
 * **add**, adds the files matching the provided pattern to the next commit;
 * **rm**, removes the files matching the provided pattern to the next commit;
 * **diff**, returns the output of the diff command on the whole project or for the specified pattern;
 * **commit**, commits the changes to the current branch;
 * **branchAdd**, creates a new branch with the given name;
 * **branchDelete**, deletes the branch with the given name;
 * **branchList**, lists the branches of the Git repository;
 * **checkout**, checks out the required entity (can be anything allowed by Git, like a branch, a file or an hash);
 * **status**, returns the output of the Git status command on the Git project;
 * **merge**, merges the given branch to the current one;
 * **log**, returns the output of the Git log command, by default the last 10 commits;
 * **push**, pushes the commits to the remote repository;
 * **pull**, pulls the commits from the remote repository;
 * **fetch**, fetches the remote branches;
 * **flow**, access the git-flow wrapper extension.

List of other methods provided for *Git*:

 * **run**, allows you to run any custom Git command you could think of;
 * **getConfiguration**, return an array of Git configuration key and values;
 * **getBranches**, returns an array of branches with related last commit hash and message;
 * **getStatuses**, returns an array of non commited changes with a status each (in "porcelain" Git flavour);
 * **getLogs**, returns the array of last commits with related messages, you can specify the size of the array;
 * **output**, returns the output of the last command executed on the Git project;
 * **history**, returns the array of all the commands and related outputs executed on the Git project.

List of static methods and basic features for *Git*:

 * **create** (static), returns a new instance for the specified path and Git executable (optional);
 * **cloneRemote** (static), returns a new instance cloning the remote repository in the local path;
 * **getPath**, returns the path of the Git project;
 * **getDefaults**, returns the default options for the shortcut method (see above the list of shortcut methods);
 * **setDefaults**, sets the default options for the shortcut method (see above the list of shortcut methods).

When the execution of a Git command fails because of wrong options or for unknown reasons the any method can return a
*Gioffreda\Component\Git\Exception\GitProcessException*, while if the error happens parsing the output of the command
the exception will be of *Gioffreda\Component\Git\Exception\GitParsingOutputException*. Both share the same parent so
they can be caught at once if needed *Gioffreda\Component\Git\Exception\GitException*.

Usage - GitFlow
---------------

List of shortcut and self-explanatory methods implemented for *Git-Flow*:

 * **init**, initialized git-flow for the Git project with the default values, to use custom values call the **run** method instead providing the desired options;
 * **featureList**, lists existing features;
 * **featureStart**, starts a new feature, optionally basing it on another base instead of "develop";
 * **featureFinish**, finishes the feature;
 * **featurePublish**, starts sharing the feature;
 * **featureTrack**, starts tracking the feature;
 * **featureDiff**, shows all changes that are not in "develop";
 * **featureRebase**, rebases on "develop";
 * **featureCheckout**, switches to the feature branch;
 * **featurePull**, pull the feature from the remote repository;
 * **releaseList**, lists existing releases;
 * **releaseStart**, start a new release;
 * **releaseFinish**, finishes the release;
 * **releasePublish**, starts sharing the release;
 * **releaseTrack**, starts tracking the release;
 * **hotfixList**, lists existing hotfixes;
 * **hotfixStart**, start a new hotfix, optionally basing it on a different base than "master";
 * **hotfixFinish**, finishes the hotfix;
 * **supportList**, lists existing support branches;
 * **supportStart**, starts a new support branch;
 * **getConfiguration**, returns the git-flow current configuration;
 * **getVersion**, returns the git-flow current version;
 * **run**, allows you to run any custom command through git-flow;
 * **output**, returns the output for the last executed command, it's an alias for the parent Git::output() method;
 * **extend** (static), return the git-flow extension wrapper for the given Git project.

Beware if the git-flow extension is not available all the above methods (but **output** and **extend**) will throw an
exception.

Example
-------

The following example shows how to use the component. All non getter methods not used to read properties or command
output implement a fluent interface to improve readability:

```php
<?php

namespace MyNamespace;

use Gioffreda\Component\Git\Git;

class MyJob
{

    public function doSomething($remoteUri, $localPath)
    {
        // cloning a remote repository
        $git = Git::cloneRemote($remoteUri, $localPath);
        // switches to the develop branch
        $git->checkout('develop');

        // your logic here, change some files
        // ...

        $git
            // adds all the files
            ->add('.')
            // commits the changes to the develop branch
            ->commit('Changed some files')
            // switches to the master branch
            ->checkout('master')
            // merges the develop branch into the master branch
            ->merge('develop')
            // commits the changes into the master branch
            ->commit('Merged the changes into master.')
            // pushes the changes to the remote repository using a custom command line
            ->run(array('push', '--all'))
        ;

        // or you can use a local one even if not initialized yet
        // new Git project using a custom executable
        $git = Git::create($localPath, '/usr/local/custom/git');
        $git
            // this will initialize the Git project if not initialized already
            ->init()
            // adds all the files in the folder ./src
            ->add('./src')
            // commits the changes
            ->commit('Initial commit (only sources).')
        ;

        // retrieves the last commits hashes and messages
        $logs = $git->getLogs();
        // retrieves the list of branches with latest commit hash and message
        $branches = $git->getBranches()

        // using git-flow, initializing it first
        $git->flow()->init();
        // starts a new feature
        $git->flow()->featureStart('test1');

        // your logic here, change some files
        // ...

        $git
            // mark the changes so they'll be committed
            ->add('.')
            // commits the the changes
            ->commit('feature finished')
            // finishes the feature
            ->flow()->featureFinish('test1')
        ;
    }

}
```

Resources
---------

You can run the unit tests with the following command (requires [phpunit](http://phpunit.de/)):

    $ cd path/to/Gioffreda/Component/Git/
    $ composer.phar install
    $ phpunit

License
-------

This software is distributed under the following licenses: [GNU GPL v2](LICENSE_GPLv2.md),
[GNU GPL v3](LICENSE_GPLv3.md) and [MIT License](LICENSE_MIT.md). You may choose the one that best suits your needs.