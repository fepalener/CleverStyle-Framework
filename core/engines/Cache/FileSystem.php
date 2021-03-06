<?php
/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2011-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
namespace cs\Cache;
/**
 * Provides cache functionality based on file system structure.
 */
class FileSystem extends _Abstract {
	/**
	 * Like realpath() but works even if files does not exists
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	protected function get_absolute_path ($path) {
		$path      = str_replace(['/', '\\'], '/', $path);
		$parts     = array_filter(explode('/', $path), 'strlen');
		$absolutes = [];
		foreach ($parts as $part) {
			if ('.' == $part) {
				continue;
			}
			if ('..' == $part) {
				array_pop($absolutes);
			} else {
				$absolutes[] = $part;
			}
		}
		return CACHE.'/'.implode('/', $absolutes);
	}
	/**
	 * @inheritdoc
	 */
	public function get ($item) {
		$path_in_filesystem = $this->get_absolute_path($item);
		if (
			strpos($path_in_filesystem, CACHE) !== 0 ||
			!is_file($path_in_filesystem)
		) {
			return false;
		}
		$cache = file_get_contents($path_in_filesystem);
		$cache = _json_decode($cache);
		if ($cache !== false) {
			return $cache;
		}
		unlink($path_in_filesystem);
		return false;
	}
	/**
	 * @inheritdoc
	 */
	public function set ($item, $data) {
		$path_in_filesystem = $this->get_absolute_path($item);
		if (strpos($path_in_filesystem, CACHE) !== 0) {
			return false;
		}
		$data = _json_encode($data);
		if (mb_strpos($item, '/') !== false) {
			$path = mb_substr($item, 0, mb_strrpos($item, '/'));
			if (!is_dir(CACHE."/$path")) {
				/** @noinspection MkdirRaceConditionInspection */
				@mkdir(CACHE."/$path", 0770, true);
			}
			unset($path);
		}
		if (!file_exists($path_in_filesystem) || is_writable($path_in_filesystem)) {
			return (bool)file_put_contents($path_in_filesystem, $data, LOCK_EX | FILE_BINARY);
		}
		trigger_error("File $path_in_filesystem not available for writing", E_USER_WARNING);
		return false;
	}
	/**
	 * @inheritdoc
	 */
	public function del ($item) {
		$path_in_filesystem = $this->get_absolute_path($item);
		if (strpos($path_in_filesystem, CACHE) !== 0) {
			return false;
		}
		if (is_dir($path_in_filesystem)) {
			/**
			 * Rename to random name in order to immediately invalidate nested elements, actual deletion done right after this
			 */
			$new_path = $path_in_filesystem.md5(random_bytes(1000));
			/**
			 * Sometimes concurrent deletion might happen, so we need to silent error and actually remove directory only when renaming was successful
			 */
			return @rename($path_in_filesystem, $new_path) ? rmdir_recursive($new_path) : !is_dir($path_in_filesystem);
		}
		return file_exists($path_in_filesystem) ? @unlink($path_in_filesystem) : true;
	}
	/**
	 * @inheritdoc
	 */
	public function clean () {
		$ok         = true;
		$dirs_to_rm = [];
		/**
		 * Remove root files and rename root directories for instant cache cleaning
		 */
		$random_key = md5(random_bytes(1000));
		get_files_list(
			CACHE,
			false,
			'fd',
			true,
			false,
			false,
			false,
			true,
			function ($item) use (&$ok, &$dirs_to_rm, $random_key) {
				if (is_writable($item)) {
					if (is_dir($item)) {
						rename($item, "$item$random_key");
						$dirs_to_rm[] = "$item$random_key";
					} else {
						@unlink($item);
					}
				} else {
					$ok = false;
				}
			}
		);
		/**
		 * Then remove all renamed directories
		 */
		foreach ($dirs_to_rm as $dir) {
			$ok = rmdir_recursive($dir) && $ok;
		}
		return $ok;
	}
}
