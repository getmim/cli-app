<?php
/**
 * Config library
 * @package cli-app
 * @version 0.7.1
 */

namespace CliApp\Library;

use Mim\Library\Fs;
use Cli\Library\Bash;
use StableSort\StableSort;

class Config
{

    private static $app_autoload;

    private static function _reqHandler(object $route, object $gate, bool $inc_middleware=true): object{
        $route->_handlers = [];

        // let combine route middlewares and gate middlewares
        $handlers = [
            'pre'  => [],
            'main' => [ $route->handler => 1 ],
            'post' => []
        ];

        $sources = [$gate,$route];
        foreach($sources as $source){
            if(!isset($source->middlewares) || !$inc_middleware)
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

        $is_cli = $gate->host->value === 'CLI';

        if(!$is_cli)
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
                    $sign = $is_cli ? ' ' : '/';
                    $rval.= '[^' . $sign . ']+';
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

    private static function _loadAppClass(string $name, string $here): void{
        if(isset(self::$app_autoload->classes->$name))
            require_once $here . '/' . self::$app_autoload->classes->$name;
    }

    private static function _parseAutoload(object &$config, string $here): void{
        $result = AutoloadParser::parse($config, $here);
        self::$app_autoload = $result;
        $config->autoload = $result;
    }

    private static function _parseCallback(object &$configs, string $here): void{
        if(!isset($configs->callback))
            return;

        foreach($configs->callback as $module => &$modules){
            foreach($modules as $event => &$events){
                $new_events = [];
                foreach($events as $handler => $cond){
                    if(!$cond)
                        continue;

                    $handler = explode('::', $handler);
                    $class  = $handler[0];
                    $method = $handler[1];

                    $new_events[] = (object)[
                        'class' => $class,
                        'method' => $method
                    ];
                }
                $events = $new_events;
            }
        }
        unset($events);

        if(!isset($configs->callback->app->reconfig))
            return;

        $callbacks = $configs->callback->app->reconfig;
        foreach($callbacks as $callback){
            $cls = $callback->class;
            $mth = $callback->method;
            self::_loadAppClass($cls, $here);
            $cls::$mth($configs, $here);
        }
    }

    private static function _parseGates(object &$configs, string $here): void{
        $nl = PHP_EOL;
        $result = [];

        if(isset($configs->gates)){
            $routes = $configs->routes ?? (object)[];

            foreach($configs->gates as $name => $conf){
                if(!isset($conf->asset))
                    $conf->asset = (object)['host' => $conf->host->value];
                if(strstr($conf->asset->host, 'HOST'))
                    $conf->asset->host = str_replace('HOST', $configs->host, $conf->asset->host);

                $res = (object)[
                    'name'          => $name,
                    'priority'      => $conf->priority ?? 1000,
                    'host'          => self::_reqHost($configs, $conf->host),
                    'path'          => self::_reqPath($configs, $conf->path, $conf, true),
                    'asset'         => $conf->asset,
                    'middlewares'   => $conf->middlewares ?? (object)[],
                    'errors'        => (object)[]
                ];

                if(isset($routes->$name)){
                    // 404
                    if(isset($routes->$name->{'404'})){
                        $res->errors->{'404'} = self::_reqHandler($routes->$name->{'404'}, $conf, false);
                    }

                    // 500
                    if(isset($routes->$name->{'500'})){
                        $res->errors->{'500'} = self::_reqHandler($routes->$name->{'500'}, $conf, false);
                    }
                }

                $result[] = $res;
            }

            StableSort::usort($result, function($a, $b){
                return $b->priority - $a->priority;
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
        $nl     = PHP_EOL;
        $result = (object)[];

        if(isset($configs->routes)){
            $result->_gateof = (object)[];

            $gates   = $configs->gates ?? (object)[];
            $groutes = $configs->routes;

            foreach($groutes as $gname => $routes){
                $gate_routes = [];
                if(!isset($gates->$gname))
                    continue;

                $gate         = $gates->$gname;
                $sep          = $gate->host->value === 'CLI' ? ' ' : '/';
                $gpath_params = $gate->path->params ?? (object)[];
                $gpath        = $gate->path->value;

                foreach($routes as $rname => $conf){
                    if(in_array($rname, ['404','500']))
                        continue;

                    if(isset($conf->modules)){
                        $dont_use = false;
                        foreach($conf->modules as $mod => $use){
                            if(!$use)
                                continue;
                            if(!in_array($mod, $configs->_modules)){
                                $dont_use = true;
                                break;
                            }
                        }

                        if($dont_use)
                            continue;
                    }

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

                    if($sep === '/'){
                        if(!isset($conf->method))
                            $conf->method = 'GET';
                        $conf->_method = explode('|', $conf->method);
                    }

                    $conf = self::_reqHandler($conf, $gate);

                    $gate_routes[$rname] = $conf;
                }

                StableSort::uasort($gate_routes, function($a, $b){
                    return $b->priority - $a->priority;
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

    static function fetch(string $here): ?object{
        $module_dir = $here . '/modules';
        if(!is_dir($module_dir))
            return null;

        $user_app = $here . '/app';

        $app_config_file = $here . '/etc/config/main.php';
        if(!is_file($app_config_file))
            return null;

        $configs = [[
            '_modules' => []
        ]];
        $modules = Fs::scan($module_dir);

        foreach($modules as $mod){
            $config_dir = [
                $module_dir . '/' . $mod,
                $user_app   . '/' . $mod
            ];

            foreach($config_dir as $idx => $mod_path){
                if(!is_dir($mod_path))
                    continue;

                $mod_conf_file = $mod_path . '/config.php';
                if(!is_file($mod_conf_file)){
                    if(!$idx)
                        Bash::error('Module `' . $mod . '` has no config file');
                    continue;
                }

                $mod_conf = include $mod_conf_file;
                $mod_conf_filtered = [];

                foreach($mod_conf as $cname => $cval){
                    if(substr($cname,0,2) === '__')
                        continue;
                    $mod_conf_filtered[$cname] = $cval;
                }

                $configs[] = $mod_conf_filtered;

                $configs[0]['_modules'][] = $mod;
            }
        }

        $app_config = include $app_config_file;

        // .includes
        if(isset($app_config['includes'])){
            foreach($app_config['includes'] as $file => $cond){
                if(!$cond)
                    continue;
                if(substr($file,0,1) != '/')
                    $file = realpath($here . '/' . $file);
                $file = chop($file, '/');

                if(is_file($file)){
                    $configs[] = include $file;
                }elseif(is_dir($file)){
                    $inc_files = Fs::scan($file);
                    foreach($inc_files as $inc_file){
                        if(substr($inc_file, -4) === '.php')
                            $configs[] = include $file . '/' . $inc_file;
                    }
                }
            }
        }

        $configs[] = $app_config;
        $env = trim(file_get_contents($here . '/etc/.env'));
        $env_config_file = $here . '/etc/config/' . $env . '.php';
        if(is_file($env_config_file))
            $configs[] = include $env_config_file;

        $configs = array_replace_recursive(...$configs);
        $configs = objectify($configs);

        return $configs;
    }

    static function init(string $here): void{
        $nl = PHP_EOL;

        $configs = self::fetch($here);
        if(!$configs)
            return;

        self::_parseAutoload($configs, $here);
        self::_parseGates($configs, $here);
        self::_parseRoutes($configs, $here);
        self::_parseCallback($configs, $here);

        if(isset($configs->__gitignore))
            unset($configs->__gitignore);
        $source = to_source($configs);

        $tx = '<?php' . $nl;
        $tx.= '/* GENERATE BY CLI */' . $nl;
        $tx.= '/* DON\'T MODIFY */' . $nl;
        $tx.= $nl;
        $tx.= 'return ' . $source . ';';

        Fs::write($here . '/etc/cache/config.php', $tx);
    }
}
