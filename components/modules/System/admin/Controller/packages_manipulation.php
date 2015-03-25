<?php
/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
namespace cs\modules\System\admin\Controller;
use
	cs\Config,
	cs\Core,
	cs\DB,
	cs\Language,
	cs\Page;

trait packages_manipulation {
	/**
	 * @param string $file_name File key in `$_FILES` superglobal
	 *
	 * @return bool|string Path to file location if succeed or `false` on failure
	 */
	static protected function move_uploaded_file_to_tmp ($file_name) {
		if (!isset($_FILES[$file_name]) || !$_FILES[$file_name]['tmp_name']) {
			return false;
		}
		$L    = Language::instance();
		$Page = Page::instance();
		switch ($_FILES[$file_name]['error']) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				$Page->warning($L->file_too_large);
				return false;
			case UPLOAD_ERR_NO_TMP_DIR:
				$Page->warning($L->temporary_folder_is_missing);
				return false;
			case UPLOAD_ERR_CANT_WRITE:
				$Page->warning($L->cant_write_file_to_disk);
				return false;
			case UPLOAD_ERR_PARTIAL:
			case UPLOAD_ERR_NO_FILE:
				return false;
		}
		if ($_FILES[$file_name]['error'] != UPLOAD_ERR_OK) {
			return false;
		}
		$tmp_name = TEMP.'/'.md5(openssl_random_pseudo_bytes(1000)).'.phar';
		return move_uploaded_file($_FILES[$file_name]['tmp_name'], $tmp_name) ? $tmp_name : false;
	}
	/**
	 * Generic extraction of files from phar distributive for CleverStyle CMS (components installation)
	 *
	 * @param string $target_directory
	 * @param string $source_phar Will be removed after extraction
	 *
	 * @return bool
	 */
	static protected function install_extract ($target_directory, $source_phar) {
		$tmp_dir = "phar://$source_phar";
		$fs      = file_get_json("$tmp_dir/fs.json");
		$extract = array_product(
			array_map(
				function ($index, $file) use ($tmp_dir, $target_directory) {
					if (
						!file_exists(dirname("$target_directory/$file")) &&
						!mkdir(dirname("$target_directory/$file"), 0770, true)
					) {
						return 0;
					}
					return (int)copy("$tmp_dir/fs/$index", "$target_directory/$file");
				},
				$fs,
				array_keys($fs)
			)
		);
		unlink($source_phar);
		if ($extract) {
			file_put_json("$target_directory/fs.json", array_keys($fs));
		}
		return (bool)$extract;
	}
	/**
	 * Generic extraction of files from phar distributive for CleverStyle CMS (system and components update)
	 *
	 * @param string      $target_directory
	 * @param string      $source_phar             Will be removed after extraction
	 * @param null|string $fs_location_directory   Defaults to `$target_directory`
	 * @param null|string $meta_location_directory Defaults to `$target_directory`
	 *
	 * @return bool
	 */
	static protected function update_extract ($target_directory, $source_phar, $fs_location_directory = null, $meta_location_directory = null) {
		$fs_location_directory   = $fs_location_directory ?: $target_directory;
		$meta_location_directory = $meta_location_directory ?: $target_directory;
		/**
		 * Backup some necessary information about current version
		 */
		copy("$fs_location_directory/fs.json", "$fs_location_directory/fs_backup.json");
		copy("$meta_location_directory/meta.json", "$meta_location_directory/meta_backup.json");
		/**
		 * Extracting new versions of files
		 */
		$tmp_dir = "phar://$source_phar";
		$fs      = file_get_json("$tmp_dir/fs.json");
		$extract = array_product(
			array_map(
				function ($index, $file) use ($tmp_dir, $target_directory) {
					if (
						!file_exists(dirname("$target_directory/$file")) &&
						!mkdir(dirname("$target_directory/$file"), 0770, true)
					) {
						return 0;
					}
					return (int)copy("$tmp_dir/fs/$index", "$target_directory/$file");
				},
				$fs,
				array_keys($fs)
			)
		);
		unlink($source_phar);
		unset($tmp_dir);
		if (!$extract) {
			return false;
		}
		unset($extract);
		$fs = array_keys($fs);
		/**
		 * Removing of old unnecessary files and directories
		 */
		foreach (
			array_diff(
				file_get_json("$fs_location_directory/fs.json"),
				$fs
			) as $file
		) {
			$file = "$target_directory/$file";
			if (file_exists($file) && is_writable($file)) {
				unlink($file);
				// Recursively remove all empty parent directories
				while (!get_files_list($file = dirname($file))) {
					rmdir($file);
				}
			}
		}
		unset($file, $dir);
		file_put_json("$fs_location_directory/fs.json", $fs);
		/**
		 * Removing backups after successful update
		 */
		unlink("$fs_location_directory/fs_backup.json");
		unlink("$meta_location_directory/meta_backup.json");
		return true;
	}
	/**
	 * Generic update for CleverStyle CMS (system and components), runs PHP scripts and does DB migrations after extracting of new distributive
	 *
	 * @param string     $target_directory
	 * @param string     $old_version
	 * @param array|null $db_array `$module_data['db']` if module or system
	 */
	static protected function update_php_sql ($target_directory, $old_version, $db_array = null) {
		$Core   = Core::instance();
		$Config = Config::instance();
		$db     = DB::instance();
		foreach (file_get_json("$target_directory/versions.json") as $version) {
			if (version_compare($old_version, $version, '<')) {
				/**
				 * PHP update script
				 */
				_include("$target_directory/meta/update/$version.php", true, false);
				/**
				 * Database update
				 */
				if ($db_array && file_exists("$target_directory/meta/db.json")) {
					$db_json = file_get_json("$target_directory/meta/db.json");
					time_limit_pause();
					foreach ($db_json as $database) {
						if ($db_array[$database] == 0) {
							$db_type = $Core->db_type;
						} else {
							$db_type = $Config->db[$db_array[$database]]['type'];
						}
						$sql_file = "$target_directory/meta/update_db/$database/$version/$db_type.sql";
						if (isset($db_array[$database]) && file_exists($sql_file)) {
							$db->{$db_array[$database]}()->q(
								explode(';', file_get_contents($sql_file))
							);
						}
					}
					unset($db_json, $database, $db_type, $sql_file);
					time_limit_pause(false);
				}
			}
		}
	}
	/**
	 * @param string $target_directory
	 *
	 * @return bool
	 */
	static protected function recursive_directory_removal ($target_directory) {
		$ok = true;
		get_files_list(
			$target_directory,
			false,
			'fd',
			true,
			true,
			false,
			false,
			true,
			function ($item) use (&$ok) {
				if (is_writable($item)) {
					is_dir($item) ? rmdir($item) : unlink($item);
				} else {
					$ok = false;
				}
			}
		);
		if ($ok) {
			rmdir($target_directory);
		}
		return $ok;
	}
	/**
	 * Check dependencies for new component (during installation/updating/enabling)
	 *
	 * @param string      $name Name of component
	 * @param string      $type Type of component module|plugin
	 * @param null|string $dir  Path to component (if null - component should be found among installed)
	 * @param string      $mode Mode of checking for modules install|update|enable
	 *
	 * @return bool
	 */
	static protected function check_dependencies ($name, $type, $dir = null, $mode = 'enable') {
		if (!$dir) {
			switch ($type) {
				case 'module':
					$dir = MODULES."/$name";
					break;
				case 'plugin':
					$dir = PLUGINS."/$name";
					break;
				default:
					return false;
			}
		}
		if (!file_exists("$dir/meta.json")) {
			return true;
		}
		$meta              = file_get_json("$dir/meta.json");
		$Config            = Config::instance();
		$Core              = Core::instance();
		$L                 = Language::instance();
		$Page              = Page::instance();
		$check_result      = true;
		$db_supported      = self::check_dependencies_db($meta['db_support']);
		$storage_supported = self::check_dependencies_storage($meta['storage_support']);
		$check_result      = $db_supported && $storage_supported;
		$provide           = @(array)$meta['provide'] ?: [];
		$require           = @$meta['require'] ? self::dep_normal($meta['require']) : [];
		$conflict          = @$meta['conflict'] ? self::dep_normal($meta['conflict']) : [];
		/**
		 * Checking for compatibility with modules
		 */
		$check_result_modules = true;
		foreach ($Config->components['modules'] as $module => $module_data) {
			/**
			 * If module uninstalled, disabled (in enable check mode), module name is the same as checked or meta.json file absent
			 * Then skip this module
			 */
			if (!file_exists(MODULES."/$module/meta.json")) {
				continue;
			}
			$module_meta = file_get_json(MODULES."/$module/meta.json");
			/** @noinspection NotOptimalIfConditionsInspection */
			if (
				$module_data['active'] == -1 ||
				(
					$mode == 'enable' && $module_data['active'] == 0
				) ||
				(
					$module == $name && $type == 'module'
				)
			) {
				/**
				 * If module updates, check update possibility from current version
				 */
				if (
					$module == $name &&
					$type == 'module' &&
					$mode == 'update' &&
					isset($meta['update_from']) &&
					version_compare($meta['update_from_version'], $module_meta['version'], '>')
				) {
					if ($check_result_modules) {
						$Page->warning($L->dependencies_not_satisfied);
					}
					$Page->warning(
						$L->module_cant_be_updated_from_version_to_supported_only(
							$module,
							$module_meta['version'],
							$meta['version'],
							$meta['update_from_version']
						)
					);
					return false;
				}
				continue;
			}
			/**
			 * If some module already provides the same functionality
			 */
			if (
				!empty($provide) &&
				isset($module_meta['provide']) &&
				!empty($module_meta['provide']) &&
				$intersect = array_intersect($provide, (array)$module_meta['provide'])
			) {
				if ($check_result_modules) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$check_result_modules = false;
				$Page->warning(
					$L->module_already_provides_functionality(
						$module,
						implode('", "', $intersect)
					)
				);
			}
			unset($intersect);
			/**
			 * Checking for required packages
			 */
			if (isset($require[$module_meta['package']])) {
				if (
				version_compare(
					$module_meta['version'],
					$require[$module_meta['package']][1],
					$require[$module_meta['package']][0]
				)
				) {
					unset($require[$module_meta['package']]);
				} else {
					if ($check_result_modules) {
						$Page->warning($L->dependencies_not_satisfied);
					}
					$check_result_modules = false;
					$Page->warning(
						$L->unsatisfactory_version_of_the_module(
							$module,
							$require[$module_meta['package']][0].' '.$require[$module_meta['package']][1],
							$module_meta['version']
						)
					);
				}
			}
			/**
			 * Checking for required functionality
			 */
			if (isset($module_meta['provide'])) {
				foreach ((array)$module_meta['provide'] as $p) {
					unset($require[$p]);
				}
				unset($p);
			}
			/**
			 * Checking for conflict packages
			 */
			if (
				(
					isset($conflict[$module_meta['package']]) &&
					version_compare(
						$module_meta['version'],
						$conflict[$module_meta['package']][1],
						$conflict[$module_meta['package']][0]
					)
				) ||
				(
					isset($module_meta['conflict']) &&
					($module_meta['conflict'] = self::dep_normal($module_meta['conflict'])) &&
					isset($module_meta['conflict'][$name]) &&
					version_compare(
						$meta['version'],
						$module_meta['conflict'][$name][1],
						$module_meta['conflict'][$name][0]
					)
				)
			) {
				if ($check_result_modules) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$check_result_modules = false;
				$Page->warning(
					$L->conflict_module(
						$module_meta['package'],
						$module
					).
					(
					$conflict[$module_meta['package']][1] != 0 ? $L->compatible_package_versions(
						$require[$module_meta['package']][0].' '.$require[$module_meta['package']][1]
					) : $L->package_is_incompatible(
						$module_meta['package']
					)
					)
				);
			}
		}
		$check_result = $check_result && $check_result_modules;
		unset($check_result_modules, $module, $module_data, $module_meta);
		/**
		 * Checking for compatibility with plugins
		 */
		$check_result_plugins = true;
		foreach ($Config->components['plugins'] as $plugin) {
			if (
				(
					$plugin == $name && $type == 'plugin'
				) ||
				!file_exists(PLUGINS."/$plugin/meta.json")
			) {
				continue;
			}
			$plugin_meta = file_get_json(PLUGINS."/$plugin/meta.json");
			/**
			 * If plugin already provides the same functionality
			 */
			if (
				isset($plugin_meta['provide']) &&
				$intersect = array_intersect($provide, (array)$plugin_meta['provide'])
			) {
				if ($check_result_plugins) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$check_result_plugins = false;
				$Page->warning(
					$L->plugin_already_provides_functionality(
						$plugin,
						implode('", "', $intersect)
					)
				);
			}
			unset($intersect);
			/**
			 * Checking for required packages
			 */
			if (isset($require[$plugin_meta['package']])) {
				if (
				version_compare(
					$plugin_meta['version'],
					$require[$plugin_meta['package']][1],
					$require[$plugin_meta['package']][0]
				)
				) {
					unset($require[$plugin_meta['package']]);
				} else {
					if ($check_result_plugins) {
						$Page->warning($L->dependencies_not_satisfied);
					}
					$check_result_plugins = false;
					$Page->warning(
						$L->unsatisfactory_version_of_the_plugin(
							$plugin,
							$require[$plugin_meta['package']][0].' '.$require[$plugin_meta['package']][1],
							$plugin_meta['version']
						)
					);
				}
			}
			/**
			 * Checking for required functionality
			 */
			if (
				!empty($require) &&
				isset($plugin_meta['provide']) &&
				!empty($plugin_meta['provide'])
			) {
				foreach ((array)$plugin_meta['provide'] as $p) {
					unset($require[$p]);
				}
				unset($p);
			}
			/**
			 * Checking for conflict packages
			 */
			if (
				(
					isset($conflict[$plugin_meta['package']]) &&
					version_compare(
						$module_meta['version'],
						$conflict[$plugin_meta['package']][1],
						$conflict[$plugin_meta['package']][0]
					)
				) ||
				(
					isset($plugin_meta['conflict']) &&
					($plugin_meta['conflict'] = self::dep_normal($plugin_meta['conflict'])) &&
					isset($plugin_meta['conflict'][$name]) &&
					version_compare(
						$meta['version'],
						$plugin_meta['conflict'][$name][1],
						$plugin_meta['conflict'][$name][0]
					)
				)
			) {
				if ($check_result_plugins) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$check_result_plugins = false;
				$Page->warning(
					$L->conflict_plugin($plugin).
					(
					$conflict[$plugin_meta['package']][1] != 0 ? $L->compatible_package_versions(
						$require[$plugin_meta['package']][0].' '.$require[$plugin_meta['package']][1]
					) : $L->package_is_incompatible(
						$plugin_meta['package']
					)
					)
				);
			}
		}
		$check_result = $check_result && $check_result_plugins;
		unset($check_result_plugins, $plugin, $plugin_meta, $provide, $conflict);
		/**
		 * If some required packages missing
		 */
		$return_r = true;
		if (!empty($require)) {
			foreach ($require as $package => $details) {
				if ($return_r) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$return_r = false;
				$Page->warning(
					$L->package_or_functionality_not_found($details[1] ? "$package $details[0] $details[1]" : $package)
				);
			}
		}
		return $check_result && $return_r;
	}
	/**
	 * Check whether there is available supported DB engine
	 *
	 * @param string[] $db_support
	 *
	 * @return bool
	 */
	static protected function check_dependencies_db ($db_support) {
		/**
		 * Component doesn't support (and thus use) any DB engines, so we don't care what system have
		 */
		if (!$db_support) {
			return true;
		}
		$Core         = Core::instance();
		$Config       = Config::instance();
		$L            = Language::instance();
		$Page         = Page::instance();
		$check_result = false;
		if (!in_array($Core->db_type, $db_support)) {
			foreach ($Config->db as $database) {
				if (isset($database['type']) && in_array($database['type'], $db_support)) {
					$check_result = true;
					break;
				}
			}
			unset($database);
		}
		if (!$check_result) {
			$Page->warning(
				$L->compatible_databases_not_found(
					implode('", "', $db_support)
				)
			);
		} elseif (!$Config->core['simple_admin_mode']) {
			$Page->success(
				$L->compatible_databases(
					implode('", "', $db_support)
				)
			);
		}
		return $check_result;
	}
	/**
	 * Check whether there is available supported Storage engine
	 *
	 * @param string[] $storage_support
	 *
	 * @return bool
	 */
	static protected function check_dependencies_storage ($storage_support) {
		/**
		 * Component doesn't support (and thus use) any Storage engines, so we don't care what system have
		 */
		if (!$storage_support) {
			return true;
		}
		$Core         = Core::instance();
		$Config       = Config::instance();
		$L            = Language::instance();
		$Page         = Page::instance();
		$check_result = false;
		if (in_array($Core->storage_type, $storage_support)) {
			$check_result = true;
		} else {
			foreach ($Config->storage as $storage) {
				if (in_array($storage['connection'], $storage_support)) {
					$check_result = true;
					break;
				}
			}
			unset($storage);
		}
		if (!$check_result) {
			$Page->warning(
				$L->compatible_storages_not_found(
					implode('", "', $storage_support)
				)
			);
		} elseif (!$Config->core['simple_admin_mode']) {
			$Page->success(
				$L->compatible_storages(
					implode('", "', $storage_support)
				)
			);
		}
		return $check_result;
	}
	/**
	 * Check backward dependencies (during uninstalling/disabling)
	 *
	 * @param string $name Component name
	 * @param string $type Component type module|plugin
	 * @param string $mode Mode of checking for modules uninstall|disable
	 *
	 * @return bool
	 */
	static protected function check_backward_dependencies ($name, $type = 'module', $mode = 'disable') {
		switch ($type) {
			case 'module':
				$dir = MODULES."/$name";
				break;
			case 'plugin':
				$dir = PLUGINS."/$name";
				break;
			default:
				return false;
		}
		if (!file_exists("$dir/meta.json")) {
			return true;
		}
		$meta         = file_get_json("$dir/meta.json");
		$check_result = true;
		$Config       = Config::instance();
		$L            = Language::instance();
		$Page         = Page::instance();
		/**
		 * Checking for backward dependencies of modules
		 */
		$check_result_modules = true;
		foreach ($Config->components['modules'] as $module => $module_data) {
			/**
			 * If module uninstalled, disabled (in disable check mode), module name is the same as checking or meta.json file does not exists
			 * Then skip this module
			 */
			/** @noinspection NotOptimalIfConditionsInspection */
			if (
				$module_data['active'] == -1 ||
				(
					$mode == 'disable' && $module_data['active'] == 0
				) ||
				(
					$module == $name && $type == 'module'
				) ||
				!file_exists(MODULES."/$module/meta.json")
			) {
				continue;
			}
			$module_require = file_get_json(MODULES."/$module/meta.json");
			if (!isset($module_require['require'])) {
				continue;
			}
			$module_require = self::dep_normal($module_require['require']);
			if (
				isset($module_require[$meta['package']]) ||
				(
					isset($meta['provide']) && array_intersect(array_keys($module_require), (array)$meta['provide'])
				)
			) {
				if ($check_result_modules) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$check_result_modules = false;
				$Page->warning($L->this_package_is_used_by_module($module));
			}
		}
		$check_result = $check_result && $check_result_modules;
		unset($check_result_modules, $module, $module_data, $module_require);
		/**
		 * Checking for backward dependencies of plugins
		 */
		$check_result_plugins = true;
		foreach ($Config->components['plugins'] as $plugin) {
			if (
				(
					$plugin == $name && $type == 'plugin'
				) ||
				!file_exists(PLUGINS."/$plugin/meta.json")
			) {
				continue;
			}
			$plugin_require = file_get_json(PLUGINS."/$plugin/meta.json");
			if (!isset($plugin_require['require'])) {
				continue;
			}
			$plugin_require = self::dep_normal($plugin_require['require']);
			if (
				isset($plugin_require[$meta['package']]) ||
				(
					isset($meta['provide']) && array_intersect(array_keys($plugin_require), (array)$meta['provide'])
				)
			) {
				if ($check_result_plugins) {
					$Page->warning($L->dependencies_not_satisfied);
				}
				$check_result_plugins = false;
				$Page->warning($L->this_package_is_used_by_plugin($plugin));
			}
		}
		return $check_result && $check_result_plugins;
	}
	/**
	 * Normalize structure of `meta.json`
	 *
	 * Addition necessary items if they are not present and casting some string values to arrays in order to decrease number of checks in further code
	 *
	 * @param array $meta
	 *
	 * @return array mixed
	 */
	static protected function normalize_meta ($meta) {
		foreach (['db_support', 'storage_support', 'provide', 'require', 'conflict'] as $item) {
			$meta[$item] = isset($meta[$item]) ? (array)$meta[$item] : [];
		}
		foreach (['require', 'conflict'] as $item) {
			$meta[$item] = self::dep_normal($meta[$item]);
		}
		return $meta;
	}
	/**
	 * Function for normalization of dependencies structure
	 *
	 * @param array|string $dependence_structure
	 *
	 * @return array
	 */
	static protected function dep_normal ($dependence_structure) {
		$return = [];
		foreach ((array)$dependence_structure as $d) {
			preg_match('/^([^<=>!]+)([<=>!]*)(.*)$/', $d, $d);
			$return[$d[1]] = [
				isset($d[2]) && $d[2] ? str_replace('=>', '>=', $d[2]) : (isset($d[3]) && $d[3] ? '=' : '>='),
				isset($d[3]) && $d[3] ? $d[3] : 0
			];
		}
		return $return;
	}
}
