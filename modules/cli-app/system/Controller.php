<?php
/**
 * cli-app gate base
 * @package cli-app
 * @version 0.0.1
 */

namespace CliApp;

class Controller extends \Cli\Controller
{
    public function isAppBase(string $path): bool{
        return \CliApp\Library\Module::isAppBase($path);
    }
}