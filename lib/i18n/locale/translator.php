<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class WdLocaleTranslator extends WdObject
{
	static private $translators=array();

	static public function get($id)
	{
		if (isset(self::$translators[$id]))
		{
			return self::$translators[$id];
		}

		return self::$translators[$id] = new WdLocaleTranslator($id);
	}

	static protected $cache;

	static protected function get_cache()
	{
		global $core;

		if (!self::$cache)
		{
			self::$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_COMPRESS => true,
					WdFileCache::T_REPOSITORY => $core->config['repository.cache'] . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);
		}

		return self::$cache;
	}

	static public function messages_construct($id)
	{
		$messages = array();

		foreach (WdI18n::$load_paths as $path)
		{
			$filename = $path . '/i18n/' . $id . '.php';

			if (!file_exists($filename))
			{
				continue;
			}

			$messages += wd_array_flatten(require $filename);
		}

		return $messages;
	}

	/**
	 * @var array Translation messages.
	 */
	protected $messages;

	protected function __get_messages()
	{
		global $core;

		$messages = array();
		$id = $this->id;

		if ($core->config['cache catalogs'])
		{
			$messages = self::get_cache()->load('i18n_' . $id, array(__CLASS__, 'messages_construct'), $id);
		}
		else
		{
			$messages = self::messages_construct($id);
		}

		if ($this->fallback)
		{
			$messages += $this->fallback->messages;
		}

		return $messages;
	}

	/**
	 * @var WdLocaleTranslator Fallback translator
	 */
	protected $fallback;

	protected function __get_fallback()
	{
		list($id, $territory) = explode('-', $this->id) + array(1 => null);

		if (!$territory && $id == 'en')
		{
			return;
		}
		else if (!$territory)
		{
			$id = 'en';
		}

		return self::get($id);
	}

	/**
	 * @var Locale id for this translator.
	 */
	protected $id;

	/**
	 * Constructor.
	 *
	 * @param string $id Locale identifier
	 */
	protected function __construct($id)
	{
		unset($this->messages);
		unset($this->fallback);

		$this->id = $id;
	}

	public function __invoke($native, array $args=array(), array $options=array())
	{
		$native = (string) $native;
		$messages = $this->messages;
		$translated = null;

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

		$scope = isset($options['scope']) ? $options['scope'] : array();

		if (is_array($scope))
		{
			$scope = implode('.', $scope);
		}

		$i18n_scope = WdI18n::get_scope();

		if ($i18n_scope && $scope)
		{
			$scope = $i18n_scope . '.' . $scope;
		}
		else if ($i18n_scope)
		{
			$scope = $i18n_scope;
		}

		while ($scope)
		{
			$try = $scope . '.' . $native . $suffix;

			if (isset($messages[$try]))
			{
				$translated = $messages[$try];

				break;
			}

			$pos = strpos($scope, '.');

			if ($pos === false)
			{
				break;
			}

			$scope = substr($scope, $pos + 1);
		}

		if (!$translated)
		{
			if (isset($messages[$native . $suffix]))
			{
				$translated = $messages[$native . $suffix];
			}
		}

		if (!$translated)
		{
			if (!empty($options['default']))
			{
				$native = $options['default'];
				unset($options['default']);

				return $this->__invoke($native, $args, $options);
			}

			$translated = $native;
		}

		if ($args)
		{
			$translated = self::resolve($translated, $args);
		}

		return $translated;
	}

	/**
	 * Resolves the given string by replacing placeholders with the given values.
	 *
	 * @param string $str The string to resolve.
	 * @param array $args An array of replacement for the plaecholders. Occurences in $str of any
	 * key in $args are replaced with the corresponding sanitized value. The sanitization function
	 * depends on the first character of the key:
	 *
	 * * :key: Replace as is. Use this for text that has already been sanitized.
	 * * !key: Sanitize using the `wd_entities()` function.
	 * * %key: Sanitize using the `wd_entities()` function and wrap inside a "EM" markup.
	 *
	 * Numeric indexes can also be used e.g '\2' or "{2}" are replaced by the value of the index
	 * "2".
	 *
	 * @return string
	 */
	static public function resolve($str, array $args=array())
	{
		if (!$args)
		{
			return $str;
		}

		$holders = array();

		$i = 0;

		foreach ($args as $key => $value)
		{
			$i++;

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
			else if (is_string($key))
			{
				switch ($key{0})
				{
					case ':': break;
					case '!': $value = wd_entities($value); break;
					case '%': $value = '<em>' . wd_entities($value) . '</em>'; break;
				}
			}

			if (is_numeric($key))
			{
				$key = '\\' . $i;
				$holders['{' . $i . '}'] = $value;
			}

			$holders[$key] = $value;
		}

		return strtr($str, $holders);
	}
}