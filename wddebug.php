<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

require_once 'wdlocale.php';
require_once 'wdutils.php';
require_once 'wdexception.php';

class WdDebug
{
	static public $config = array
	(
		'maxMessages' => 100,
		'reportAddress' => null,
		'verbose' => true,
		'lineNumber' => true,
		'stackTrace' => true,
		'codeSample' => true,

		'mode' => 'test',
		'modes' => array
		(
			'test' => array
			(
				'verbose' => true
			),

			'production' => array
			(
				'verbose' => false,
				'stackTrace' => false,
				'codeSample' => false
			)
		)
	);

	static public function autoconfig($config)
	{
		$configs = func_get_args();

		array_unshift($configs, self::$config);

		self::$config = call_user_func_array('wd_array_merge_recursive', $configs);

		self::$config = array_merge(self::$config, self::$config['modes'][self::$config['mode']]);
	}

	/*
	**

	DEBUG & TRACE

	**
	*/

	public static function errorHandler($no, $str, $file, $line, $context)
	{
		if (!headers_sent())
		{
			header('HTTP/1.0 500 Error with the following message: ' . strip_tags($str));
		}

		#
		# prolog
		#

		$lines[] = '<strong>Error with the following message:</strong><br />';
		$lines[] = $str . '<br />';
		$lines[] = 'in <em>' . $file . '</em> at line <em>' . $line . '</em><br />';

		#
		# trace
		#

		$stack = debug_backtrace();

		#
		# remove errorHandler trace & trigger trace
		#

		array_shift($stack);
		array_shift($stack);

		if (self::$config['stackTrace'])
		{
			$lines = array_merge($lines, self::formatTrace($stack));
		}

		if (self::$config['codeSample'])
		{
			$lines = array_merge($lines, self::codeSample($file, $line));
		}

		#
		#
		#

		$rc = '<code>' . implode('<br />', $lines) . '</code><br />';

		self::report($rc);

		if (self::$config['verbose'])
		{
			echo '<br />' . $rc;
		}
	}

	public static function exceptionHandler($exception)
	{
		if (!headers_sent())
		{
			header('HTTP/1.0 500 Exception with the following message: ' . strip_tags($exception->getMessage()));
		}

		echo $exception;

		exit;
	}

	public static function trigger()
	{
		$stack = debug_backtrace();
		$caller = array_shift($stack);

		#
		# we skip user_func calls, and get to the real call
		#

		while (empty($caller['file']))
		{
			$caller = array_shift($stack);
		}

		#
		# prolog
		#

		$args = func_get_args();
		$message = call_user_func_array('t', $args);

		$file = $caller['file'];
		$line = $caller['line'];

		$lines = array
		(
			'<strong>Backtrace with the following message:</strong><br />',
			$message . '<br />',
			'in <em>' . $file . '</em> at line <em>' . $line . '</em><br />'
		);

		#
		# stack
		#

		$lines = array_merge($lines, self::formatTrace($stack));

		#
		#
		#

		$rc = '<code>' . join("<br />\n", $lines) . '</code><br />';

		self::report($rc);

		if (self::$config['verbose'])
		{
			echo '<br /> ' . $rc;
		}
	}

	public static function lineNumber($file, $line, &$saveback=null)
	{
		$lines = array();

		if (!self::$config['lineNumber'])
		{
			return $lines();
		}

		$file = substr($file, strlen($_SERVER['DOCUMENT_ROOT']));

		$lines[] = '<br />→ in <em>' . $file . '</em> at line <em>' . $line . '</em>';

		if (is_array($saveback))
		{
			$saveback = array_merge($saveback, $lines);
		}

		return $lines;
	}

	const MAX_STRING_LEN = 16;

	public static function formatTrace($stack, &$saveback=null)
	{
		$lines = array();

		if (!$stack || !self::$config['stackTrace'])
		{
			return $lines;
		}

		$root = str_replace('\\', '/', realpath('.'));
		$count = count($stack);

		$lines[] = '<br /><strong>Stack trace:</strong><br />';

		foreach ($stack as $i => $node)
		{
			$trace_file = null;
			$trace_line = 0;
			$trace_class = null;
			$trace_type = null;
			$trace_args = null;

			extract($node, EXTR_PREFIX_ALL, 'trace');

			if ($trace_file)
			{
				$trace_file = str_replace('\\', '/', $trace_file);
				$trace_file = str_replace($root, '', $trace_file);
			}

			$params = array();

			if ($trace_args)
			{
				foreach ($trace_args as $arg)
				{
					switch (gettype($arg))
					{
						case 'array': $arg = 'Array'; break;
						case 'object': $arg = 'Object of ' . get_class($arg); break;
						case 'resource': $arg = 'Resource of type ' . get_resource_type($arg); break;

						default:
						{
							if (strlen($arg) > self::MAX_STRING_LEN)
							{
								$arg = substr($arg, 0, self::MAX_STRING_LEN) . '...';
							}
						}
						break;
					}

					$params[] = $arg;
				}
			}

			$lines[] = sprintf
			(
				'%02d ➦ %s(%d): %s%s%s(%s)',

				$count - $i, $trace_file, $trace_line, $trace_class, $trace_type,
				$trace_function, wd_entities(join(', ', $params))
			);
		}

		if (is_array($saveback))
		{
			$saveback = array_merge($saveback, self::formatTrace($stack));
		}

		return $lines;
	}

