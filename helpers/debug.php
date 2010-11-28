<?php

function wd_log($str, array $params=array(), $messageId=null, $type='debug')
{
	WdDebug::putMessage($type, $str, $params, $messageId);
}

function wd_log_done($str, array $params=array(), $messageId=null)
{
	wd_log($str, $params, $messageId, 'done');
}

function wd_log_error($str, array $params=array(), $messageId=null)
{
	wd_log($str, $params, $messageId, 'error');
}

function wd_log_time($str, array $params=array())
{
	static $reference;
	static $last;

	if (!$reference)
	{
		global $wddebug_time_reference;

		$reference = isset($wddebug_time_reference) ? $wddebug_time_reference : microtime(true);

		// TODO-20100525: the first call is used as an initializer, we have to find a better way
		// to initialize the reference time.

//		return;
	}

	$now = microtime(true);

	$add = '<var>[';

	$add .= '∑' . number_format($now - $reference, 3, '\'', '') . '"';

	if ($last)
	{
		$add .= ', +' . number_format($now - $last, 3, '\'', '') . '"';
	}

	$add .= ']</var>';

	$last = $now;

	$str = $add . ' ' . $str;

	wd_log($str, $params);
}