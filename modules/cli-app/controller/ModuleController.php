<?php
/**
 * Module controller
 * @package cli-app
 * @version 0.0.5
 */

namespace CliApp\Controller;

use Cli\Library\Bash;
use \CliApp\Library\{
    Module,
    Config
};

class ModuleController extends \CliApp\Controller
{
    private function filterArgModules(array $modules): array{
        $result = [];

        $module_db_file = getcwd() . '/etc/modules.php';
        if(!is_file($module_db_file))
            Bash::error('Please run the command under exists application');
        $module_db = include $module_db_file;
        
        foreach($modules as $module){
            if($module === '-'){
                foreach($module_db as $name => $uri)
                    $result[$name] = $uri;
            }else{
                $result[$module] = $module_db[$module] ?? null;
            }
        }
        
        return $result;
    }
    
    public function indexAction(){
        $here = getcwd();
        // expected modules file
        $module_file = $here . '/etc/modules.php';
        $module_dir  = $here . '/modules';
        
        if(!$this->isAppBase($here))
            Bash::error('Please run the command under exists application');
        
        $modules = include $module_file;
        
        $printed = [];

        ksort($modules);
        
        foreach($modules as $name => $repo){
            $mod_conf = $module_dir . '/' . $name . '/config.php';
            $version = '';
            if(!is_file($mod_conf))
                $version = '(Not installed)';
            else{
                $mod_config = include $mod_conf;
                $version = $mod_config['__version'];
            }
            
            $printed[] = $name;
            Bash::echo(' - ' . $name . ' ' . $version);
        }
        
        $dirs = \Mim\Library\Fs::scan($module_dir);
        foreach($dirs as $dir){
            if(in_array($dir, $printed))
                continue;
            $mod_dir = $module_dir . '/' . $dir;
            if(!is_dir($mod_dir))
                continue;
            
            Bash::echo(' - ' . $dir . ' (Not registered)');
        }
    }
    
    public function installAction(){
        $arg_modules = $this->req->param->modules ?? ['-'];
        $modules = $this->filterArgModules($arg_modules);
        if(!$modules)
            Bash::error('No module to process');

        $ignore_dev = false;
        if($arg_modules && $arg_modules[0] === '-')
            $ignore_dev = true;
        
        $here = getcwd();
        
        // install all selected modules
        foreach($modules as $name => $uri){
            $module_config_file = null;
            
            if(!is_null($uri) || preg_match('!^[a-z0-9-]+$!', $name))
                $module_config_file = $here . '/modules/' . $name . '/config.php';
            
            if($module_config_file && is_file($module_config_file)){
                Bash::echo('Module `' . $name . '` already there, skip installation');
                continue;
            }
            
            if($uri === '~'){
                Bash::echo('Module `' . $name . '` is local module. Skipping...');
                continue;
            }
            
            if(!Module::install($here, $name, $uri, $ignore_dev))
                return;
        }
        
        Config::init($here);
        
        Bash::echo('The module(s) successfully installed');
    }
    
    public function updateAction(){
        $arg_modules = $this->req->param->modules ?? ['-'];
        $modules = $this->filterArgModules($arg_modules);
        if(!$modules)
            Bash::error('No module to process');

        $ignore_dev = false;
        if($arg_modules && $arg_modules[0] === '-')
            $ignore_dev = true;

        $here = getcwd();
        
        // update all selected modules
        foreach($modules as $name => $uri){
            $module_config_file = $here . '/modules/' . $name . '/config.php';
            if(!is_file($module_config_file)){
                Bash::error('Module `' . $name . '` is not installed. Skipping...', false);
                continue;
            }

            if (substr($uri, 0, 1) === '/') {
                $uri = '~';
            }

            if(!Module::update($here, $name, $uri, $ignore_dev)) {
                return;
            }
        }
        
        Config::init($here);
        
        Bash::echo('All module successfully updated');
    }
    
    public function removeAction(){
        $arg_modules = $this->req->param->modules ?? ['-'];
        $modules = $this->filterArgModules($arg_modules);
        if(!$modules)
            Bash::error('No module to process');
        
        $here = getcwd();
        $ask = true;
        
        if(count($modules) > 1){
            $ask_conf = [
                'text' => 'Are you sure want to remove all selected modules?',
                'type' => 'bool',
                'default' => false
            ];

            if(Bash::ask($ask_conf))
                $ask = false;
        }
        
        // remove all selected modules
        foreach($modules as $name => $uri){
            if(!Module::remove($here, $name, $ask))
                return;
        }
        
        Config::init($here);
        
        Bash::echo('The confirmed module(s) successfully removed');
    }
}
