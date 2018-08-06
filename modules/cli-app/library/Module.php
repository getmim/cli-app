<?php
/**
 * Module library
 * @package cli-app
 * @version 0.0.5
 */

namespace CliApp\Library;

use CliApp\Library\{
    ConfigInjector,
    Git,
    Syncer
};
use Cli\Library\Bash;
use Mim\Library\Fs;

class Module
{

    static function addGitIgnoreDb(string $here, array $config): void{
        $nl = PHP_EOL;
        if(!isset($config['__gitignore']))
            return;
        
        $mod_ignores = $config['__gitignore'];
        $app_ignores = [];
        
        $all_ignores = [];
        
        $app_ignore_file = $here . '/.gitignore';
        if(is_file($app_ignore_file)){
            $dump_ignores = file_get_contents($app_ignore_file);
            $ignores = explode($nl, $dump_ignores);
            $last_parent = 'global';
            
            foreach($ignores as $line){
                $line = trim($line);
                if(!$line)
                    continue;
                if(substr($line, 0, 2) === '##'){
                    $last_parent = trim(substr($line, 2));
                    $app_ignores[$last_parent] = [];
                    continue;
                }
                
                $cond = substr($line, 0, 1) !== '#';
                if(!$cond)
                    $line = trim(substr($line, 1));
                if(!in_array($line, $app_ignores[$last_parent]))
                    $app_ignores[$last_parent][] = $line;
                $all_ignores[$line] = $cond;
            }
        }
        
        $parent = $config['__name'];
        if(!isset($app_ignores[$parent]))
            $app_ignores[$parent] = [];
        foreach($mod_ignores as $line => $cond){
            $all_ignores[$line] = $cond;
            if(!in_array($line, $app_ignores[$parent]))
                $app_ignores[$parent][] = $line;
        }
        
        $tx = '';
        foreach($app_ignores as $parent => $lines){
            if($tx)
                $tx.= $nl;
            $tx.= '## ' . $parent . $nl;
            foreach($lines as $line){
                if(!$all_ignores[$line])
                    $line = '# ' . $line;
                $tx.= $line . $nl;
            }
        }
        
        Fs::write($app_ignore_file, $tx);
    }
    
    static function addModuleDb(string $here, object $temp): void{
        $nl = PHP_EOL;
        $app_modules_file = $here . '/etc/modules.php';
        $app_modules = [];
        if(is_file($app_modules_file))
            $app_modules = include $app_modules_file;
        foreach($temp->urls as $scheme => $conf){
            if($conf->used){
                $app_modules[$temp->name] = $conf->value;
                break;
            }
        }
        
        $source = to_source($app_modules);
        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';
        
        Fs::write($app_modules_file, $tx);
    }
    
    static function installDependencies(string $here, array $devs): void{
        $composer_installed = false;
        foreach($devs as $type => $modules){
            if($type === 'composer'){
                foreach($modules as $name => $version){
                    $cmd = 'cd "'.$here.'" && composer --format=json show';
                    $result = [];
                    exec($cmd, $result, $rval);

                    $install = true;
                    if(!$rval){
                        $data = json_decode(implode(' ', $result));
                        if($data && isset($data->installed)){
                            foreach($data->installed as $imd){
                                if($imd->name == $name){
                                    $install = false;
                                    break;
                                }
                            }
                        }
                    }

                    if(!$install)
                        continue;

                    $composer_installed = true;

                    // let install the module
                    $rname = $name;
                    if($version)
                        $rname.= ':' . $version;
                    $cmd = 'cd "' . $here . '" && composer require ' . $rname;

                    Bash::echo('Installing composer `' . $rname . '`');
                    exec($cmd);
                }
            }elseif(in_array($type, ['optional', 'required'])){
                foreach($modules as $mods){
                    $mods_len = count($mods);
                    
                    if($mods_len === 1){
                        foreach($mods as $mod_name => $mod_uri);
                        
                        $ask_conf = [
                            'text' => 'Module `' . $mod_name . '` is optional. Would you like to install it?',
                            'type' => 'bool',
                            'default' => true
                        ];
                        
                        $mod_int_conf_file = $here . '/modules/' . $mod_name . '/config.php';
                        $mod_exists = is_file($mod_int_conf_file);
                        
                        if(!$mod_exists && ($type === 'required' || Bash::ask($ask_conf)))
                            self::install($here, $mod_name, $mod_uri);
                    }else{
                        $ask_conf = [
                            'text' => 'One of below modules is optionally to be installed. Please select one',
                            'options' => [ 0 => '(none)' ]
                        ];
                        
                        if($type === 'required'){
                            $ask_conf = [
                                'text' => 'One of below modules is need to be installed. Please select one',
                                'options' => []
                            ];
                        }
                        
                        foreach($mods as $mod_name => $mod_uri){
                            $mod_int_conf_file = $here . '/modules/' . $mod_name . '/config.php';
                            if(is_file($mod_int_conf_file))
                                continue 2;
                            $ask_conf['options'][] = $mod_name;
                        }
                        
                        $picked = (int)Bash::ask($ask_conf);
                        $mod_name = $ask_conf['options'][$picked];
                        
                        if($mod_name === '(none)')
                            continue;
                        $mod_uri  = $mods[$mod_name];
                        self::install($here, $mod_name, $mod_uri);
                    }
                }
            }
        }

        // let optimize the composer
        if($composer_installed){
            Bash::echo('Optimize composer autoload');

            $cmd = 'cd "' . $here . '"'
                 . ' && composer dump-autoload -o'
                 . ' && composer dump-autoload -a';
            exec($cmd);
        }
    }
    