	public static function codeSample($file, $line, &$saveback=null)
	{
		if (!self::$config['codeSample'])
		{
			return array();
		}

		$lines =  array('<br /><strong>Code sample:</strong><br />');

		$fh = fopen($file, 'r');

		$i = 0;
		$start = $line - 5;
		$stop = $line + 5;

		while ($str = fgets($fh))
		{
			$i++;

			if ($i > $start)
			{
				$str = wd_entities(rtrim($str));

				if ($i == $line)
				{
					$str = '<ins>' . $str . '</ins>';
				}

				$str = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $str);

				$lines[] = $str;
			}

			if ($i > $stop)
			{
				break;
			}
		}

		fclose($fh);

		if (is_array($saveback))
		{
			$saveback = array_merge($saveback, $lines);
		}

		return $lines;
	}

	public static function report($message)
	{
		$reportAddress = self::$config['reportAddress'];

		if (!$reportAddress)
		{
			return;
		}

		#
		# add location information
		#

		$message .= '<br /><code><strong>Request URI:</strong> ' . $_SERVER['REQUEST_URI'];

		if (isset($_SERVER['HTTP_REFERER']))
		{
			$message .= '<br /><strong>Referer:</strong> ' . $_SERVER['HTTP_REFERER'];
		}

		$message .= '</code>';

		#
		# during the same session, same messages are only reported once
		#

		$hash = md5($message);

		if (isset($_SESSION[__CLASS__ . '.reported']))
		{
			return;
		}

		$_SESSION[__CLASS__ . '.reported'][$hash] = true;

		#
		#
		#

		$host = $_SERVER['HTTP_HOST'];
		$host = str_replace('www.', '', $host);

		$parts = array
		(
			'From' => 'wddebug@' . $host,
			'Content-Type' => 'text/html; charset=' . WDCORE_CHARSET
		);

		$headers = '';

		foreach ($parts as $key => $value)
		{
			$headers .= $key .= ': ' . $value . "\r\n";
		}

		mail($reportAddress, 'WdDebug: Report from ' . $host, $message, $headers);
	}

	/*
	**

	LOG

	**
	*/

	public static function putMessage($type, $message, array $params=array(), $messageId=null)
	{
		if (empty($_SESSION[__CLASS__][$type]))
		{
			$_SESSION[__CLASS__][$type] = array();
		}

		#
		# limit the number of messages
		#

		$messages = &$_SESSION[__CLASS__][$type];

		if ($messages)
		{
			$max = self::$config['maxMessages'];
			$count = count($messages);

			if ($count > $max)
			{
				$messages = array_splice($messages, $count - $max);

				array_unshift($messages, array('*** SLICED', array()));
			}
		}

		$messageId ? $messages[$messageId] = array($message, $params) : $messages[] = array($message, $params);
	}

	public static function getMessages($type)
	{
		if (empty($_SESSION[__CLASS__][$type]))
		{
			return array();
		}

		$rc = array();

		foreach ($_SESSION[__CLASS__][$type] as $message)
		{
			$rc[] = t($message[0], $message[1]);
		}

		return $rc;
	}

	public static function fetchMessages($type)
	{
		$rc = self::getMessages($type);

		$_SESSION[__CLASS__][$type] = array();

		return $rc;
	}
}

/*
**

SUPPORT FUNCTIONS

**
*/

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
		$reference = microtime(true);
	}

	$now = microtime(true);

	$add = '<var>[';

	if ($last)
	{
		$add .= '+' . number_format($now - $last, 3, '\'', '') . '", ';
	}

	$add .= '∑' . number_format($now - $reference, 3, '\'', '') . '"';

	$add .= ']</var>';

	$last = $now;

	$str = $add . ' ' . $str;

	wd_log($str, $params);
}