<?php

/*

!! THIS CODE IS EXPERIMENTAL !!

*/

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

// http://en.wikipedia.org/wiki/Calendar_date

class WdDateTime
{
	private $time;

	public function __construct($date='now')
	{
		$this->time = is_numeric($date) ? $date : strtotime($date);
	}

	public function modify($relative)
	{
		$this->time = strtotime($relative, $this->time);
	}

	public function format($fmt, $modify=NULL, $upperize=false)
	{
		$time = $modify ? strtotime($modify, $this->time) : $this->time;
		$date = strftime($fmt, $time);

		if ($upperize)
		{
			$date = preg_replace('#^[[:lower:]]|\s+[[:lower:]]#e', 'strtoupper("\0")', $date);
		}

		//echo '"' . $date . '" is ' . mb_detect_encoding($date) . '<br />';

		/*
		if (mb_detect_encoding($date) != 'UTF-8')
		{
			$date = utf8_encode($date);
		}
		*/

		return $date;
	}
}

function wd_date_period($date)
{
	require_once WDCORE_ROOT . 'wddate.php';

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

	$diff = $today_days - $date_days;

	if ($diff == 0)
	{
		return "Aujourd'hui";
	}
	else if ($diff == 1)
	{
		return 'Hier';
	}
	else if ($diff < 6)
	{
		return ucfirst(strftime('%A', $date_secs));
	}

	return wd_format_time($date);
}