<?php

return [
    '__name' => 'cli-app',
    '__version' => '0.9.1',
    '__git' => 'git@github.com:getphun/cli-app.git',
    '__license' => 'MIT',
    '__author' => [
        'name' => 'Iqbal Fauzi',
        'email' => 'iqbalfawz@gmail.com',
        'website' => 'https://iqbalfn.com/'
    ],
    '__files' => [
        'modules/cli-app' => ['install','update','remove']
    ],
    '__dependencies' => [
        'required' => [
            [
                'cli' => NULL
            ]
        ],
        'optional' => []
    ],
    'autoload' => [
        'classes' => [
            'CliApp\\Controller' => [
                'type' => 'file',
                'base' => 'modules/cli-app/system/Controller.php',
                'children' => 'modules/cli-app/controller'
            ],
            'CliApp\\Library' => [
                'type' => 'file',
                'base' => 'modules/cli-app/library'
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
            404 => [
                'handler' => 'Cli\\Controller::show404'
            ],
            500 => [
                'handler' => 'Cli\\Controller::show500'
            ],
            'toolAppConfig' => [
                'info' => 'Regenerate application configs',
                'path' => [
                    'value' => 'config'
                ],
                'handler' => 'CliApp\\Controller\\Config::generate'
            ],
            'toolAppEnv' => [
                'info' => 'Change application environment',
                'path' => [
                    'value' => 'env (:target)',
                    'params' => [
                        'target' => 'slug'
                    ]
                ],
                'handler' => 'CliApp\\Controller\\Application::env'
            ],
            'toolAppInit' => [
                'info' => 'Create empty application on current directory',
                'path' => [
                    'value' => 'init'
                ],
                'handler' => 'CliApp\\Controller\\Application::init'
            ],
            'toolAppGitIgnore' => [
                'info' => 'Create or regenerate application gitignore file',
                'path' => [
                    'value' => 'gitignore'
                ],
                'handler' => 'CliApp\\Controller\\Application::gitignore'
            ],
            'toolAppList' => [
                'info' => 'Show all known mim apps on current machine',
                'path' => [
                    'value' => 'list'
                ],
                'handler' => 'CliApp\\Controller\\Application::list'
            ],
            'toolAppModuleIndex' => [
                'info' => 'List of all modules under current application',
                'path' => [
                    'value' => 'module'
                ],
                'handler' => 'CliApp\\Controller\\Module::index'
            ],
            'toolAppModuleInstallAll' => [
                'info' => 'Install all registered modules to the current application',
                'path' => [
                    'value' => 'install'
                ],
                'handler' => 'CliApp\\Controller\\Module::install'
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
            'toolAppModuleRemoveAll' => [
                'info' => 'Remove all exists modules from this application',
                'path' => [
                    'value' => 'remove'
                ],
                'handler' => 'CliApp\\Controller\\Module::remove'
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
            'toolAppModuleUpdateAll' => [
                'info' => 'Update all exists modules in this application',
                'path' => [
                    'value' => 'update'
                ],
                'handler' => 'CliApp\\Controller\\Module::update'
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
            ],
            'toolAppTo' => [
                'info' => 'Go to mim app dir on current machine',
                'path' => [
                    'value' => 'to (:host)'
                ],
                'handler' => 'CliApp\\Controller\\Application::to'
            ]
        ]
    ],
    'cli' => [
        'autocomplete' => [
            '!^app env( .*)?$!' => [
                'priority' => 5,
                'handler' => [
                    'class' => 'CliApp\\Library\\Autocomplete',
                    'method' => 'env'
                ]
            ],
            '!^app (install|remove|update)( .*)?$!' => [
                'priority' => 5,
                'handler' => [
                    'class' => 'CliApp\\Library\\Autocomplete',
                    'method' => 'module'
                ]
            ],
            '!^app to( .*)?$!' => [
                'priority' => 5,
                'handler' => [
                    'class' => 'CliApp\\Library\\Autocomplete',
                    'method' => 'host'
                ]
            ],
            '!^app (config|init|list|module|server)$!' => [
                'priority' => 4,
                'handler' => [
                    'class' => 'CliApp\\Library\\Autocomplete',
                    'method' => 'none'
                ]
            ],
            '!^app( [a-z]*)?$!' => [
                'priority' => 3,
                'handler' => [
                    'class' => 'CliApp\\Library\\Autocomplete',
                    'method' => 'command'
                ]
            ]
        ]
    ]
];
