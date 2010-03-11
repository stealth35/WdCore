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