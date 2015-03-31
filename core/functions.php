<?php
/**
 * @package   CleverStyle CMS
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2011-2015, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
/**
 * Base system functions, do not edit this file, or make it very carefully
 * otherwise system workability may be broken
 */
use
	cs\Cache,
	cs\Config,
	cs\Index,
	cs\Language,
	cs\Page,
	cs\Text,
	cs\User;
/**
 * Auto Loading of classes
 */
spl_autoload_register(function ($class) {
	static $cache;
	if (!isset($cache)) {
		$cache = file_exists(CACHE.'/classes/autoload') ? file_get_json(CACHE.'/classes/autoload') : [];
	}
	if (isset($cache[$class])) {
		return require_once $cache[$class];
	}
	$prepared_class_name	= ltrim($class, '\\');
	if (substr($prepared_class_name, 0, 3) == 'cs\\') {
		$prepared_class_name	= substr($prepared_class_name, 3);
	}
	$prepared_class_name	= explode('\\', $prepared_class_name);
	$namespace				= count($prepared_class_name) > 1 ? implode('/', array_slice($prepared_class_name, 0, -1)) : '';
	$class_name				= array_pop($prepared_class_name);
	/**
	 * Try to load classes from different places. If not found in one place - try in another.
	 */
	if (
		_require_once($file = DIR."/core/classes/$namespace/$class_name.php", false) ||		//Core classes
		_require_once($file = DIR."/core/thirdparty/$namespace/$class_name.php", false) ||	//Third party classes
		_require_once($file = DIR."/core/traits/$namespace/$class_name.php", false) ||		//Core traits
		_require_once($file = ENGINES."/$namespace/$class_name.php", false) ||				//Core engines
		_require_once($file = MODULES."/../$namespace/$class_name.php", false)				//Classes in modules and plugins
	) {
		$cache[$class] = realpath($file);
		if (!is_dir(CACHE.'/classes')) {
			@mkdir(CACHE.'/classes', 0770, true);
		}
		file_put_json(CACHE.'/classes/autoload', $cache);
		return true;
	}
	return false;
}, true, true);
/**
 * Clean cache of classes autoload and customization
 */
function clean_classes_cache () {
	if (file_exists(CACHE.'/classes/autoload')) {
		unlink(CACHE.'/classes/autoload');
	}
	if (file_exists(CACHE.'/classes/modified')) {
		unlink(CACHE.'/classes/modified');
	}
}
/**
 * Get or set modified classes (used in Singleton trait)
 *
 * @param array|null $updated_modified_classes
 *
 * @return array
 */
function modified_classes ($updated_modified_classes = null) {
	static $modified_classes;
	if (!isset($modified_classes)) {
		$modified_classes = file_exists(CACHE.'/classes/modified') ? file_get_json(CACHE.'/classes/modified') : [];
	}
	if ($updated_modified_classes) {
		$modified_classes = $updated_modified_classes;
		file_put_json(CACHE.'/classes/modified', $modified_classes);
	}
	return $modified_classes;
}
/**
 * Correct termination
 *
 * @param bool|null $enable	Allows to disable shutdown function execution since there is no good way to un-register it
 */
function shutdown_function ($enable = null) {
	static $enable_internal = true;
	if ($enable !== null) {
		$enable_internal = $enable;
		return;
	}
	if (!$enable_internal) {
		return;
	}
	if (!class_exists('\\cs\\Core', false)) {
		return;
	}
	Index::instance(true)->__finish();
	Page::instance()->__finish();
	User::instance(true)->__finish();
}
/**
 * Enable of errors processing
 */
function errors_on () {
	error_reporting(defined('DEBUG') && DEBUG ? E_ALL : E_ERROR | E_WARNING | E_PARSE);
}
/**
 * Disabling of errors processing
 */
function errors_off () {
	error_reporting(0);
}
/**
 * Enabling of page interface
 */
function interface_on () {
	Page::instance()->interface	= true;
}
/**
 * Disabling of page interface
 */
function interface_off () {
	Page::instance()->interface	= false;
}
/**
 * Easy getting of translations
 *
 * @param string $item
 * @param mixed  $arguments There can be any necessary number of arguments here
 * @param mixed  $_
 *
 * @return string
 */
function __ ($item, $arguments = null, $_ = null) {
	static $L;
	if (!isset($L)) {
		$L = Language::instance();
	}
	if (func_num_args() > 1) {
		return $L->format($item, array_slice(func_get_args(), 1));
	} else {
		return $L->$item;
	}
}
/**
 * Get file url by it's destination in file system
 *
 * @param string		$source
 *
 * @return bool|string
 */
