<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdLocale
{
	#
	# Language codes: http://www.w3.org/TR/REC-html40/struct/dirlang.html#langcodes
	#

	static protected $config;

	static public function __static_construct()
	{
		$fragments = WdConfig::get('locale');

		$config = call_user_func_array('array_merge', $fragments);

		self::$config = $config;

		self::$native = $config['native'];
		self::$language = $config['language'];
		self::$languages = array_combine($config['languages'], $config['languages']);

		self::setLanguage(self::$language);
		self::setTimezone($config['timezone']);
	}

	static protected $pending_catalogs = array();
	static public $messages = array();

	static public $native;
	static public $language;
	static public $languages;
	static public $conventions;

	static public function setLanguage($language)
	{
		self::$language = $language;

		list($language, $country) = explode('-', $language) + array(1 => null);

		if (!$country)
		{
			static $country_by_language = array
			(
				'en' => 'US'
			);

			$country = isset($country_by_language[$language]) ? $country_by_language[$language] : strtoupper($language);
		}

		setlocale(LC_ALL, "{$language}_{$country}.UTF-8");

		self::$conventions = localeconv();
	}

	static public function setTimezone($timezone)
	{
		#
		# if the 'timezone' is numeric e.g. 3600, we get
		# the associated abbr to use with the
		# date_default_timezone_set() function
		#

		if (is_numeric($timezone))
		{
			$timezone = timezone_name_from_abbr(null, $timezone, 0);
		}

		date_default_timezone_set($timezone);
	}

	static protected function load_catalog($root, array $options=array())
	{
		//echo "loadCatalog: $root<br />";

		$codes = array('en-US', 'en');

		if (self::$language != 'en' && self::$language != 'en-US')
		{
			list($language, $country) = explode('_', self::$language) + array(1 => null);

			array_unshift($codes, $language);

			if ($country)
			{
				array_unshift($codes, self::$language);
			}
		}

		#
		# load all catalogs
		#

		$messages = array();

		$location = getcwd();

		chdir($root);

		foreach ($codes as $code)
		{
			$file = 'i18n' . DIRECTORY_SEPARATOR . $code . '.php';

			if (!file_exists($file))
			{
				continue;
			}

			$messages += wd_array_flatten(require $file);
		}

		chdir($location);

		return $messages;
	}

	static public function addPath($root)
	{
		$i18n = $root . DIRECTORY_SEPARATOR . 'i18n';

		if (!is_dir($i18n))
		{
			return;
		}

		self::$pending_catalogs[] = $root;
	}

	static protected $cache;

	static protected function getCache()
	{
		if (!self::$cache)
		{
			self::$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_COMPRESS => true,
					WdFileCache::T_REPOSITORY => WdCore::getConfig('repository.cache') . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);
		}

		return self::$cache;
	}

	/**
	 * The `loading` static variable is used to break inifite loop while loading pending catalogs
	 * which might happen if the loading process triggers and error (or an exception) which in
	 * turn requests a translation, which in turn try to load pending catalogs...
	 */

	static protected $loading;

	static protected function load_pending_catalogs()
	{
		if (self::$loading)
		{
			return;
		}

		self::$loading = true;

		if (self::$messages)
		{
			$messages = self::load_pending_catalogs_construct();
		}
		else
		{
			$cache = self::getCache();

			if (self::$config['cache catalogs'])
			{
				#
				# There are no messages yet, this is the first time the function is called, we can use
				# the cache.
				#

				$messages = $cache->load('i18n_' . WdLocale::$language, array(__CLASS__, __FUNCTION__ . '_construct'));
			}
			else
			{
				$cache->delete('i18n_' . WdLocale::$language);

				$messages = self::load_pending_catalogs_construct();
			}
		}

		self::$messages = $messages + self::$messages;

		if (0)
		{
			ksort(self::$messages);

			echo 'load_pending_catalogs: ' . wd_dump(self::$pending_catalogs) . wd_dump(self::$messages);
		}

		self::$pending_catalogs = array();
		self::$loading = false;
	}

	static public function load_pending_catalogs_construct()
	{
		$rc = array();

		foreach (self::$pending_catalogs as $root)
		{
			$messages = self::load_catalog($root);

			if (!$messages)
			{
				continue;
			}

			$rc += $messages;
		}

		return $rc;
	}

	/**
	 * Get the contents of a localized file.
	 *
	 * @param $file
	 * @param $root
	 * @return unknown_type
	 */

	static public function translate($str, array $args=array(), array $options=array())
	{
		if (!$str)
		{
			return $str;
		}

		if (self::$pending_catalogs)
		{
			self::load_pending_catalogs();
		}

		$catalog = self::$messages;

		if ($str{0} == '@' && array_key_exists('count', $args))
		{
			echo "using plural selector: $str<br />";
		}

		#
		#
		#

		$try = $str;

		if (isset($options['scope']))
		{
			$scope = $options['scope'];

			if (is_array($scope))
			{
				$scope = implode('.', $scope);
			}

			$try = $scope . '.' . $str;
		}

		if (isset($catalog[$try]))
		{
			$str = $catalog[$try];
		}
		else if (isset($options['default']))
		{
			$default = $options['default'];

			$str = $default;

			if (0)
			{
				$_SESSION['wddebug']['messages']['debug'][] = "localize: $str";
			}
		}

		if ($args)
		{
			$holders = array();

			$i = 0;

			foreach ($args as $key => $value)
			{
				$i++;

				if (is_numeric($key))
				{
					$key = '\\' . $i;
				}

				if (is_array($value) || is_object($value))
				{
					$value = wd_dump($value);
				}
				else if (is_bool($value))
				{
					$value = $value ? 'true' : 'false';
				}
				else if (is_null($value))
				{
					$value = '<em>null</em>';
				}
				else
				{
					switch ($key{0})
					{
						case ':': break;
						case '!': $value = wd_entities($value); break;
						case '%': $value = '<em>' . wd_entities($value) . '</em>'; break;
					}
				}

				$holders[$key] = $value;
			}

			$str = strtr($str, $holders);
		}

		return $str;
	}
}

