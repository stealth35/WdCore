<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class WdI18n
{
	static public $load_paths = array();

	static protected $config;

	static public function __static_construct()
	{
		global $core;

		self::$config = $config = $core->configs['i18n'];

		self::$native = $config['native'];
		self::$language = $config['language'];

		self::setLanguage(self::$language);
		self::setTimezone($config['timezone']);
	}

	static public $messages = array();

	static public $native;
	static public $language;
	static public $locale;

	static public function setLanguage($language)
	{
		self::$language = $language;
		self::$locale = WdLocale::get($language);

//		WdDebug::trigger("set language $language");

		list($language, $territory) = explode('-', $language) + array(1 => null);

		if (!$territory)
		{
			static $territory_by_language = array
			(
				'en' => 'US',
				'cs' => 'CZ'
			);

			$territory = isset($territory_by_language[$language]) ? $territory_by_language[$language] : strtoupper($language);
		}

		setlocale(LC_ALL, "{$language}_{$territory}.UTF-8");
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

	static private $scope;
	static private $scope_chain = array();

	static public function push_scope($scope)
	{
		array_push(self::$scope_chain, self::$scope);

		self::$scope = (array) $scope;
	}

	static public function pop_scope()
	{
		self::$scope = array_pop(self::$scope_chain);
	}

	static public function get_scope()
	{
		$scope = self::$scope;

		if (is_array($scope))
		{
			$scope = implode('.', $scope);
		}

		return $scope;
	}
}

/**
 * The WdTranslatorProxi class creates translators, which can be used to easily translate string using
 * a same set of options.
 */
class WdTranslatorProxi extends WdObject
{
	protected $options = array();

	public function __construct(array $options)
	{
		$this->options = $options;
	}

	protected function __set_scope($scope)
	{
		$this->options['scope'] = $scope;
	}

	protected function __set_language($language)
	{
		$this->options['language'] = $language;
	}

	protected function __set_default($default)
	{
		$this->options['default'] = $default;
	}

	public function __invoke($str, array $args=array())
	{
		return t($str, $args, $this->options);
	}
}

function t($str, array $args=array(), array $options=array())
{
	static $translators=array();

	$id = isset($options['language']) ? $options['language'] : WdI18n::$language;

	if (empty($translators[$id]))
	{
		$translators[$id] = WdLocale::get($id)->translator;
	}

	return $translators[$id]->__invoke($str, $args, $options);
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

	return t($str, array(':size' => round($size)));
}

function wd_format_number($number)
{
	$decimal_point = WdI18n::$locale->conventions['numbers']['symbols']['decimal'];
	$thousands_sep = ' ';

	return number_format($number, ($number - floor($number) < .009) ? 0 : 2, $decimal_point, $thousands_sep);
}

function wd_format_date($time, $pattern='default')
{
	if ($pattern == 'default')
	{
		$pattern = WdI18n::$locale->conventions['dates']['dateFormats']['default'];
	}

	if (isset(WdI18n::$locale->conventions['dates']['dateFormats'][$pattern]))
	{
		$pattern = WdI18n::$locale->conventions['dates']['dateFormats'][$pattern];
	}

	return WdI18n::$locale->date_formatter->format($time, $pattern);
}

function wd_format_datetime($time, $date_pattern='default', $time_pattern='default')
{
	if (is_string($time))
	{
		$time = strtotime($time);
	}

	if (isset(WdI18n::$locale->conventions['dates']['dateTimeFormats']['availableFormats'][$date_pattern]))
	{
		$date_pattern = WdI18n::$locale->conventions['dates']['dateTimeFormats']['availableFormats'][$date_pattern];
		$time_pattern = null;
	}

	return WdI18n::$locale->date_formatter->format_datetime($timestamp, $date_pattern, $time_pattern);
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

function wd_date_period($date)
{
	static $relative;

	if (is_numeric($date))
	{
		$date_secs = $date;
		$date = date('Y-m-d', $date);
	}
	else
	{
		$date_secs = strtotime($date);
	}

	$today_days = strtotime(date('Y-m-d')) / (60 * 60 * 24);
	$date_days = strtotime(date('Y-m-d', $date_secs)) / (60 * 60 * 24);

	$diff = round($date_days - $today_days);

	if (empty($relative[WdI18n::$language]))
	{
		$relative[WdI18n::$language] = WdI18n::$locale->conventions['dates']['fields']['day']['relative'];
	}

	if (isset($relative[WdI18n::$language][$diff]))
	{
		return $relative[WdI18n::$language][$diff];
	}
	else if ($diff > -6)
	{
		return ucfirst(strftime('%A', $date_secs));
	}

	return wd_format_date($date);
}