    static function isAppBase(string $here): bool{
        // should has this folders
        $dirs = [
            'app',
            'modules',
            'etc'
        ];
        foreach($dirs as $dir){
            if(!is_dir($here . '/' . $dir))
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
            if(!is_file($here . '/' . $file))
                return false;
        }
        
        return true;
    }
    
    static function isModuleBase(string $here): bool{
        // module should has `modules/name` dir
        $mod_dir = $here . '/modules';
        if(!is_dir($mod_dir))
            return false;
            
        // file under modules dir
        $files = Fs::scan($mod_dir);
        $names = [];
        foreach($files as $file){
            $file_abs = $mod_dir . '/' . $file;
            if(is_dir($file_abs))
                $names[] = $file;
        }
        
        if(count($names) != 1)
            return false;
        
        $module_name = $names[0];
        
        // module should has config file
        $mod_conf_file = $mod_dir . '/' . $module_name . '/config.php';
        if(!is_file($mod_conf_file))
            return false;
        
        $mod_conf = include $mod_conf_file;
        // module config should has those properties
        $props = ['__name', '__version', '__git', '__files'];
        foreach($props as $prop){
            if(!array_key_exists($prop, $mod_conf))
                return false;
        }
        return true;
    }
    
    static function install(string $here, string $module, string $uri=null): bool{
        Bash::echo('Installing module `' . $module . '`');
        
        // downloading the module
        $temp = Git::download($here, $module, $uri);
        
        if(!$temp){
            Bash::error('Unable to get module `' . $module . '` source');
            return false;
        }
        
        if(!self::isModuleBase($temp->base)){
            Bash::error('The module is not mim module');
            return false;
        }
        
        $temp->config_file = $temp->base . '/modules/' . $temp->name . '/config.php';
        $temp->config = include $temp->config_file;
        
        // sync module files
        if(!Syncer::sync($temp->base, $here, $temp->config['__files'], 'install')){
            Bash::error('Unable to sync module sources');
            return false;
        }
        
        $module_conf_file = $here . '/etc/config/main.php';
        
        // inject application config
        ConfigInjector::inject($module_conf_file, $temp->config);
        
        // Add current module to application modules
        self::addModuleDb($here, $temp);
        
        // Add gitignore
        self::addGitIgnoreDb($here, $temp->config);
        
        // Install dependencies (req/opt)
        if(isset($temp->config['__dependencies']))
            self::installDependencies($here, $temp->config['__dependencies']);
        
        // Remove the tmp files
        Fs::rmdir($temp->base);
        Fs::cleanUp(dirname($temp->base));
        
        return true;
    }
    
    static function remove(string $here, string $module, bool $ask=true): bool{
        $module_conf_file = $here . '/modules/' . $module . '/config.php';
        if(!is_file($module_conf_file)){
            Bash::error('Module `' . $module . '` not found. Skipping...', false);
            return true;
        }
        
        $ask_conf = [
            'text' => 'Are you sure want to remove module `' . $module . '`?',
            'type' => 'bool',
            'default' => false
        ];
        
        if($ask && !Bash::ask($ask_conf))
            return true;
        
        // make some file here so the remover don't remove it
        $unremoval = $here . '/.stop';
        touch($unremoval);
        
        $module_config = include $module_conf_file;
        
        // remove the module
        if(!Syncer::remove($here, $module_config['__files'])){
            Bash::error('Unable to remove the module');
            return false;
        }
        
        self::removeModuleDb($here, $module_config['__name']);
        unlink($unremoval);
        
        return true;
    }

    static function removeModuleDb(string $here, string $name): void{
        $nl = PHP_EOL;
        $app_modules_file = $here . '/etc/modules.php';
        $app_modules = [];
        if(is_file($app_modules_file))
            $app_modules = include $app_modules_file;

        if(isset($app_modules[$name]))
            unset($app_modules[$name]);

        $source = to_source($app_modules);
        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';
        
        Fs::write($app_modules_file, $tx);
    }
    
    static function update(string $here, string $module, string $uri=null): bool{
        Bash::echo('Updating module `' . $module . '`');
        
        // downloading the module
        $temp = Git::download($here, $module, $uri);
        
        if(!$temp){
            Bash::error('Unable to get module `' . $module . '` source');
            return false;
        }
        
        if(!self::isModuleBase($temp->base)){
            Bash::error('The module is not mim module');
            return false;
        }
        
        $temp->config_file = $temp->base . '/modules/' . $temp->name . '/config.php';
        $temp->config = include $temp->config_file;
        
        // sync module files
        if(!Syncer::sync($temp->base, $here, $temp->config['__files'], 'update')){
            Bash::error('Unable to sync module sources');
            return false;
        }
        
        $module_conf_file = $here . '/etc/config/main.php';
        
        // inject application config
        ConfigInjector::inject($module_conf_file, $temp->config);
        
        // Add gitignore
        self::addGitIgnoreDb($here, $temp->config);
        
        // Install dependencies (req/opt)
        if(isset($temp->config['__dependencies']))
            self::installDependencies($here, $temp->config['__dependencies']);
        
        // Remove the tmp files
        Fs::rmdir($temp->base);
        Fs::cleanUp(dirname($temp->base));
        
        return true;
    }
}