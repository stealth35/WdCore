<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class WdEvent
{
	const DELIMITER = '~';

	static protected $listeners = array();

	static protected function listeners()
	{
		global $core;

		if (empty(self::$listeners))
		{
			self::$listeners = $core->configs->fuse('events', array(__CLASS__, 'listeners_construct'), 'hooks');
		}

		return self::$listeners;
	}

	static public function listeners_construct($fragments)
	{
		$listeners = array();

		foreach ($fragments as $fragment)
		{
			if (empty($fragment['events']))
			{
				continue;
			}

			foreach ($fragment['events'] as $pattern => $definition)
			{
				$listeners[self::translateRegEx($pattern)][] = $definition;
			}
		}

		return $listeners;
	}

	static public function translateRegEx($pattern)
	{
		if (strpos($pattern, '*') !== false || strpos($pattern, '?') !== false)
		{
			$pattern = self::DELIMITER . '^' . str_replace(array('\*', '\?'), array('.*', '.'), preg_quote($pattern, self::DELIMITER)) . '$' . self::DELIMITER;
		}

		return $pattern;
	}

	static public function add($pattern, $callback)
	{
		self::$listeners[self::translateRegEx($pattern)][] = $callback;
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

		foreach ($listeners as $pattern => $definitions)
		{
			if (!($pattern{0} == self::DELIMITER ? preg_match($pattern, $type) : $pattern == $type))
			{
				continue;
			}

			#
			# It's time to call the event callback. If there is no event object created yet, we
			# create one now, otherwise we update its type.
			#

			$event ? $event->type = $type : $event = new WdEvent($type, $params);

			foreach ($definitions as $definition)
			{
				list($callback) = $definition;

				if (isset($params['target']) && isset($definition['instanceof']))
				{
					$target = $params['target'];
					$instanceof = $definition['instanceof'];
					$is_instance_of = false;

					if (is_array($instanceof))
					{
						foreach ($instanceof as $name)
						{
							if (!$target instanceof $name)
							{
								continue;
							}

							$is_instance_of = true;

							break;
						}
					}
					else
					{
						$is_instance_of = $target instanceof $instanceof;
					}

					if (!$is_instance_of)
					{
						continue;
					}
				}

				#
				# autoload modules if the callback is prefixed by 'm:'
				#

				if (is_array($callback) && is_string($callback[0]) && $callback[0]{1} == ':' && $callback[0]{0} == 'm')
				{
					global $core;

					$module_id = substr($callback[0], 2);

					if (empty($core->modules[$module_id]))
					{
						#
						# If the module is unavailable, we silently continue
						#

						continue;
					}

					$callback[0] = $core->modules[$module_id];
				}

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
	protected $stop = false;

	public function __construct($type, array $params=array())
	{
		$this->type = $type;

		foreach ($params as $key => &$value)
		{
			$this->$key = &$value;
		}
	}

	public function stop()
	{
		$this->stop = true;
	}
}