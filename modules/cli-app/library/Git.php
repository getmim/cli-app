<?php
/**
 * Git handler
 * @package cli-app
 * @version 0.0.2
 */

namespace CliApp\Library;

use \Mim\Library\Fs;
use \Cli\Library\Bash;

Class Git
{
    private static function _makeUri(string $name): ?object{
        $result = (object)[
            'name'      => null,
            'provider'  => null,
            'urls'      => null,
            'params'    => null
        ];
        
        if(preg_match('!^[\w-]+$!', $name))
            $name = 'git@github.com:getmim/' . $name . '.git';
        
        $regexs = [
            '!github\.com\/(?<user>[^\/]+)\/(?<repo>[\w-]+)\.git!'         => 'github',
            '!github\.com\/(?<user>[^\/]+)\/(?<repo>[\w-]+)!'              => 'github',
            '!git@github\.com:(?<user>[^\/]+)\/(?<repo>[\w-]+)\.git!'      => 'github',
            '![\w]+@bitbucket\.org\/(?<user>[^\/]+)/(?<repo>[\w-]+)\.git!' => 'bitbucket',
            '!bitbucket\.org\/(?<user>[^\/]+)\/(?<repo>[\w-]+)!'           => 'bitbucket',
            '!git@bitbucket\.org:(?<user>[^\/]+)\/(?<repo>[\w-]+)\.git!'   => 'bitbucket',
        ];
        
        $provider_urls = [
            'github' => (object)[
                'ssh'   => (object)[
                    'value' => 'git@github.com:($user)/($repo).git',
                    'asks' => (object)[],
                    'used' => false
                ],
                'https' => (object)[
                    'value' => 'https://github.com/($user)/($repo).git',
                    'asks' => (object)[],
                    'used' => false
                ]
            ],
            'bitbucket' => (object)[
                'ssh'   => (object)[
                    'value' => 'git@bitbucket.org:($user)/($repo).git',
                    'asks'  => (object)[],
                    'used' => false
                ],
                'https' => (object)[
                    'value' => 'https://$(username)@bitbucket.org/($user)/($repo).git',
                    'asks'  => (object)[
                        'username' => 'Please provide your bitbucket username:'
                    ],
                    'used' => false
                ]
            ]
        ];
        
        foreach($regexs as $regex => $prov){
            if(!preg_match($regex, $name, $match))
                continue;
            
            $result->provider = $prov;
            $result->urls = $provider_urls[$prov];
            $result->params = $match;
            
            foreach($result->urls as $scheme => $conf){
                foreach($match as $ma_name => $ma_value){
                    if($ma_name === 'repo')
                        $result->name = $ma_value;
                    $val = $result->urls->$scheme->value;
                    $val = str_replace('($' . $ma_name . ')', $ma_value, $val);
                    $result->urls->$scheme->value = $val;
                }
            }
        }
        
        if(!$result->name)
            return null;
        return $result;
    }
    
    static function download(string $here, string $name, string $uri=null): ?object{
        $repo = self::_makeUri($uri??$name);
        if(!$repo)
            return null;
        
        $tmp_dir = $here . '/.tmp/' . $repo->name;
        if(is_dir($tmp_dir))
            Fs::rmdir($tmp_dir);
        
        $repo->base = $tmp_dir;
        
        Fs::mkdir($tmp_dir);
        
        $success = false;
        foreach($repo->urls as $scheme => $conf){
            $url = $conf->value;
            foreach($conf->asks as $ask_name => $question){
                $answer = Bash::ask(['text' => $question]);
                $url = str_replace('$(' . $ask_name . ')', $answer, $url);
            }
            
            Bash::echo('- Cloning module from ' . $url . '...');
            $cmd = 'cd ' . $tmp_dir . ' && git clone ' . $url . ' . -q';
            $result = Bash::run($cmd);
            
            if(!$result->code){
                $success = true;
                $conf->used = true;
                $repo->urls->$scheme = $conf;
                break;
            }else{
                Bash::echo('');
                Bash::echo('- Failed. Trying the next method if exists...');
            }
        }
        
        return $success ? $repo : null;
    }
}
