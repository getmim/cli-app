<?php
/**
 * Server controller
 * @package cli-app
 * @version 0.0.1
 */

namespace CliApp\Controller;

class ServerController extends \CliApp\Controller
{
    public function testAction(){
        $here = getcwd();
        // expected config file
        $config_file = $here . '/etc/cache/config.php';
        if(!is_file($config_file))
            $this->error('Please run the command under exists application');
        $config = include $config_file;
        
        if(!isset($config->server))
            $this->error('Server test case not found');
    
        $servers = $config->server;
        $result = [];
        $length = (object)[
            'module' => 0,
            'test' => 0,
            'info' => 0
        ];
        
        foreach($servers as $module => $tests){
            $mod_len = strlen($module);
            if($mod_len > $length->module)
                $length->module = $mod_len;
            
            foreach($tests as $label => $handler){
                $label_len = strlen($label);
                if($label_len > $length->test)
                    $length->test = $label_len;
                
                if(is_string($handler)){
                    $hdr = explode('::', $handler);
                    $class = $hdr[0];
                    $method= $hdr[1];
                }else{
                    $class = $handler->class;
                    $method= $handler->method;
                }
                
                $res = $class::$method();
                $info_len = strlen($res['info']);
                if($info_len > $length->info)
                    $length->info = $info_len;
                
                $result[] = (object)[
                    'module' => $module,
                    'test' => $label,
                    'info' => $res['info'],
                    'success' => $res['success'],
                ];
            }
        }
        
        $length->module+= 3;
        $length->test+= 3;
        $length->info+= 3;
        
        $table_title = str_pad('result', 9, ' ')
                     . str_pad('module', $length->module, ' ')
                     . str_pad('test', $length->test, ' ')
                     . str_pad('info', $length->info, ' ');
        
        $this->echo('');
        $this->echo($table_title);
        $this->echo(str_repeat(' ', strlen($table_title)));
        foreach($result as $res){
            $row = str_pad(($res->success?'[x]':'[ ]'), 9, ' ')
                 . str_pad($res->module, $length->module, ' ')
                 . str_pad($res->test, $length->test, ' ')
                 . str_pad($res->info, $length->info, ' ');
            $this->echo($row);
        }
        $this->echo('');
    }
}