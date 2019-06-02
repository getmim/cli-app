<?php
/**
 * Application controller
 * @package cli-app
 * @version 0.0.2
 */

namespace CliApp\Controller;

use Mim\Library\Fs;
use Cli\Library\Bash;
use CliApp\Library\{
    Module,
    Config
};

class ApplicationController extends \CliApp\Controller
{
    public function initAction(){
        $here = getcwd();
        if(Fs::scan($here))
            Bash::error('Current directory is not empty');
        
        if(!Module::install($here, 'core'))
            return;
        Config::init($here);
        
        Bash::echo('Blank application installed');
    }

    public function gitignoreAction(){
        $here = getcwd();
        if(!$this->isAppBase($here))
            Bash::error('Please run the command under exists application');

        Module::regenerateGitIgnoreDb($here);

        Bash::echo('GitIgnore file regenerated');
    }
}