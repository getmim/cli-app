<?php
/**
 * Syncer library
 * @package cli-app
 * @version 0.0.2
 */

namespace CliApp\Library;

use Mim\Library\Fs;

class Syncer
{
    private static function scanDir(string $source, string $path): array{
        $result = [];
        
        $dir_abs = $source . '/' . $path;
        $dir_files = Fs::scan($dir_abs);
        
        foreach($dir_files as $file){
            // skip editor temp file
            if(preg_match('!\.kate-swp$!', $file))
                continue;
            $file_path = $path . '/' . $file;
            $file_abs  = $source . '/' . $file_path;
            if(is_file($file_abs))
                $result[] = $file_path;
            elseif(is_dir($file_abs)){
                $subdir_files = self::scanDir($source, $file_path);
                foreach($subdir_files as $subfile)
                    $result[] = $subfile;
            }
        }
        
        return $result;
    }
    
    static function remove(string $source, array $files): bool{
        $module_files = self::scan($source, $source, $files, 'remove');
        $source_files = $module_files['source']['files'];
        
        foreach($source_files as $file => $rules){
            $source_file_abs = $source . '/' . $file;
            
            if(!in_array('remove', $rules))
                continue;
            
            if(is_file($source_file_abs)){
                unlink($source_file_abs);
                $source_file_abs = dirname($source_file_abs);
            }
            Fs::cleanUp($source_file_abs);
        }
        
        return true;
    }
    
    static function scan(string $source, string $target, array $files): array{
        $result = [
            'source' => [
                'base' => $source,
                'files' => []
            ],
            'target' => [
                'base' => $target,
                'files' => []
            ]
        ];
        
        foreach($result as $base => $conf){
            foreach($files as $file => $rule){
                $file_abs = $conf['base'] . '/' . $file;
                if(is_file($file_abs)){
                    $result[$base]['files'][$file] = $rule;
                }elseif(is_dir($file_abs)){
                    $dir_files = self::scanDir($source, $file);
                    foreach($dir_files as $dir_file){
                        if(!isset($result[$base]['files'][$dir_file]))
                            $result[$base]['files'][$dir_file] = $rule;
                    }
                }
            }
        }
        
        return $result;
    }
    
    static function sync(string $source, string $target, array $files, string $rule): bool{
        $accepted_rules = ['install', 'update'];
        
        if(!is_dir($source) || !is_dir($target))
            return false;
        if(!in_array($rule, $accepted_rules))
            return false;
        if(!$files)
            return false;
        
        $module_files = self::scan($source, $target, $files, $rule);

        foreach($module_files as $type => $conf){
            $source_base  = $conf['base'];
            $source_files = $conf['files'];
            $used_rule    = $type === 'source' ? $rule : 'obsolete';
            
            foreach($source_files as $file => $rules){
                $target_file_abs = $target . '/' . $file;
                $source_file_abs = $source_base . '/' . $file;

                $tmp_used_rule = $used_rule;

                // new file created on update with `install` only rule
                if(!is_file($target_file_abs))
                    $tmp_used_rule = 'install';

                if(!in_array($tmp_used_rule, $rules))
                    continue;

                if($tmp_used_rule === 'obsolete'){
                    if(is_file($source_file_abs)){
                        unlink($source_file_abs);
                        $source_file_abs = dirname($source_file_abs);
                    }
                    Fs::cleanUp($source_file_abs);
                }else{
                    $copy = true;
                    if($tmp_used_rule === 'install')
                        $copy = !is_file($target_file_abs);
                    
                    if($copy)
                        Fs::copy($source_file_abs, $target_file_abs);
                }
            }
        }
        
        return true;
    }
}