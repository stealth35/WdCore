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

class WdMailer
{
	const T_DESTINATION = 'destination';
	const T_FROM = 'from';
	const T_HEADER = 'header';
	const T_MESSAGE = 'message';
	const T_SUBJECT = 'subject';
	const T_TYPE = 'type';
	const T_BCC = 'bcc';

	public $charset = 'utf-8';
	public $destination = array();
	public $message;
	public $subject;
	public $type = 'plain';
	public $bcc = array();

	private $header = array();

	public function __construct(array $tags)
	{
		foreach ($tags as $tag => $value)
		{
			switch ($tag)
			{
				case self::T_DESTINATION:
				{
					if (is_array($value))
					{
						foreach ($value as $name => $email)
						{
							$this->addDestination($email, is_numeric($name) ? null : $name);
						}

						break;
					}

					$this->addDestination($value, null);
				}
				break;

				case self::T_FROM:
				{
					$this->modifyHeader(array('From' => $value, 'Reply-To' => $value, 'Return-Path' => $value));
				}
				break;

				case self::T_HEADER:
				{
					$this->modifyHeader($value);
				}
				break;

				case self::T_MESSAGE:
				{
					$this->message = $value; break;
				}
				break;

				case self::T_SUBJECT:
				{
					$this->subject = $value;
				}
				break;

				case self::T_TYPE:
				{
					$this->type = $value;
				}
				break;

				case self::T_BCC:
				{
					if (!$value)
					{
						break;
					}

					$this->modifyHeader(array('Bcc' => implode(',', (array) $value)));
				}
				break;
			}
		}
	}

	/**
	 * Adds a destination address ("To")
	 *
	 * @param $address
	 * @param $name
	 */

	public function addDestination($address, $name=null)
	{
		$this->destination[$address] = $name;
	}

	public function modifyHeader(array $modifiers)
	{
		$this->header = $modifiers + $this->header;
	}

	public function send()
	{
		$to = $this->concatAddresses($this->destination);
		$subject = $this->subject;
		$message = $this->message;

		$parts = $this->header + array
		(
			'Content-Type' => 'text/' . $this->type . '; charset=' . $this->charset
		);

		$header = null;

		foreach ($parts as $identifier => $value)
		{
			$header .= $identifier . ': ' . $value . "\r\n";
		}

		if (0)
		{
			wd_log
			(
				'<pre>mail("!to", "!subject", "!message", "!header")</pre>', array
				(
					'!to' => $to,
					'!subject' => $subject,
					'!message' => $message,
					'!header' => str_replace("\r\n", '\r\n', $header)
				)
			);
		}

		return mail($to, $subject, $message, $header);
	}

	private function concatAddresses($addresses)
	{
		$rc = array();

		foreach ($this->destination as $address => $name)
		{
			$rc[] = $name ? $name . ' <' . $address . '>' : $address;
		}

		return implode(',', $rc);
	}
}

?>