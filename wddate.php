<?php

/*

!! THIS CODE IS EXPERIMENTAL !!

*/

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