<?php
/**
 * CLI Application
 * @package cli-app 
 * @version 0.0.1
 */

return [
    '__name' => 'cli-app',
    '__version' => '0.0.1',
    '__git' => 'git@github.com:getphun/cli-app.git',
    '__license' => 'MIT',
    '__author' => [
        'name' => 'Iqbal Fauzi',
        'email' => 'iqbalfawz@gmail.com',
        'website' => 'https://iqbalfn.com/'
    ],
    '__files' => [
        'modules/cli-app' => ['install', 'update', 'remove']
    ],
    '__dependencies' => [
        'required' => [
            'core' => true,
            'cli' => true
        ],
        'optional' => []
    ],
    'autoload' => [
        'classes' => [
            'CliApp\\Controller' => [
                'type' => 'file',
                'base' => 'modules/cli-app/library/Controller.php',
                'children' => 'modules/cli-app/controller'
            ]
        ]
    ],
    'gates' => [
        'tool-app' => [
            'priority' => 1000,
            'host' => [
                'value' => 'CLI'
            ],
            'path' => [
                'value' => 'app'
            ]
        ]
    ],
    'routes' => [
        'tool-app' => [
            '404' => [
                'handler' => 'Cli\\Controller::show404'
            ],
            '500' => [
                'handler' => 'Cli\\Controller::show500'
            ],
            
            'toolAppConfig' => [
                'info' => 'Regenerate application configs',
                'path' => [
                    'value' => 'config'
                ],
                'handler' => 'CliApp\\Controller\\Config::generate'
            ],
            
            'toolAppInit' => [
                'info' => 'Create empty application on current directory',
                'path' => [
                    'value' => 'init'
                ],
                'handler' => 'CliApp\\Controller\\Application::init'
            ],
            
            'toolAppModuleIndex' => [
                'info' => 'List of all modules under current application',
                'path' => [
                    'value' => 'module'
                ],
                'handler' => 'CliApp\\Controller\\Module::index'
            ],
            'toolAppModuleInstall' => [
                'info' => 'Install new module to the current application',
                'path' => [
                    'value' => 'install (:modules)',
                    'params' => [
                        'modules' => 'rest'
                    ]
                ],
                'handler' => 'CliApp\\Controller\\Module::install'
            ],
            'toolAppModuleRemove' => [
                'info' => 'Remove exists modules from this application',
                'path' => [
                    'value' => 'remove (:modules)',
                    'params' => [
                        'modules' => 'rest'
                    ]
                ],
                'handler' => 'CliApp\\Controller\\Module::remove'
            ],
            'toolAppModuleUpdate' => [
                'info' => 'Update exists modules in this application',
                'path' => [
                    'value' => 'update (:modules)',
                    'params' => [
                        'modules' => 'rest'
                    ]
                ],
                'handler' => 'CliApp\\Controller\\Module::update'
            ],
            'toolAppServer' => [
                'info' => 'Test server installation based on current application requirement',
                'path' => [
                    'value' => 'server'
                ],
                'handler' => 'CliApp\\Controller\\Server::test'
            ]
        ]
    ]
];