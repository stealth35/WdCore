<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdDebug
{
	static public $config;

	static public function __static_construct()
	{
		$fragments = WdConfig::get('debug');

		self::$config = call_user_func_array('wd_array_merge_recursive', $fragments);

		self::$config = array_merge(self::$config, self::$config['modes'][self::$config['mode']]);
	}

	static public function shutdown_handler()
	{
		if (!self::$messages)
		{
			return;
		}

		if (!headers_sent())
		{
			global $core;

			if (isset($core))
			{
				$core->session;
			}
		}

		$_SESSION['wddebug']['messages'] = self::$messages;
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

		$rc = '<code class="wd-core-debug">' . implode('<br />', $lines) . '</code><br />';

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

		// TODO-20100718: runtime function have strange filenames.

		if (!file_exists($file))
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

		$message .= '<code><br /><br /><strong>Request URI:</strong> ' . wd_entities($_SERVER['REQUEST_URI']);

		if (isset($_SERVER['HTTP_REFERER']))
		{
			$message .= '<br /><br /><strong>Referer:</strong> ' . wd_entities($_SERVER['HTTP_REFERER']);
		}

		if (isset($_SERVER['HTTP_USER_AGENT']))
		{
			$message .= '<br /><br /><strong>User Agent:</strong> ' . wd_entities($_SERVER['HTTP_USER_AGENT']);
		}

		$message .= '</code>';

		#
		# during the same session, same messages are only reported once
		#

		$hash = md5($message);

		if (isset($_SESSION['wddebug']['reported'][$hash]))
		{
			return;
		}

		$_SESSION['wddebug']['reported'][$hash] = true;

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

	static public $messages=array();

	public static function putMessage($type, $message, array $params=array(), $messageId=null)
	{
		if (empty(self::$messages[$type]))
		{
			self::$messages[$type] = array();
		}

		#
		# limit the number of messages
		#

		$messages = &self::$messages[$type];

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
		if (empty(self::$messages[$type]))
		{
			return array();
		}

		$rc = array();

		foreach (self::$messages[$type] as $message)
		{
			$rc[] = t($message[0], $message[1]);
		}

		return $rc;
	}

	public static function fetchMessages($type)
	{
		$rc = self::getMessages($type);

		self::$messages[$type] = array();

		return $rc;
	}
}

register_shutdown_function(array('WdDebug', 'shutdown_handler'));
