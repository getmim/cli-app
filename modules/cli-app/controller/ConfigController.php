<?php
/**
 * Config controller
 * @package cli-app
 * @version 0.0.2
 */

namespace CliApp\Controller;

use Cli\Library\Bash;
use CliApp\Library\Config;

class ConfigController extends \CliApp\Controller
{

    public function generateAction(){
        $nl = PHP_EOL;
        $here = getcwd();
        if(!$this->isAppBase($here))
            Bash::error('Please run the command under exists application');
        
        Config::init($here);
        Bash::echo('Application config file regenerated');
    }
}