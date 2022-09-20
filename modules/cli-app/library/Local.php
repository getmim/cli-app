<?php
/**
 * Local handler
 * @package cli-app
 * @version 0.8.0
 */

namespace CliApp\Library;

use \Mim\Library\Fs;
use \Cli\Library\Bash;

Class Local
{
    protected static function _makeUri(string $here, string $name, bool $force): ?object
    {
        $result = (object)[
            'name'      => $name,
            'provider'  => 'local',
            'urls'      => null,
            'params'    => null
        ];

        $app_config_file = $here . '/etc/config/main.php';
        if(!is_file($app_config_file))
            return null;

        $app_config = include $app_config_file;

        $repos = $app_config['repos'] ?? [];
        if ($repos) {
            foreach ($repos as $repo) {
                $path = chop($repo, '/') . '/' . $name;
                if (!is_dir($path)) {
                    continue;
                }

                $result->urls = (object)[
                    'file' => (object)[
                        'value' => $path,
                        'asks' => (object)[],
                        'used' => true
                    ]
                ];

                break;
            }
        }

        if ($force) {
            if (!$result->urls) {
                Bash::echo('No repo found, please provide path to local dir');
                while(true) {
                    $path = Bash::ask([
                        'text' => 'Module local path',
                        'required' => true
                    ]);

                    if (!is_dir($path)) {
                        Bash::echo('Error: Path not found');
                        continue;
                    }

                    $result->urls = (object)[
                        'file' => (object)[
                            'value' => $path,
                            'asks' => (object)[],
                            'used' => true
                        ]
                    ];

                    break;
                }
            }
        }

        if (!$result->urls) {
            return null;
        }

        return $result;
    }

    static function copy(string $here, string $name, bool $force): ?object{
        $repo = self::_makeUri($here, $name, $force);
        if(!$repo)
            return null;

        $tmp_dir = $here . '/.tmp/' . $name;
        if(is_dir($tmp_dir))
            Fs::rmdir($tmp_dir);

        $repo->base = $tmp_dir;

        Fs::mkdir($tmp_dir);

        $source_dir = $repo->urls->file->value;
        Bash::echo('- Copying file from ' . $source_dir . '...');
        $cmd = 'cp -Rf ' . $source_dir . '/* ' . $tmp_dir;
        $result = Bash::run($cmd);

        $success = false;
        if(!$result->code){
            $success = true;
        }else{
            Bash::echo('');
            Bash::echo('- Failed on copying the module dir');
        }

        return $success ? $repo : null;
    }
}
