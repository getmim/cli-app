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
    Apps,
    Module,
    Config
};

class ApplicationController extends \CliApp\Controller
{
    public function envAction(){
        $here = getcwd();
        if(!$this->isAppBase($here))
            Bash::error('Please run the command under exists application');

        $target = trim($this->req->param->target);

        $env_file = $here . '/etc/.env';
        $f = fopen($env_file, 'w');
        fwrite($f, $target);
        fclose($f);

        // now run reconfig
        Config::init($here);

        Bash::echo('Application env changes to ' . $target);
    }

    public function gitignoreAction(){
        $here = getcwd();
        if(!$this->isAppBase($here))
            Bash::error('Please run the command under exists application');

        Module::regenerateGitIgnoreDb($here);

        Bash::echo('GitIgnore file regenerated');
    }

    public function initAction(){
        $here = getcwd();
        if(Fs::scan($here))
            Bash::error('Current directory is not empty');
        
        if(!Module::install($here, 'core'))
            return;
        Config::init($here);
        
        Bash::echo('Blank application installed');
    }

    public function listAction(){
        $apps = Apps::getAll();
        if(!$apps)
            return;

        $fapps = null;
        $max_host_len = 0;
        foreach($apps as $host => $path){
            $crn_host_len = strlen($host);
            if($crn_host_len > $max_host_len)
                $max_host_len = $crn_host_len;
            $apps[$host] = explode('/', $path);
            if(!$fapps)
                $fapps = $apps[$host];
        }

        $base_path = [];
        foreach($fapps as $findex => $fpar){
            $match_all = true;
            foreach($apps as $host => $dirs){
                if(isset($dirs[$findex]) && $dirs[$findex] === $fpar)
                    continue;
                $match_all = false;
                break;
            }

            if(!$match_all)
                break;

            $base_path[] = $fpar;
            foreach($apps as $host => &$dirs)
                unset($dirs[$findex]);
            unset($dirs);
        }
        
        ksort($apps);
        $max_host_len++;
        Bash::echo(implode('/', $base_path));
        foreach($apps as $host => $path)
            Bash::echo('- ' . str_pad($host, $max_host_len, ' ') . ': .../' . implode('/', $path));
    }

    public function toAction(){
        $apps = Apps::getAll();
        if(!$apps)
            return;

        $host = $this->req->param->host;
        if(!isset($apps[$host]))
            return Bash::error('App dir not found');

        $path = $apps[$host];
        if(!is_dir($path)){
            Apps::remove($host);
            return Bash::error('App is not there anymore');
        }

        $cmd = '> cd ' . $path . ' && exec "$SHELL"';

        Bash::echo($cmd);
    }
}