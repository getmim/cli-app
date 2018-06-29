<?php
/**
 * Autocomplete provider
 * @package cli-app
 * @version 0.0.7
 */

namespace CliApp\Library;

class Autocomplete
{
	static function app(array $args): string{
		return 'config init install module remove server update';
	}

	static function module(array $args): string{
		$modules = include BASEPATH . '/etc/modules.php';
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