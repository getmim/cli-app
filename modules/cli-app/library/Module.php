<?php
/**
 * Module library
 * @package cli-app
 * @version 0.0.5
 */

namespace CliApp\Library;

use CliApp\Library\{
    Apps,
    ConfigInjector,
    Git,
    Syncer
};
use Cli\Library\Bash;
use Mim\Library\Fs;

class Module
{
    private static $skipInstallModules = [];

    private static function registerAppList(string $here): void{
        $env  = trim(file_get_contents($here . '/etc/.env'));
        $app_config = include $here . '/etc/config/main.php';
        $host = $app_config['host'];

        $env_config_file = $here . '/etc/config/' . $env . '.php';
        if(is_file($env_config_file)){
            $env_config = include $env_config_file;
            if(isset($env_config['host']))
                $host = $env_config['host'];
        }

        Apps::add($host, $here);
    }

    static function addGitIgnoreDb(string $here, array $config): void{
        self::regenerateGitIgnoreDb($here);
    }

    static function addModuleIgnoredDb(string $here, string $name): void{
        $nl = PHP_EOL;
        $ignore_modules_file = self::getIgnoredModuleFile($here);
        $ignore_modules = [];
        if(is_file($ignore_modules_file))
            $ignore_modules = include $ignore_modules_file;
        $ignore_modules[$name] = time();

        $source = to_source($ignore_modules);
        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';
        
        Fs::write($ignore_modules_file, $tx);
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

    static function getIgnoredModuleFile(string $here): string{
        return $here . '/etc/modules-skipped.php';
    }

    static function getIgnoredModules(string $here): array{
        $ignore_modules_file = self::getIgnoredModuleFile($here);
        if(!is_file($ignore_modules_file))
            return [];

        return include $ignore_modules_file;
    }
    
    static function installDependencies(string $here, array $devs, bool $ignore_dev=false): void{
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
                $ignored_modules = self::getIgnoredModules($here);

                foreach($modules as $mods){
                    $mods_len = count($mods);

                    if($ignore_dev && $type === 'optional')
                        continue;

                    // let find if there's dev that is not ignored
                    $non_ignored_modules = [];
                    foreach($mods as $mod_name => $mod_uri){
                        if(!isset($ignored_modules[$mod_name]))
                            $non_ignored_modules[] = $mod_name;
                    }

                    // no module to install, skip process
                    if(!$non_ignored_modules)
                        continue;
                    
                    if($mods_len === 1){
                        foreach($mods as $mod_name => $mod_uri);
                        
                        $ask_conf = [
                            'text' => 'Module `' . $mod_name . '` is optional. Would you like to install it?',
                            'type' => 'bool',
                            'default' => true
                        ];
                        
                        $mod_int_conf_file = $here . '/modules/' . $mod_name . '/config.php';
                        $mod_exists = is_file($mod_int_conf_file);

                        if(!$mod_exists){
                            $install_it = $type === 'required';
                            if(!$install_it){
                                if(!in_array($mod_name, self::$skipInstallModules)){
                                    $install_it = Bash::ask($ask_conf);
                                    if(!$install_it){
                                        self::$skipInstallModules[] = $mod_name;
                                        self::addModuleIgnoredDb($here, $mod_name);
                                    }
                                }
                            }

                            if($install_it)
                                self::install($here, $mod_name, $mod_uri);
                        }
                            
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
                            if(!in_array($mod_name, self::$skipInstallModules))
                                $ask_conf['options'][] = $mod_name;
                        }
                        
                        if(count($ask_conf['options']) === 1)
                            continue;

                        $picked = (int)Bash::ask($ask_conf);
                        $mod_name = $ask_conf['options'][$picked];
                        
                        if($mod_name === '(none)'){
                            foreach($ask_conf['options'] as $idx => $mod_name){
                                if($idx){
                                    self::$skipInstallModules[] = $mod_name;
                                    self::addModuleIgnoredDb($here, $mod_name);
                                }
                            }
                            continue;
                        }
                        
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

        self::registerAppList($here);
        
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
    
    static function install(string $here, string $module, string $uri=null, bool $ignore_dev=false): bool{
        Bash::echo('Installing module `' . $module . '`');

        $force = $uri == '~' ? true : false;
        $temp = Local::copy($here, $module, $force);

        if (!$temp) {
            $temp = Git::download($here, $module, $uri);
        }

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
        ConfigInjector::inject($module_conf_file, $temp->config, $here);
        
        // Add current module to application modules
        self::addModuleDb($here, $temp);
        
        // Add gitignore
        self::addGitIgnoreDb($here, $temp->config);
        
        // Install dependencies (req/opt)
        if(isset($temp->config['__dependencies']))
            self::installDependencies($here, $temp->config['__dependencies'], $ignore_dev);
        
        // Remove the tmp files
        Fs::rmdir($temp->base);
        Fs::cleanUp(dirname($temp->base));

        return true;
    }

    static function regenerateGitIgnoreDb(string $here): bool{
        $app_ignore_file = $here . '/.gitignore';
        if(is_file($app_ignore_file))
            unlink($app_ignore_file);

        $module_dir = $here . '/modules';
        if(!is_dir($module_dir))
            return false;

        $app_config_file = $here . '/etc/config/main.php';
        if(!is_file($app_config_file))
            return false;

        $modules = Fs::scan($module_dir);

        $gitignores = [];
        $ignorelines= [];

        foreach($modules as $mod){
            $mod_path = $module_dir . '/' . $mod;
            if(!is_dir($mod_path))
                continue;
            
            $mod_conf_file = $mod_path . '/config.php';
            if(!is_file($mod_conf_file))
                Bash::error('Module `' . $mod . '` has no config file');

            $mod_conf = include $mod_conf_file;
            $mod_conf_filtered = [];

            $ignoreline = $mod_conf['__gitignore'] ?? [];

            if(!$ignoreline)
                continue;

            $gitignores[$mod_conf['__name']] = $ignoreline;
            $ignorelines = array_merge($ignorelines, $ignoreline);
        }

        $app_config = include $app_config_file;
        if(isset($app_config['__gitignore'])){
            $gitignores['global'] = $app_config['__gitignore'];
            $ignorelines = array_merge($ignorelines, $app_config['__gitignore']);
        }

        $env = file_get_contents($here . '/etc/.env');
        $env_config_file = $here . '/etc/config/' . $env . '.php';
        if(is_file($env_config_file)){
            $env_config = include $env_config_file;
            if(isset($env_config['__gitignore'])){
                $gitignores['global'] = $env_config['__gitignore'];
                $ignorelines = array_merge($ignorelines, $env_config['__gitignore']);
            }
        }

        $nl = PHP_EOL;
        $tx = '';

        foreach($gitignores as $parent => $lines){
            $files = [];
            foreach($lines as $file => $cond){
                if(!$ignorelines[$file])
                    continue;
                $files[] = $file;
            }

            if(!$files)
                continue;

            if($tx)
                $tx.= $nl;
            $tx.= '# ' . $parent . $nl;
            foreach($files as $file)
                $tx.= $file .  $nl;
        }

        Fs::write($app_ignore_file, trim($tx));

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

        // Regenerate gitignore file
        self::regenerateGitIgnoreDb($here);
    }
    
    static function update(string $here, string $module, string $uri=null, bool $ignore_dev=false): bool{
        Bash::echo('Updating module `' . $module . '`');
        
        if ($uri === '~') {
            $temp = Local::copy($here, $module, false);
        } else {
            // download the module
            $temp = Git::download($here, $module, $uri);
        }

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
        ConfigInjector::inject($module_conf_file, $temp->config, $here);
        
        // Add gitignore
        self::addGitIgnoreDb($here, $temp->config);
        
        // Install dependencies (req/opt)
        if(isset($temp->config['__dependencies']))
            self::installDependencies($here, $temp->config['__dependencies'], $ignore_dev);
        
        // Remove the tmp files
        Fs::rmdir($temp->base);
        Fs::cleanUp(dirname($temp->base));

        return true;
    }
}
