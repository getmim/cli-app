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
        foreach($modules as $module){
            if($module === '-'){
                $module_db_file = getcwd() . '/etc/modules.php';
                if(!is_file($module_db_file))
                    Bash::error('Please run the command under exists application');
                $module_db = include $module_db_file;
                foreach($module_db as $name => $uri)
                    $result[$name] = $uri;
            }else{
                $result[$module] = null;
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
        $modules = $this->filterArgModules($this->req->param->modules);
        if(!$modules)
            Bash::error('No module to process');
        
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
            
            if(!Module::install($here, $name, $uri))
                return;
        }
        
        Config::init($here);
        
        Bash::echo('The module(s) successfully installed');
    }
    
    public function updateAction(){
        $modules = $this->filterArgModules($this->req->param->modules);
        if(!$modules)
            Bash::error('No module to process');
        
        $here = getcwd();
        
        // update all selected modules
        foreach($modules as $name => $uri){
            $module_config_file = $here . '/modules/' . $name . '/config.php';
            if(!is_file($module_config_file)){
                Bash::error('Module `' . $name . '` is not installed. Skipping...', false);
                continue;
            }
            
            if(!Module::update($here, $name, $uri))
                return;
        }
        
        Config::init($here);
        
        Bash::echo('All module successfully updated');
    }
    
    public function removeAction(){
        $modules = $this->filterArgModules($this->req->param->modules);
        if(!$modules)
            Bash::error('No module to process');
        
        $here = getcwd();
        
        // remove all selected modules
        foreach($modules as $name => $uri){
            if(!Module::remove($here, $name))
                return;
        }
        
        Config::init($here);
        
        Bash::echo('The confirmed module(s) successfully removed');
    }
}