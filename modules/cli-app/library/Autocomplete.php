<?php
/**
 * Autocomplete provider
 * @package cli-app
 * @version 0.0.8
 */

namespace CliApp\Library;

class Autocomplete extends \Cli\Autocomplete
{
    static function env(): string{
        return 'production development testing';
    }

    static function none(): string{
        return '1';
    }
    
    static function command(array $args): string{
        $farg = $args[1] ?? null;

        $routes = include BASEPATH . '/etc/cache/routes.php';
        
        $result = [];

        foreach($routes->{'tool-app'} as $route){
            $bpath = explode(' ', trim($route->path->value));
            if(!isset($bpath[1]))
                continue;
            if(!in_array($bpath[1], $result))
                $result[] = $bpath[1];
        }
        
        if(!$farg)
            return trim(implode(' ', $result));

        return parent::lastArg($farg, $result);
    }

    static function host(): string{
        $hosts = Apps::getAll();
        if(!$hosts)
            return '1';

        $hosts = array_keys($hosts);

        return trim(implode(' ', $hosts));
    }

    static function module(array $args): string{
        $mod_file = getcwd() . '/etc/modules.php';
        if(!is_file($mod_file))
            return '1';

        $modules = include $mod_file;
        $modules = array_keys($modules);

        $result = [];
        foreach($modules as $mod){
            if(!in_array($mod, $args))
                $result[] = $mod;
        }

        return implode(' ', $result);
    }
}