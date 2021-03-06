<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

@define('WDEXCEPTION_WITH_LOG', true);

class WdException extends Exception
{
	protected $code;
	protected $title = 'Exception';

	public function __construct($message, array $params=array(), $code=500)
	{
		$this->code = $code;

		if (is_array($code))
		{
			$this->code = key($code);
			$this->title = array_shift($code);
		}
		else if ($code == 404)
		{
			$this->title = 'Not Found';
		}
		else if ($code == 403)
		{
			$this->title = 'Forbidden';
		}
		else if ($code == 401)
		{
			$this->title = 'Unauthorized';
		}

		#
		# the error message is localized and formated
		#

		$message = t($message, $params);

		parent::__construct($message);
	}

	public function __toString()
	{
		if ($this->code && !headers_sent())
		{
			header('HTTP/1.0 ' . $this->code . ' ' . $this->title);
		}

		#
		#
		#

		$file = $this->getFile();
		$line = $this->getLine();

		$lines = array();

		$lines[] = '<strong>' . $this->title . ', with the following message:</strong><br />';
		$lines[] = $this->getMessage();

		WdDebug::lineNumber($file, $line, $lines);
		WdDebug::formatTrace($this->getTrace(), $lines);

		#
		# if WDEXCEPTION_WITH_LOG is set to true, we join the messages from the log
		# to the trace
		#

		if (WDEXCEPTION_WITH_LOG)
		{
			$log = array_merge(WdDebug::fetchMessages('debug'), WdDebug::fetchMessages('error'), WdDebug::fetchMessages('done'));

			if ($log)
			{
				$lines[] = '<br /><strong>Log:</strong><br />';

				foreach ($log as $message)
				{
					$lines[] = $message . '<br />';
				}
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

	public function getHTTPCode()
	{
		return $this->code;
	}

	public function getTitle()
	{
		return $this->code . ' ' . $this->title;
	}

	/**
	 * Alters the HTTP header according to the exception code and title.
	 */
	public function alter_header()
	{
		header("HTTP/1.0 $this->code $this->title");
	}
}

class WdHTTPException extends WdException
{
	public function __toString()
	{
		if ($this->code && !headers_sent())
		{
			header('HTTP/1.0 ' . $this->code . ' ' . $this->title);
		}

		$rc  = '<code class="exception">';
		$rc .= '<strong>' . $this->title . ', with the following message:</strong><br /><br />';
		$rc .= $this->getMessage() . '<br />';
		$rc .= '</code>';

		return $rc;
	}
}