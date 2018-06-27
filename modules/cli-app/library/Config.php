<?php
/**
 * Config library
 * @package cli-app
 * @version 0.0.2
 */

namespace CliApp\Library;

use Mim\Library\Fs;
use Cli\Library\Bash;

class Config
{
    private static function _autoloadFile(object &$target, string $ns, object $conf, string $here): void{
        $base_abs = $here . '/' . $conf->base;
        
        if(is_file($base_abs))
            $target->classes->{$ns} = $conf->base;
        elseif(is_dir($base_abs))
            self::_autoloadFolder($target, $ns, $conf->base, $here);
        
        if(isset($conf->children))
            self::_autoloadFolder($target, $ns, $conf->children, $here);
    }
    
    private static function _autoloadFolder(object &$target, string $ns, string $base, string $here): void{
        $base_abs = $here . '/' . $base;
        
        $files = Fs::scan($base_abs);
        
        foreach($files as $file){
            $file_abs  = $base_abs . '/' . $file;
            $file_base = $base . '/' . $file;
            
            if(is_file($file_abs)){
                $next_ns = $ns . '\\' . preg_replace('!\.php$!', '', $file);
                $target->classes->{$next_ns} = $file_base;
            }elseif(is_dir($file_abs)){
                $next_ns = $ns . '\\' . ucfirst($file);
                self::_autoloadFolder($target, $next_ns, $file_base, $here);
            }
        }
    }
    
    private static function _reqHandler(object $route, object $gate): object{
        $route->_handlers = [];
        
        // let combine route middlewares and gate middlewares
        $handlers = [
            'pre'  => [],
            'main' => [ $route->handler => 1 ],
            'post' => []
        ];
        
        $sources = [$gate,$route];
        foreach($sources as $source){
            if(!isset($source->middlewares))
                continue;
            foreach($handlers as $group => $hdrs){
                if(!isset($source->middlewares->$group))
                    continue;
            
                foreach($source->middlewares->$group as $handler => $order)
                    $handlers[$group][$handler] = $order;
                asort($handlers[$group]);
            }
        }
        
        foreach($handlers as $key => $hds){
            foreach($hds as $hd => $o){
                $hdrs = explode('::', $hd);
                $suffix = $key === 'main' ? 'Controller' : 'Middleware';
                if(substr($hdrs[0], -strlen($suffix)) !== $suffix)
                    $hdrs[0].= $suffix;
                    
                $hdr = (object)[
                    'class' => $hdrs[0],
                    'method' => $hdrs[1] . 'Action'
                ];
                
                if($hd === 'main')
                    $route->handler = $hdr;
                $hdr->solved = false;
                $route->_handlers[] = $hdr;
            }
        }
        
        return $route;
    }
    
    private static function _reqHost(object $config, object $conf): object{
        $result = (object)[
            'value' => $conf->value,
            'params' => $conf->params ?? (object)[],
            '_type' => 'text'
        ];
        
        if($conf->value === 'CLI')
            return $result;
        
        if(strstr($conf->value, 'HOST'))
            $result->value = str_replace('HOST', $config->host, $conf->value);
        
        if(!preg_match_all('!\(:([a-z]+)\)!', $conf->value, $match))
            return $result;
        
        $result->_type = 'regex';
        
        $regex = '!^' . str_replace('.', '\\.', $result->value) . '$!';
        foreach($match[1] as $key){
            $type = $result->params->$key ?? 'any';
            $rval = '(?<' . $key . '>';
            
            $result->params->$key = $type;
            
            if(is_array($type)){
                $rval.= '(' . implode('|', $type) . ')';
            }else{
                switch($type){
                case 'any':
                    $rval.= '[^\.]+';
                    break;
                case 'slug':
                    $rval.= '[A-Za-z0-9_-]+';
                    break;
                case 'number':
                    $rval.= '[0-9]+';
                    break;
                case 'rest':
                    $rval.= '.+';
                }
            }
            
            $rval.= ')';
            
            $regex = str_replace('(:' . $key . ')', $rval, $regex);
        }
        
        $result->_value = $regex;
        
        return $result;
    }
    
    private static function _reqPath(object $config, object $conf, ?object $gate, bool $rest): object{
        $result = (object)[
            'value' => $conf->value,
            'params' => $conf->params ?? (object)[],
            '_type' => 'text'
        ];

        if(!preg_match_all('!\(:([a-z]+)\)!', $conf->value, $match))
            return $result;

        $result->_type = 'regex';
        
        if($gate->host->value !== 'CLI')
            $regex = '!^' . str_replace('/', '\\/', $result->value) . ($rest?'((\/.*))*':'') . '$!';
        else
            $regex = '!^' . $result->value . ($rest?'(( .*))*':'') . '$!';
        
        foreach($match[1] as $key){
            $type = $result->params->$key ?? 'any';
            $rval = '(?<' . $key . '>';
            
            $result->params->$key = $type;
            
            if(is_array($type)){
                $rval.= '(' . implode('|', $type) . ')';
            }else{
                switch($type){
                case 'any':
                    $rval.= '[^\.]+';
                    break;
                case 'slug':
                    $rval.= '[A-Za-z0-9_-]+';
                    break;
                case 'number':
                    $rval.= '[0-9]+';
                    break;
                case 'rest':
                    $rval.= '.+';
                }
            }
            
            $rval.= ')';
            
            $regex = str_replace('(:' . $key . ')', $rval, $regex);
        }
        
        $result->_value = $regex;
        
        return $result;
    }
    
