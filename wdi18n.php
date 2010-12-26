<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdI18n
{
	static public $load_paths = array();

	#
	# Language codes: http://www.w3.org/TR/REC-html40/struct/dirlang.html#langcodes
	#

	static protected $config;

	static public function __static_construct()
	{
		$fragments = WdConfig::get('i18n');

		$config = call_user_func_array('array_merge', $fragments);

		self::$config = $config;

		self::$native = $config['native'];
		self::$language = $config['language'];
		self::$languages = array_combine($config['languages'], $config['languages']);

		self::setLanguage(self::$language);
		self::setTimezone($config['timezone']);
	}

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

		self::load_conventions($language, $country);
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

	static protected function load_conventions($language, $country=null)
	{
		self::$conventions = localeconv();

		$conventions = require dirname(__FILE__) . '/i18n/conv/' . $language . '.php';

		if ($language != 'en')
		{
			$conventions = wd_array_merge_recursive(require dirname(__FILE__) . '/i18n/conv/en.php', $conventions);
		}

		self::$conventions += $conventions;
	}

	static protected function load_catalog($language, $root)
	{
		static $loaded_catalogs;

		// TODO-20101224: reimplement country code: fr-FR AND fr

		$filename = $root . '/i18n/' . $language . '.php';

		if (isset($loaded_catalogs[$filename]))
		{
			return $loaded_catalogs[$filename];
		}

		if (!file_exists($filename))
		{
			$loaded_catalogs[$filename] = false;

			return;
		}

		$messages = wd_array_flatten(require $filename);

		$loaded_catalogs[$filename] = $messages;

		return $messages;

		/*
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
		*/
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
					WdFileCache::T_REPOSITORY => WdCore::$config['repository.cache'] . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);
		}

		return self::$cache;
	}

	/**
	 * The `loading` static variable is used to break inifite recursion while loading pending catalogs
	 * which might happen if the loading process triggers and error (or an exception) which in
	 * turn requests a translation, which in turn try to load pending catalogs...
	 *
	 * Recursion is prevented on a language basis.
	 */

	static private $loading = array();

	static protected function load_catalogs($language=null)
	{
		if (!$language)
		{
			$language = self::$language;
		}

		if (!empty(self::$loading[$language]))
		{
			return;
		}

		self::$loading[$language] = true;

		if (!empty(self::$messages[$language]))
		{
			$messages = self::load_catalogs_construct($language);
		}
		else
		{
			if (WdCore::$config['cache catalogs'])
			{
				$cache = self::getCache();

				#
				# There are no messages yet, this is the first time the function is called, we can use
				# the cache.
				#

				$messages = $cache->load('i18n_' . $language, array(__CLASS__, __FUNCTION__ . '_construct'), $language);
			}
			else
			{
				$messages = self::load_catalogs_construct($language);
			}
		}

		if ($language != 'en')
		{
			$native_messages = self::load_catalogs('en');

			$messages += $native_messages;
		}

		self::$messages[$language] = empty(self::$messages[$language]) ? $messages : $messages + self::$messages[$language];

		if (0)
		{
			ksort(self::$messages[$language]);

			echo 'load_catalogs: ' . wd_dump(self::$load_paths) . wd_dump(self::$messages[$language]);
		}

//		self::$load_paths = array();
		self::$loading[$language] = false;

		return self::$messages[$language];
	}

	static public function load_catalogs_construct($language)
	{
		$rc = array();

		foreach (self::$load_paths as $root)
		{
			$messages = self::load_catalog($language, $root);

			if (!$messages)
			{
				continue;
			}

			$rc += $messages;
		}

		return $rc;
	}

	static private $loaded_languages = array();

	/**
	 * Translates a string.
	 *
	 * @param string $str
	 * @param array $args
	 * @param array $options
	 */

	static public function translate($str, array $args=array(), array $options=array())
	{
		if (!$str)
		{
			return $str;
		}

		$language = empty($options['language']) ? self::$language : $options['language'];

		if (empty(self::$loaded_languages[$language]))
		{
			self::load_catalogs();

			self::$loaded_languages[$language] = true;
		}

		$messages = self::$messages[$language];
		$suffix = null;

		if ($args && array_key_exists(':count', $args))
		{
			$count = $args[':count'];

			if ($count == 0)
			{
				$suffix = '.none';
			}
			else if ($count == 1)
			{
				$suffix = '.one';
			}
			else
			{
				$suffix = '.other';
			}
		}

		if (isset($options['scope']))
		{
			$scope = $options['scope'];

			if (is_array($scope))
			{
				while ($scope)
				{
					$try = implode('.', $scope) . '.' . $str;

					array_shift($scope);

					if (isset($messages[$try . $suffix]))
					{
						$str = $try;

						break;
					}
				}
			}
			else if (isset($messages[$scope . '.' . $str . $suffix]))
			{
				$str = $scope . '.' . $str;
			}
		}

		if (isset($messages[$str . $suffix]))
		{
			$str = $messages[$str . $suffix];
		}
		else if (isset($options['default']))
		{
			$default = (array) $options['default'];

			foreach ($default as $str)
			{
				if (isset($messages[$str . $suffix]))
				{
					$str = $messages[$str . $suffix];

					break;
				}
			}

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

	static public function store_translation($language, $translation)
	{
		$translation = wd_array_flatten($translation);

		self::$messages[$language] = empty(self::$messages[$language]) ? $translation : $translation + self::$messages[$language];
	}
}

function t($str, array $args=array(), array $options=array())
{
	return WdI18n::translate($str, $args, $options);
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

	$conv = WdI18n::$conventions;

	$size = number_format($size, ($size - floor($size) < .009) ? 0 : 2, $conv['decimal_point'], $conv['thousands_sep']);

	return t($str, array(':size' => $size));
}

function wd_format_number($number)
{
	$conv = WdI18n::$conventions;

	return number_format($number, ($number - floor($number) < .009) ? 0 : 2, $conv['decimal_point'], $conv['thousands_sep']);
}

function wd_format_time($time, $format='default')
{
	if ($format[0] == ':')
	{
		$format = substr($format, 1);
	}

	if (isset(WdI18n::$conventions['date']['formats'][$format]))
	{
		$format = WdI18n::$conventions['date']['formats'][$format];
	}

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