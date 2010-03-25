<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

require_once 'wdutils.php';
require_once 'wdlocale.php';
require_once 'wddebug.php';

@define('WDEXCEPTION_WITH_LOG', true);

class WdException extends Exception
{
	public function __construct($message, array $params=array(), $code=null)
	{
		#
		# the error message is localized and formated
		#

		$message = t($message, $params);

		if ($code)
		{
			header('HTTP/1.0 ' . $code . ' ' . strip_tags($message));
		}

		parent::__construct($message);
	}

	public function __toString()
	{
		$lines = array();

		$lines[] = '<strong>Exception with the following message:</strong><br />';
		$lines[] = $this->getMessage() . '<br />';
		$lines[] = 'in <em>' . $this->getFile() . '</em> at line <em>' . $this->getLine() . '</em><br />';

		$stack = $this->getTrace();

		$lines = array_merge($lines, WdDebug::formatTrace($stack));

		#
		# if WDEXCEPTION_WITH_LOG is set to true, we join the messages from the log
		# to the trace
		#

		if (WDEXCEPTION_WITH_LOG)
		{
			if (!empty($_SESSION['log']))
			{
				$lines[] = '<br /><strong>Log:</strong><br />';

				foreach ($_SESSION['log'] as $message)
				{
					$lines[] = $message . '<br />';
				}

				$_SESSION['log'] = NULL;
			}
		}

		#
		# now we join all of these lines, report the message and return it
		# so it can be displayed by the exception handler
		#

		$rc = '<code class="exception">' . implode('<br />' . PHP_EOL, $lines) . '</code>';

		WdDebug::report($rc);

		return $rc;
	}
}