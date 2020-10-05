<?php
/**
 * Apps
 * @package cli-app
 * @version 0.2.0
 */

namespace CliApp\Library;


class Apps
{
	private static $cache_file = '/etc/cache/app-list.php';

	static function add(string $host, string $path): void{
		if(!$host)
			return;
		
		$hosts = self::getAll();

		if(isset($hosts[$host])){
			if($hosts[$host] == $path)
				return;
			$hosts[$host] = $path;

		}elseif(in_array($path, $hosts)){
			$old_hosts = array_keys($hosts, $path);
			foreach($old_hosts as $old_host)
				unset($hosts[$old_host]);
		}

		$hosts[$host] = $path;

		$tx = '<?php' . PHP_EOL;
        $tx.= 'return ' . to_source($hosts) . ';';
        
        $f = fopen(BASEPATH . self::$cache_file, 'w');
        fwrite($f, $tx);
        fclose($f);
	}

	static function getAll(): array{
		$file = BASEPATH . self::$cache_file;
		if(!is_file($file))
			return [];
		return include $file;
	}

	static function remove(string $host): void{
		$hosts = self::getAll();
		if(isset($hosts[$host]))
			unset($hosts[$host]);

		$tx = '<?php' . PHP_EOL;
        $tx.= 'return ' . to_source($hosts) . ';';
        
        $f = fopen(BASEPATH . self::$cache_file, 'w');
        fwrite($f, $tx);
        fclose($f);
	}
}