    private static function _parseAutoload(object &$config, string $here): void{
        $result = (object)[
            'classes' => (object)[],
            'files' => $config->autoload->files
        ];
        
        foreach($config->autoload->classes as $ns => $conf){
            switch($conf->type){
            case 'file':
                self::_autoloadFile($result, $ns, $conf, $here);
                break;
            default:
                Bash::error('Autoload type `' . $conf->type . '` is not supported');
            }
        }
        
        $config->autoload = $result;
    }
    
    private static function _parseGates(object &$configs, string $here): void{
        $nl = PHP_EOL;
        $result = [];
        
        if(isset($configs->gates)){
            $routes = $configs->routes ?? (object)[];
            
            foreach($configs->gates as $name => $conf){
                $res = (object)[
                    'name' => $name,
                    'priority' => $conf->priority ?? 1000,
                    'host' => self::_reqHost($configs, $conf->host),
                    'path' => self::_reqPath($configs, $conf->path, $conf, true),
                    'middlewares' => $conf->middlewares ?? (object)[],
                    'errors' => (object)[]
                ];
                
                if(isset($routes->$name)){
                    // 404
                    if(isset($routes->$name->{'404'})){
                        $res->errors->{'404'} = self::_reqHandler($routes->$name->{'404'}, $conf);
                    }
                    
                    // 500
                    if(isset($routes->$name->{'500'})){
                        $res->errors->{'500'} = self::_reqHandler($routes->$name->{'500'}, $conf);
                    }
                }
                $result[] = $res;
            }
            
            usort($result, function($a, $b){
                return $a->priority - $b->priority;
            });
        }
        
        $source = to_source($result);
        
        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';
        
        Fs::write($here . '/etc/cache/gates.php', $tx);
    }
    
    private static function _parseRoutes(object &$configs, string $here): void{
        $nl = PHP_EOL;
        $result  = (object)[];
        
        if(isset($configs->routes)){
            $result->_gateof = (object)[];
            
            $gates   = $configs->gates ?? (object)[];
            $groutes = $configs->routes;
            
            foreach($groutes as $gname => $routes){
                $gate_routes = [];
                if(!isset($gates->$gname))
                    continue;
                
                $gate = $gates->$gname;
                $sep  = $gate->host->value === 'CLI' ? ' ' : '/';
                $gpath_params = $gate->path->params ?? (object)[];
                $gpath = $gate->path->value;
                
                foreach($routes as $rname => $conf){
                    if(in_array($rname, ['404','500']))
                        continue;
                    $result->_gateof->$rname = $gname;
                    
                    $conf->name = $rname;
                    $conf->priority = $conf->priority ?? 1000;
                    $conf->middlewares = $conf->middlewares ?? (object)[];
                    
                    $route_path_params = $conf->path->params ?? (object)[];
                    $combine_path_params = object_replace($gpath_params, $route_path_params);
                    
                    $conf->path->params = $combine_path_params;
                    $route_path = $conf->path->value;
                    $combined_path = $gpath . $sep . ltrim($route_path, $sep);
                    $combined_path = trim($combined_path, ' '. $sep);
                    if($sep === '/')
                        $combined_path = '/' . $combined_path;
                    $conf->path->value = $combined_path;
                    
                    $conf->path = self::_reqPath($configs, $conf->path, $gate, false);
                    
                    $conf = self::_reqHandler($conf, $gate);
                    
                    $gate_routes[$rname] = $conf;
                }
                
                uasort($gate_routes, function($a, $b){
                    return $a->priority - $b->priority;
                });
                
                $result->$gname = (object)$gate_routes;
            }
        }
        
        $source = to_source($result);
        
        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';
        
        Fs::write($here . '/etc/cache/routes.php', $tx);
    }
    
    static function init(string $here): void{
        $nl = PHP_EOL;
        $module_dir  = $here . '/modules';
        $configs = [[
            '_modules' => []
        ]];
        $modules = Fs::scan($module_dir);
        
        foreach($modules as $mod){
            $mod_path = $module_dir . '/' . $mod;
            if(!is_dir($mod_path))
                continue;
            $mod_conf_file = $mod_path . '/config.php';
            if(!is_file($mod_conf_file))
                Bash::error('Module `' . $mod . '` has no config file');
            $mod_conf = include $mod_conf_file;
            $mod_conf_filtered = [];
            foreach($mod_conf as $cname => $cval){
                if(substr($cname, 0, 2) == '__')
                    continue;
                $mod_conf_filtered[$cname] = $cval;
            }
            $configs[] = $mod_conf_filtered;
            
            $configs[0]['_modules'][] = $mod;
        }
        
        $configs[] = include $here . '/etc/config/main.php';
        $env = file_get_contents($here . '/etc/.env');
        $env_config_file = $here . '/etc/config/' . $env . '.php';
        if(is_file($env_config_file))
            $configs[] = include $env_config_file;
        
        $configs = array_replace_recursive(...$configs);
        $configs = objectify($configs);
        
        self::_parseAutoload($configs, $here);
        self::_parseGates($configs, $here);
        self::_parseRoutes($configs, $here);
        
        $source = to_source($configs);
        
        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';
        
        Fs::write($here . '/etc/cache/config.php', $tx);
    }
}