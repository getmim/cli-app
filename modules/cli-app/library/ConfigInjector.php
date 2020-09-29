<?php
/**
 * Application config injector
 * @package cli-app 
 * @version 0.0.2
 */

namespace CliApp\Library;

use Mim\Library\Fs;
use Cli\Library\Bash;

class ConfigInjector
{
    private static $module_autoloads = [];
    private static $app_dir = '';

    private static function _loadAppClass(string $name): void{
        if(isset(self::$module_autoloads->classes->$name))
            require_once self::$app_dir . '/' . self::$module_autoloads->classes->$name;
    }

    private static function moduleAutoload(array $configs): void{
        $configs = objectify($configs);
        $result = AutoloadParser::parse($configs, self::$app_dir);
        self::$module_autoloads = $result;
    }

    private static function askInput(array $config, int $space=0){
        $options = $config['options'] ?? [];
        
        $rule = $config['rule'] ?? 'any';
        
        $askOpts = [
            'text'    => $config['question'],
            'default' => self::getDefault($config),
            'space'   => $space
        ];
        
        if($rule === 'boolean'){
            $askOpts['type'] = 'bool';
            $answer = Bash::ask($askOpts);
        }else{
            if($options){
                $used_options   = [];
                $last_index     = 0;
                $used_opt_value = [];
                foreach($options as $key => $val){
                    $used_options[++$last_index] = $val;
                    $used_opt_value[$last_index] = $key;
                }
                $askOpts['options'] = $used_options;
            }

            $answer = Bash::ask($askOpts);

            if($options)
                $answer = $used_opt_value[$answer];
        }
            
        $valid = self::validateInput($answer, $config);
        if($valid['error']){
            Bash::echo($valid['error']);
            return self::askInput($config, $space);
        }
        
        $answer = $valid['value'];
        return $answer;
    }
    
    private static function getDefault(array $config){
        if(!isset($config['default']))
            return null;
        if(!is_array($config['default']))
            return $config['default'];
        
        $class = $config['default']['class'];
        $method= $config['default']['method'];

        if(!class_exists($class, false))
            self::_loadAppClass($class);
        
        return $class::$method( self::$app_dir );
    }
    
    private static function scanForNew(array $config, array $inject): array{
        $result = [];
        
        foreach($inject as $inj){
            $iname = $inj['name'];
            $children = $inj['children'] ?? null;
            
            // fixed name
            if(!is_array($inj['name'])){
                // without children
                if(!$children){
                    if(!isset($config[$iname]))
                        $result[] = $inj;
                // with children
                }else{
                    if(!isset($config[$iname]))
                        $result[] = $inj;
                    elseif(is_array($config[$iname])){
                        $children_result = self::scanForNew($config[$iname], $children);
                        if($children_result){
                            $children = $children_result;
                            $inj['children'] = $children;
                            $result[] = $inj;
                        }
                    }
                }
                
            // dynamic name
            }else{
                // without children
                if(!$children){
                    if(!$config)
                        $result[] = $inj;
                
                // with children
                }else{
                    if(!$config){
                        $result[] = $inj;
                    }else{
                        foreach($config as $cname => $cval){
                            if(!is_array($cval))
                                continue;
                            
                            $children_result = self::scanForNew($config[$cname], $children);
                            if($children_result){
                                $children = $children_result;
                                $inj['children'] = $children;
                                $result[] = $inj;
                            }
                        }
                    }
                }
            }
        }
        
        return $result;
    }
    
    private static function validateInput($input, array $config): array{
        $rule = $config['rule'] ?? 'any';
        
        $result = [
            'value' => $input,
            'error' => 'Provided answer is not valid'
        ];
        
        if(is_array($rule)){
            $class = $rule['class'];
            $method= $rule['method'];
            if(!class_exists($class, false))
                self::_loadAppClass($class);
            $result = $class::$method($input);
        }else{
            switch($rule){
                case 'boolean':
                    $result = [
                        'value' => (bool)$input,
                        'error' => false
                    ];
                case 'any':
                    $result = [
                        'value' => $input,
                        'error' => false
                    ];
                    break;
                case 'number':
                    $is_numeric = is_numeric($input);
                    $result = [
                        'value' => (int)$input,
                        'error' => !$is_numeric ? 'Provided answer is not valid number' : false
                    ];
                    break;
                default:
                    $is_valid = preg_match($rule, $input);
                    $result = [
                        'value' => $input,
                        'error' => !$is_valid ? 'Provided answer is not valid' : false
                    ];
            }
        }
        
        return $result;
    }
    
    static function injectConfigs(array &$config, array $items, int $space=0, array $app_config=null): void{
        $next_space = $space + 2;
        foreach($items as $item){
            $children = $item['children'] ?? null;
            $name = $item['name'];
            $loop = true;
            $injector = $item['injector'] ?? null;
            
            while($loop){
                $used_name = $name;
                if(is_array($name))
                    $used_name = self::askInput($name, $space);
                else
                    $loop = false;
                if(!$used_name)
                    break;
                
                $value = [];
                
                if(!$children){
                    $value = self::askInput($item, $space);
                    if($injector){
                        $class = $injector['class'];
                        $method= $injector['method'];
                        if(!class_exists($class, false))
                            self::_loadAppClass($class);
                        $value = $class::$method($config, $value, $app_config);
                        if(is_null($value))
                            continue;
                    }
                }
                $config[$used_name] = $value;
                
                if($children){
                    if(isset($item['question'])){
                        $question = $item['question'];
                        Bash::echo($question, $space);
                    }
                    self::injectConfigs($config[$used_name], $children, $next_space, $app_config);
                    if($config[$used_name] === [])
                        unset($config[$used_name]);
                }
            }
        }
    }
    
    static function inject(string $file, array $config, string $target=''): void{
        if(!$target)
            $target = getcwd();

        self::$app_dir = $target;

        $nl = PHP_EOL;
        if(!isset($config['__inject']))
            return;
        
        if(!is_file($file)){
            Bash::error('Application config file not found');
            return;
        }

        self::moduleAutoload($config);
        
        $app_config = include $file;
        
        $to_append = self::scanForNew($app_config, $config['__inject']);
        if(!$to_append)
            return;
        
        Bash::echo('Some configs may need to inject to your application config');
        if(!Bash::ask(['text'=>'Would you like me to configure it now', 'type'=>'bool', 'default'=>true]))
            return;
        
        self::injectConfigs($app_config, $to_append, 0, $app_config);
        
        $tx = '<?php' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . to_source($app_config) . ';';
        
        Fs::write($file, $tx);
    }
}