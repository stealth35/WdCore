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

class WdEvent
{
	static protected $configs = array();
	static protected $listeners = array();

	static public function autoconfig()
	{
		$configs = func_get_args();

		self::$configs = array_merge(self::$configs, $configs);
	}

	/**
	 * Parse remaining listeners form the config and return the whole set.
	 *
	 * @return array Listeners
	 */

	static protected function listeners()
	{
		foreach (self::$configs as $config => $events)
		{
			foreach ($events as $pattern => $callback)
			{
				self::add($pattern, $callback);
			}
		}

		self::$configs = array();

		return self::$listeners;
	}

	static public function add($pattern, $callback)
	{
		if (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false)
		{
			$pattern = '~^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern)) . '$~';
		}

		self::$listeners[$pattern][] = $callback;
	}

	static public function remove($event, $callback)
	{
		if (empty(self::$listeners[$event]))
		{
			return;
		}

		foreach (self::$listeners[$event] as $key => $value)
		{
			if ($value != $callback)
			{
				continue;
			}

			unset(self::$listeners[$event][$key]);

			break;
		}
	}

	static public function fire($type, array $params=array())
	{
		$event = null;
		$listeners = self::listeners();

		foreach ($listeners as $pattern => $callbacks)
		{
			if (!($pattern{0} == '~' ? preg_match($pattern, $type) : $pattern == $type))
			{
				continue;
			}

			if (!$event)
			{
				$event = new WdEvent($type, $params);
			}

			foreach ($callbacks as $callback)
			{
				#
				# autoload modules if the callback uses 'm:'
				#

				if (is_array($callback) && is_string($callback[0]) && substr($callback[0], 0, 8) == '!module:')
				{
					throw new WdException('"!module:" must be replaced with m:');
				}

				if (is_array($callback) && is_string($callback[0]) && $callback[0]{1} == ':' && $callback[0]{0} == 'm')
				{
					global $core;

					$module_id = substr($callback[0], 2);

					if (!$core->hasModule($module_id))
					{
						continue;
					}

					$callback[0] = $core->getModule($module_id);
				}

				//wd_log('call callback ! \1', array($callback));

				call_user_func($callback, $event);

				if ($event->stop)
				{
					return $event;
				}
			}
		}

		return $event;
	}

	protected $type;

	public function __construct($type, array $params=array())
	{
		$this->type = $type;

		foreach ($params as $key => &$value)
		{
			$this->$key = &$value;
		}
	}

	protected $stop = false;

	public function stop()
	{
		$this->stop = true;
	}
}