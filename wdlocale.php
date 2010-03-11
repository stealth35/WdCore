<?php

/* ***** BEGIN LICENSE BLOCK *****
 *
 * This file is part of WdCore (http://www.weirdog.com/wdcore/).
 *
 * Software License Agreement (New BSD License)
 *
 * Copyright (c) 2007-2010, Olivier Laviale
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Olivier Laviale nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * ***** END LICENSE BLOCK ***** */

class WdLocale
{
	#
	# Language codes: http://www.w3.org/TR/REC-html40/struct/dirlang.html#langcodes
	#

	static protected $config = array
	(
		'cache catalogs' => true,
		'native' => 'en',
		'language' => 'en-US',
		'languages' => array('en'),
		'timezone' => 'TZ=US/Eastern'
	);

	static public function autoconfig()
	{
		$configs = func_get_args();

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


	static public function setLanguage($language)
	{
		self::$language = $language;

		setlocale(LC_TIME, 'fr_FR.UTF8', 'fr.UTF8');

		//echo "setLanguage: $language<br />";

		/*
		#
		# from http://www.w3.org/WAI/ER/IG/ert/iso639.htm
		#
		# 'fr' and 'fr_FR' never worked for me on the servers I've tried. On the othe hand,
		# the complete litteral 'french' seams to always work.
		#

		static $names = array
		(
			'cn' => 'chinese',
			'de' => 'german',
			'en' => 'english',
			'es' => 'spanish',
			'fr' => 'french',
			'it' => 'italian'
		);

		#
		# I chose to only localize the time,
		# but you can choose to localize everything if you want to.
		#

		setlocale(LC_TIME, $language, isset($names[$language]) ? $names[$language] : null);
		*/
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

	static protected function loadPendingCatalogs()
	{
		// TODO-20100223: caching should be an option

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

	static public function translate($str, $args, array $catalog=array())
	{
		if (self::$pendingCatalogs)
		{
			self::loadPendingCatalogs();
		}

		if (!$catalog)
		{
			$catalog = self::$messages;
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
			$_SESSION['wddebug']['debug'][] = "localize: $str";
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