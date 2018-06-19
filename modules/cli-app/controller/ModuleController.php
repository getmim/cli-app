<?php
/**
 * Module controller
 * @package cli-app
 * @version 0.0.1
 */

namespace CliApp\Controller;

class ModuleController extends \CliApp\Controller
{
    public function indexAction(){
        $here = getcwd();
        // expected modules file
        $module_file = $here . '/etc/modules.php';
        $module_dir  = $here . '/modules';
        
        if(!$this->isAppBase($here))
            $this->error('Please run the command under exists application');
        
//         if(!is_file($module_file))
//             $this->error('Please run the command under exists application');
//         if(!is_dir($module_dir))
//             $this->error('Please run the command under exists application');
        
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
            $this->echo(' - ' . $name . ' ' . $version);
        }
        
        $dirs = \Mim\Library\Fs::scan($module_dir);
        foreach($dirs as $dir){
            if(in_array($dir, $printed))
                continue;
            $mod_dir = $module_dir . '/' . $dir;
            if(!is_dir($mod_dir))
                continue;
            
            $this->echo(' - ' . $dir . ' (Not registered)');
        }
    }
    
    public function installAction(){
        $this->echo('Do it later');
    }
    
    public function updateAction(){
        $this->echo('Do it later');
    }
    
    public function removeAction(){
        $this->echo('Do it later');
    }
}