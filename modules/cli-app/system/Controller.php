<?php
/**
 * cli-app gate base
 * @package cli-app
 * @version 0.0.1
 */

namespace CliApp;

class Controller extends \Cli\Controller
{
    public function isAppBase(string $path): bool{
        // should has this folders
        $dirs = [
            'app',
            'modules',
            'etc'
        ];
        foreach($dirs as $dir){
            if(!is_dir($path . '/' . $dir))
                return false;
        }
        
        // should has this files
        $files = [
            'index.php',
            'etc/.env',
            'etc/modules.php',
            'etc/config/main.php'
        ];
        foreach($files as $file){
            if(!is_file($path . '/' . $file))
                return false;
        }
        
        return true;
    }
}