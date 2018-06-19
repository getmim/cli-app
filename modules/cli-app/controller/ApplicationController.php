<?php
/**
 * Application controller
 * @package cli-app
 * @version 0.0.1
 */

namespace CliApp\Controller;

class ApplicationController extends \CliApp\Controller
{
    public function initAction(){
        $this->echo('Do it later');
    }
}