function url_by_source ($source) {
	$Config	= Config::instance(true);
	if (!$Config) {
		return false;
	}
	$source = realpath($source);
	if (mb_strpos($source, DIR) === 0) {
		return $Config->core_url().mb_substr($source, mb_strlen(DIR));
	}
	return false;
}
/**
 * Get file destination in file system by it's url
 *
 * @param string		$url
 *
 * @return bool|string
 */
function source_by_url ($url) {
	$Config	= Config::instance(true);
	if (!$Config) {
		return false;
	}
	if (mb_strpos($url, $Config->core_url()) === 0) {
		return DIR.mb_substr($url, mb_strlen($Config->core_url()));
	}
	return false;
}
/**
 * Public cache cleaning
 *
 * @return bool
 */
function clean_pcache () {
	$ok = true;
	$list = get_files_list(PUBLIC_CACHE, false, 'fd', true, true, 'name|desc');
	foreach ($list as $item) {
		if (is_writable($item)) {
			is_dir($item) ? @rmdir($item) : @unlink($item);
		} else {
			$ok = false;
		}
	}
	unset($list, $item);
	return $ok;
}
/**
 * Formatting of time in seconds to human-readable form
 *
 * @param int		$time	Time in seconds
 *
 * @return string
 */
function format_time ($time) {
	if (!is_numeric($time)) {
		return $time;
	}
	$L		= Language::instance();
	$res	= [];
	if ($time >= 31536000) {
		$time_x = round($time / 31536000);
		$time -= $time_x * 31536000;
		$res[] = $L->time($time_x, 'y');
	}
	if ($time >= 2592000) {
		$time_x = round($time / 2592000);
		$time -= $time_x * 2592000;
		$res[] = $L->time($time_x, 'M');
	}
	if($time >= 86400) {
		$time_x = round($time / 86400);
		$time -= $time_x * 86400;
		$res[] = $L->time($time_x, 'd');
	}
	if($time >= 3600) {
		$time_x = round($time / 3600);
		$time -= $time_x * 3600;
		$res[] = $L->time($time_x, 'h');
	}
	if ($time >= 60) {
		$time_x = round($time / 60);
		$time -= $time_x * 60;
		$res[] = $L->time($time_x, 'm');
	}
	if ($time > 0 || empty($res)) {
		$res[] = $L->time($time, 's');
	}
	return implode(' ', $res);
}
/**
 * Formatting of data size in bytes to human-readable form
 *
 * @param int		$size
 * @param bool|int	$round
 *
 * @return float|string
 */
function format_filesize ($size, $round = false) {
	if (!is_numeric($size)) {
		return $size;
	}
	$L		= Language::instance();
	$unit	= '';
	if($size >= 1099511627776) {
		$size /= 1099511627776;
		$unit = " $L->TB";
	} elseif($size >= 1073741824) {
		$size /= 1073741824;
		$unit = " $L->GB";
	} elseif ($size >= 1048576) {
		$size /= 1048576;
		$unit = " $L->MB";
	} elseif ($size >= 1024) {
		$size /= 1024;
		$unit = " $L->KB";
	} else {
		$size = "$size $L->Bytes";
	}
	return $round ? round($size, $round).$unit : $size.$unit;
}
/**
 * Get list of timezones
 *
 * @return array
 */
function get_timezones_list () {
	if (
		!class_exists('\\cs\\Cache', false) ||
		!($Cache = Cache::instance(true)) ||
		($timezones = $Cache->timezones) === false
	) {
		$tzs = timezone_identifiers_list();
		$timezones_ = $timezones = [];
		foreach ($tzs as $tz) {
			$offset		= (new DateTimeZone($tz))->getOffset(new DateTime);
			$offset_	=	($offset < 0 ? '-' : '+').
							str_pad(floor(abs($offset / 3600)), 2, 0, STR_PAD_LEFT).':'.
							str_pad(abs(($offset % 3600) / 60), 2, 0, STR_PAD_LEFT);
			$timezones_[(39600 + $offset).$tz] = [
				'key'	=> strtr($tz, '_', ' ')." ($offset_)",
				'value'	=> $tz
			];
		}
		unset($tzs, $tz, $offset);
		ksort($timezones_, SORT_NATURAL);
		/**
		 * @var array $offset
		 */
		foreach ($timezones_ as $tz) {
			$timezones[$tz['key']] = $tz['value'];
		}
		unset($timezones_, $tz);
		/** @noinspection NotOptimalIfConditionsInspection */
		if (isset($Cache) && $Cache) {
			$Cache->timezones = $timezones;
		}
	}
	return $timezones;
}
/**
 * Get multilingual value from $Config->core array
 *
 * @param string $item
 *
 * @return bool|string
 */
