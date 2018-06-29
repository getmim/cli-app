<?php
/**
 * Autocomplete provider
 * @package cli-app
 * @version 0.0.8
 */

namespace CliApp\Library;

class Autocomplete
{
	static function app(array $args): string{
		return 'config init install module remove server update';
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
	
	static function none(): string{
		return '1';
	}
}