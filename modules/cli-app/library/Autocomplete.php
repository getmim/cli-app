<?php
/**
 * Autocomplete provider
 * @package cli-app
 * @version 0.0.8
 */

namespace CliApp\Library;

class Autocomplete extends \Cli\Autocomplete
{
	static function command(array $args): string{
		$farg = $args[1] ?? null;
		$result = ['config', 'init', 'install', 'module', 'remove', 'server', 'update'];

		if(!$farg)
			return trim(implode(' ', $result));

		return parent::lastArg($farg, $result);
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