function t($str, array $args=array(), array $options=array())
{
	return WdLocale::translate($str, $args, $options);
}

function wd_format_size($size)
{
	if ($size < 1024)
	{
		$str = ":size\xC2\xA0b";
	}
	else if ($size < 1024 * 1024)
	{
		$str = ":size\xC2\xA0Kb";
		$size = $size / 1024;
	}
	else if ($size < 1024 * 1024 * 1024)
	{
		$str = ":size\xC2\xA0Mb";
		$size = $size / (1024 * 1024);
	}
	else
	{
		$str = ":size\xC2\xA0Gb";
		$size = $size / (1024 * 1024 * 1024);
	}

	$conv = WdLocale::$conventions;

	$size = number_format($size, ($size - floor($size) < .009) ? 0 : 2, $conv['decimal_point'], $conv['thousands_sep']);

	return t($str, array(':size' => $size));
}

function wd_format_time($time, $format=':default')
{
	if ($format[0] == ':')
	{
		$format = 'date.formats.' . substr($format, 1);
	}

	$format = t($format);

	if (is_string($time))
	{
		$time = strtotime($time);
	}

	return strftime($format, $time);
}

function wd_array_flatten($array, $separator='.', $depth=0)
{
	$rc = array();

	if (is_array($separator))
	{
		foreach ($array as $key => $value)
		{
			if (!is_array($value))
			{
				$rc[$key . ($depth ? $separator[1] : '')] = $value;

				continue;
			}

			$values = wd_array_flatten($value, $separator, $depth + 1);

			foreach ($values as $vkey => $value)
			{
				$rc[$key . ($depth ? $separator[1] : '') . $separator[0] . $vkey] = $value;
			}
		}
	}
	else
	{
		foreach ($array as $key => $value)
		{
			if (!is_array($value))
			{
				$rc[$key] = $value;

				continue;
			}

			$values = wd_array_flatten($value, $separator, $depth + 1);

			foreach ($values as $vkey => $value)
			{
				$rc[$key . $separator . $vkey] = $value;
			}
		}
	}

	return $rc;
}