function get_core_ml_text ($item) {
	$Config	= Config::instance(true);
	if (!$Config) {
		return false;
	}
	return Text::instance()->process($Config->module('System')->db('texts'), $Config->core[$item], true);
}
/**
 * Pages navigation based on links
 *
 * @param int				$page		Current page
 * @param int				$total		Total pages number
 * @param callable|string	$url		if string - it will be formatted with sprintf with one parameter - page number<br>
 * 										if callable - one parameter will be given, callable should return url string
 * @param bool				$head_links	If <b>true</b> - links with rel="prev" and rel="next" will be added
 *
 * @return bool|string					<b>false</b> if single page, otherwise string, set of navigation links
 */
function pages ($page, $total, $url, $head_links = false) {
	if ($total == 1) {
		return false;
	}
	$Page	= Page::instance();
	$output	= [];
	if (is_callable($url)) {
		$url_func	= $url;
	} else {
		$original_url	= $url;
		$url_func		= function ($page) use ($original_url) {
			return sprintf($original_url, $page);
		};
	}
	$base_url	= Config::instance()->base_url();
	$url		= function ($page) use ($url_func, $base_url) {
		$url	= $url_func($page);
		if (is_string($url) && strpos($url, 'http') !== 0) {
			$url	= $base_url.'/'.ltrim($url, '/');
		}
		return $url;
	};
	if ($total <= 11) {
		for ($i = 1; $i <= $total; ++$i) {
			$output[]	= [
				$i,
				[
					'href'	=> $i == $page ? false : $url($i),
					'class'	=> $i == $page ? 'uk-button uk-button-primary uk-frozen' : 'uk-button'
				]
			];
			if ($head_links && ($i == $page - 1 || $i == $page + 1)) {
				$Page->link([
					'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
					'rel'	=> $i == $page - 1 ? 'prev' : ($i == $page + 1 ? 'next' : false)
				]);
			}
		}
	} else {
		if ($page <= 5) {
			for ($i = 1; $i <= 7; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> $i == $page ? false : $url($i),
						'class'	=> $i == $page ? 'uk-button uk-button-primary uk-frozen' : 'uk-button'
					]
				];
				if ($head_links&& ($i == $page - 1 || $i == $page + 1)) {
					$Page->link([
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'rel'	=> $i == $page - 1 ? 'prev' : ($i == $page + 1 ? 'next' : false)
					]);
				}
			}
			$output[]	= [
				'...',
				[
					'class'	=> 'uk-button uk-frozen'
				]
			];
			for ($i = $total - 2; $i <= $total; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'class'	=> 'uk-button'
					]
				];
			}
		} elseif ($page >= $total - 4) {
			for ($i = 1; $i <= 3; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'class'	=> 'uk-button'
					]
				];
			}
			$output[]	= [
				'...',
				[
					'class'	=> 'uk-button uk-frozen'
				]
			];
			for ($i = $total - 6; $i <= $total; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> $i == $page ? false : $url($i),
						'class'	=> $i == $page ? 'uk-button uk-button-primary uk-frozen' : 'uk-button'
					]
				];
				if ($head_links && ($i == $page - 1 || $i == $page + 1)) {
					$Page->link([
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'rel'	=> $i == $page - 1 ? 'prev' : ($i == $page + 1 ? 'next' : false)
					]);
				}
			}
		} else {
			for ($i = 1; $i <= 2; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'class'	=> 'uk-button'
					]
				];
			}
			$output[]	= [
				'...',
				[
					'class'	=> 'uk-button uk-frozen'
				]
			];
			for ($i = $page - 1; $i <= $page + 3; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> $i == $page ? false : $url($i),
						'class'	=> $i == $page ? 'uk-button uk-button-primary uk-frozen' : 'uk-button'
					]
				];
				if ($head_links && ($i == $page - 1 || $i == $page + 1)) {
					$Page->link([
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'rel'	=> $i == $page - 1 ? 'prev' : ($i == $page + 1 ? 'next' : false)
					]);
				}
			}
			$output[]	= [
				'...',
				[
					'class'	=> 'uk-button uk-frozen'
				]
			];
			for ($i = $total - 1; $i <= $total; ++$i) {
				$output[]	= [
					$i,
					[
						'href'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'class'	=> 'uk-button'
					]
				];
			}
		}
	}
	return h::a($output);
}
/**
 * Pages navigation based on buttons (for search forms, etc.)
 *
 * @param int					$page		Current page
 * @param int					$total		Total pages number
 * @param bool|callable|string	$url		Adds <i>formaction</i> parameter to every button<br>
 * 											if <b>false</b> - only form parameter <i>page</i> will we added<br>
 * 											if string - it will be formatted with sprintf with one parameter - page number<br>
 * 											if callable - one parameter will be given, callable should return url string
 *
 * @return bool|string						<b>false</b> if single page, otherwise string, set of navigation buttons
 */
