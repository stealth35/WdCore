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

function wd_ftime($date, $format='%A, %d %B %Y', $modify=NULL, $upperize=false)
{
	$date = new WdDateTime($date);

	return $date->format($format, $modify, $upperize);
}