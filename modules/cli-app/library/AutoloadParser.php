<?php
/**
 * AutoloadParser library
 * @package cli-app
 * @version 0.0.10
 */

namespace CliApp\Library;

use Mim\Library\Fs;
use Cli\Library\Bash;

class AutoloadParser
{
    private static function _autoloadFilePSR0(object &$target, string $ns, object $conf, string $here): void{
        $base_abs = $here . '/' . $conf->base;

        $prefix = $conf->prefix ?? '';
        if($prefix)
            $prefix.= '_';

        if(substr($ns, 0, 1) === '-')
            $ns = '';

        if(is_file($base_abs)){
            $cname = $prefix . basename($conf->base, '.php');
            if($ns)
                $cname = $ns . '\\' . $cname;
            $target->classes->{$cname} = $conf->base;
        }elseif(is_dir($base_abs)){
            self::_autoloadFolderPSR0($target, $ns, $conf->base, $here, $prefix);
        }
    }

    private static function _autoloadFolderPSR0(object &$target, string $ns, string $base, string $here, string $prefix){
        $base_abs = $here . '/' . $base;

        $files = Fs::scan($base_abs);
        if(!$files)
            return;

        if($prefix)
            $prefix = chop($prefix, '_') . '_';

        foreach($files as $file){
            $file_abs  = $base_abs . '/' . $file;
            $file_base = $base . '/' . $file;

            if(is_file($file_abs)){
                $cname = $prefix . basename($file, '.php');
                if($ns)
                    $cname = $ns . '\\' . $cname;
                $target->classes->{$cname} = $file_base;
            }elseif(is_dir($file_abs)){
                $next_prefix = $prefix . $file;
                self::_autoloadFolderPSR0($target, $ns, $file_base, $here, $next_prefix);
            }
        }
    }

    private static function _autoloadFilePSR4(object &$target, string $ns, object $conf, string $here): void{
        $conf_bases = $conf->base;

        if(!is_array($conf_bases))
            $conf_bases = [$conf_bases];

        foreach($conf_bases as $conf_base){
            $base_abs = $here . '/' . $conf_base;
            
            if(is_file($base_abs))
                $target->classes->{$ns} = $conf_base;
            elseif(is_dir($base_abs))
                self::_autoloadFolderPSR4($target, $ns, $conf_base, $here);
            
            if(isset($conf->children)){
                $conf_children = $conf->children;
                if(!is_array($conf_children))
                    $conf_children = [$conf_children];
                foreach($conf_children as $conf_child)
                    self::_autoloadFolderPSR4($target, $ns, $conf_child, $here);
            }
        }
    }

    private static function _autoloadFolderPSR4(object &$target, string $ns, string $base, string $here): void{
        $base_abs = $here . '/' . $base;
        
        $files = Fs::scan($base_abs);
        if(!$files)
            return;
        
        foreach($files as $file){
            $file_abs  = $base_abs . '/' . $file;
            $file_base = $base . '/' . $file;
            
            if(is_file($file_abs)){
                $next_ns = $ns . '\\' . preg_replace('!\.php$!', '', $file);
                $target->classes->{$next_ns} = $file_base;
            }elseif(is_dir($file_abs)){
                $next_ns = $ns . '\\' . ucfirst($file);
                self::_autoloadFolderPSR4($target, $next_ns, $file_base, $here);
            }
        }
    }

    static function parse(object $config, string $here): ?object{
        $result = (object)[
            'classes' => (object)[],
            'files'   => $config->autoload->files ?? (object)[]
        ];
        
        foreach($config->autoload->classes as $ns => $conf){
            switch($conf->type){
            case 'file':
            case 'psr4':
                self::_autoloadFilePSR4($result, $ns, $conf, $here);
                break;
            case 'psr0':
                self::_autoloadFilePSR0($result, $ns, $conf, $here);
                break;
            default:
                Bash::error('Autoload type `' . $conf->type . '` is not supported');
            }
        }
        
        return $result;
    }
}