function pages_buttons ($page, $total, $url = false) {
	if ($total == 1) {
		return false;
	}
	$output	= [];
	if (!is_callable($url)) {
		$original_url	= $url;
		$url			= function ($page) use ($original_url) {
			return sprintf($original_url, $page);
		};
	}
	if ($total <= 11) {
		for ($i = 1; $i <= $total; ++$i) {
			$output[]	= [
				$i,
				[
					'formaction'	=> $i == $page || $url === false ? false : $url($i),
					'value'			=> $i,
					'type'			=> $i == $page ? 'button' : 'submit',
					'class'			=> $i == $page ? 'uk-button-primary uk-frozen' : false
				]
			];
		}
	} else {
		if ($page <= 5) {
			for ($i = 1; $i <= 7; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> $i == $page || $url === false ? false : $url($i),
						'value'			=> $i == $page ? false : $i,
						'type'			=> $i == $page ? 'button' : 'submit',
						'class'			=> $i == $page ? 'uk-button-primary uk-frozen' : false
					]
				];
			}
			$output[]	= [
				'...',
				[
					'type'			=> 'button',
					'disabled'
				]
			];
			for ($i = $total - 2; $i <= $total; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'value'			=> $i,
						'type'			=> 'submit'
					]
				];
			}
		} elseif ($page >= $total - 4) {
			for ($i = 1; $i <= 3; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'value'			=> $i,
						'type'			=> 'submit'
					]
				];
			}
			$output[]	= [
				'...',
				[
					'type'			=> 'button',
					'disabled'
				]
			];
			for ($i = $total - 6; $i <= $total; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> $i == $page || $url === false ? false : $url($i),
						'value'			=> $i == $page ? false : $i,
						'type'			=> $i == $page ? 'button' : 'submit',
						'class'			=> $i == $page ? 'uk-button-primary uk-frozen' : false
					]
				];
			}
		} else {
			for ($i = 1; $i <= 2; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'value'			=> $i,
						'type'			=> 'submit'
					]
				];
			}
			$output[]	= [
				'...',
				[
					'type'			=> 'button',
					'disabled'
				]
			];
			for ($i = $page - 1; $i <= $page + 3; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> $i == $page || $url === false ? false : $url($i),
						'value'			=> $i == $page ? false : $i,
						'type'			=> $i == $page ? 'button' : 'submit',
						'class'			=> $i == $page ? 'uk-button-primary uk-frozen' : false
					]
				];
			}
			$output[]	= [
				'...',
				[
					'type'			=> 'button',
					'disabled'
				]
			];
			for ($i = $total - 1; $i <= $total; ++$i) {
				$output[]	= [
					$i,
					[
						'formaction'	=> is_callable($url) ? $url($i) : sprintf($url, $i),
						'value'			=> $i,
						'type'			=> 'submit'
					]
				];
			}
		}
	}
	return h::{'button.uk-button[name=page]'}($output);
}
/**
 * Checks whether specified functionality available or not
 *
 * @param string|string[]	$functionality	One functionality or array of them
 *
 * @return bool								<i>true</i> if all functionality available, <i>false</i> otherwise
 */
function functionality ($functionality) {
	if (is_array($functionality)) {
		$result	= true;
		foreach ($functionality as $f) {
			$result	= $result && functionality($f);
		}
		return $result;
	}
	$all	= Cache::instance()->get("functionality", function () {
		$functionality	= [];
		$components		= Config::instance()->components;
		foreach ($components['modules'] as $module => $module_data) {
			if ($module_data['active'] != 1 || !file_exists(MODULES."/$module/meta.json")) {
				continue;
			}
			$meta			= file_get_json(MODULES."/$module/meta.json");
			if (!isset($meta['provide'])) {
				continue;
			}
			/** @noinspection SlowArrayOperationsInLoopInspection */
			$functionality	= array_merge(
				$functionality,
				(array)$meta['provide']
			);
		}
		unset($module, $module_data, $meta);
		foreach ($components['plugins'] as $plugin) {
			if (!file_exists(PLUGINS."/$plugin/meta.json")) {
				continue;
			}
			$meta			= file_get_json(PLUGINS."/$plugin/meta.json");
			if (!isset($meta['provide'])) {
				continue;
			}
			/** @noinspection SlowArrayOperationsInLoopInspection */
			$functionality	= array_merge(
				$functionality,
				(array)$meta['provide']
			);
		}
		return $functionality;
	});
	return in_array($functionality, $all);
}
/**
 * Returns system version
 *
 * @return string
 */
function system_version () {
	return file_get_json(MODULES.'/System/meta.json')['version'];
}
