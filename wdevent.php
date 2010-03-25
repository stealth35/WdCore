<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

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