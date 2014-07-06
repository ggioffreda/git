Git Component
=============

This component helps interacting with the Git command line tool.

This tool is built using the Symfony Process Component (https://github.com/symfony/Process) but can be used in any
PHP projects. The requirements are specified in the composer.json file:

 * PHP version >=5.3.3
 * symfony/Process version >= 2.4
 * symfony/Filesystem version >= 2.2 (only for running the tests)

Installation
------------

From command line run:

    $ composer.phar require "ggioffreda/git" "dev-master"

Or add the following to your composer.json in the require section:

    "require": {
        ... other requirements ...,
        "ggioffreda/git": "dev-master"
    }

Usage
-----

The following example shows how to use the component:

```php
<?php

namespace MyNamespace;

use Gioffreda\Component\Git\Git;

class MyJob
{

    public function doSOmething($remoteUri, $localPath)
    {
        // cloning a remote repository
        $git = Git::cloneRemote($remoteUri, $localPath); // clones a remote repository into the local path
        $git->checkout('develop'); // switches to the develop branch
        // change some files and
        $git
            ->add('.') // adds all the files
            ->commit('Changed some files') // commits the changes to the develop branch
            ->checkout('master') // switches to the master branch
            ->merge('develop') // merges the develop branch into the master branch
            ->commit('Merged the changes into master.') // commits the changes into the master branch
            ->run(array('push', '--all')) // pushes the changes to the remote repository using a custom command line
        ;

        // or you can use a local one even if not initialized yet
        $git = Git::create($localPath, '/usr/local/custom/git'); // new Git project using a custom executable
        $git
            ->init() // this will initialize the Git project if not initialized already
            ->add('./src') // adds all the files in the folder ./src
            ->commit('Initial commit (only sources).') // commits the changes
        ;

        // retrieve information
        $logs = $git->getLogs(); // retrieves the last commits hashes and messages
        $branches = $git->getBranches() // retrieves the list of branches with latest commit hash and message
    }

}
```

Resources
---------

You can run the unit tests with the following command:

    $ cd path/to/Gioffreda/Component/Git/
    $ composer.phar install
    $ phpunit