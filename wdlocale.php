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

	static protected $config = array
	(
		'cache catalogs' => false,
		'native' => 'en',
		'language' => 'en-US',
		'languages' => array('en'),
		'timezone' => 'TZ=US/Eastern'
	);

	static public function autoconfig(array $configs)
	{
		array_unshift($configs, self::$config);

		self::$config = call_user_func_array('array_merge', $configs);

		self::$native = self::$config['native'];
		self::$language = self::$config['language'];
		self::$languages = array_combine(array_values(self::$config['languages']), self::$config['languages']);

		self::setLanguage(self::$language);
		self::setTimezone(self::$config['timezone']);
	}

	static protected $pendingCatalogs = array();
	static public $messages = array();

	static public $native;
	static public $language;
	static public $languages;
	static public $conventions;

	static public function setLanguage($language)
	{
		self::$language = $language;

		setlocale(LC_ALL, 'fr_FR.UTF-8', 'fr_FR.UTF8', 'fr.UTF-8', 'fr.UTF8');

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

	/**
	 * Create a path made of the root, the language and the file.
	 * One would override this method to use a custom scheme e.g.
	 * <root>/<language>-<file>
	 *
	 * @param $root
	 * @param $file
	 * @param $language
	 * @return string
	 * The path to the localized file
	 */

	/*
	protected function makeFilename($root, $file, array $options=array())
	{
		$options += array
		(
			'language' => 'en',
			'template' => '{root}/language/{language}/{file}',
			'no-check' => false
		);

		$rc = strtr
		(
			$options['template'], array
			(
				'{file}' => $file,
				'{language}' => $options['language'],
				'{root}' => $root
			)
		);

		// FIXME: on OSX, realpath() returns a valid path even if the file does not exists

		if (!is_file($rc)) // FIXME: no-check ??
		{
			//echo t('unknown file \1<br />', array($rc));

			return false;
		}

		//echo t('try: \1 == \2', array($rc, realpath($rc)));

		return $options['no-check'] ? $rc : realpath($rc);
	}

	private function getFilename($root, $file, array $options=array())
	{
		#
		# try to get the localized file
		#

		$path = $this->makeFilename($root, $file, $options + array('language' => $this->language));

		if ($path)
		{
			return $path;
		}
		else if ($this->language == 'en')
		{
			#
			# if the language is already 'en' we quit here
			#

			return;
		}

		#
		# fallback to the non-localized file
		#

		return $this->makeFilename($root, $file, $options);
	}
	*/

	static public function loadCatalog($root, array $options=array())
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

		$catalog = array();

		$location = getcwd();

		chdir($root);

		foreach ($codes as $code)
		{
			$file = 'i18n' . DIRECTORY_SEPARATOR . $code . '.php';

			if (!file_exists($file))
			{
				continue;
			}

			$catalog += require $file;
		}

		//var_dump($catalog);

		chdir($location);

		return $catalog;
	}

	static public function addPath($root)
	{
		$i18n = $root . DIRECTORY_SEPARATOR . 'i18n';

		if (!is_dir($i18n))
		{
			return;
		}

		self::$pendingCatalogs[] = $root;
	}

	static protected $cache;

	static public function getCache()
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

	static protected function loadPendingCatalogs()
	{
		if (self::$loading)
		{
			return;
		}

		self::$loading = true;

		if (self::$messages)
		{
			$catalog = self::loadPendingCatalogs_construct();
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

				$catalog = $cache->load('i18n_' . WdLocale::$language, array(__CLASS__, __FUNCTION__ . '_construct'));
			}
			else
			{
				$cache->delete('i18n_' . WdLocale::$language);

				$catalog = self::loadPendingCatalogs_construct();
			}
		}

		self::$messages = $catalog + self::$messages;

		if (0)
		{
			ksort(self::$messages);

			echo 'loadPendingCatalogs: ' . wd_dump(self::$pendingCatalogs) . wd_dump(self::$messages);
		}

		self::$pendingCatalogs = array();
		self::$loading = false;
	}

	static public function loadPendingCatalogs_construct()
	{
		$messages = array();

		foreach (self::$pendingCatalogs as $root)
		{
			$catalog = self::loadCatalog($root);

			if (!$catalog)
			{
				continue;
			}

			$messages = $messages + $catalog;
		}

		return $messages;
	}

	/**
	 * Get the contents of a localized file.
	 *
	 * @param $file
	 * @param $root
	 * @return unknown_type
	 */

	/*
	public function getFileContents($file, $root)
	{
		$final = $this->getFilename($root, $file);

		if (!$final)
		{
			return;
		}

		return file_get_contents($final);
	}
	*/

	static private $_localize_args;

	static public function translate($str, $args)
	{
		if (!$str)
		{
			return $str;
		}

		if (self::$pendingCatalogs)
		{
			self::loadPendingCatalogs();
		}

		$catalog = self::$messages;

		if ($str{0} == '@' && array_key_exists('count', $args))
		{
			echo "using plural selector: $str<br />";
		}

		#
		#
		#

		if (isset($catalog[$str]))
		{
			$str = $catalog[$str];
		}
		else if (0)
		{
			$_SESSION['wddebug']['messages']['debug'][] = "localize: $str";
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

function t($str, array $args=array())
{
	return WdLocale::translate($str, $args);
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

	$size = number_format($size, $conv['frac_digits'], $conv['decimal_point'], $conv['thousands_sep']);

	return t($str, array(':size' => $size));
}