<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdHook
{
	static protected $namespaces = array();
	static protected $hooks = array();

	static public function autoconfig()
	{
		$configs = func_get_args();

		foreach ($configs as $config)
		{
			foreach ($config as $ns => $hooks)
			{
				self::$namespaces[$ns][] = $hooks;
			}
		}
	}

	static public function add($ns, $name, $tags)
	{
		self::$hooks[$ns][$name] = new WdHook($tags);
	}

	static public function find($ns, $name)
	{
		if (empty(self::$hooks[$ns][$name]))
		{
			if (empty(self::$namespaces[$ns]))
			{
				throw new WdException('Undefined namespace %ns', array('%ns' => $ns));

				return;
			}

			$descriptor = null;
			$descriptors = self::$namespaces[$ns];

			foreach ($descriptors as $descriptor)
			{
				if (empty($descriptor[0][$name]))
				{
					continue;
				}

				break;
			}

			if (empty($descriptor[0][$name]))
			{
				throw new WdException('Undefined hook %name in namespace %ns', array('%name' => $name, '%ns' => $ns));

				return;
			}

			#
			# create hook
			#

			if (isset($descriptor['namespace']))
			{
				WdDebug::trigger('The "namepsace" feature is deprecated, please update your descriptor: \1', array($descriptor));
			}


			$classname = isset($descriptor['namespace']) ? $descriptor['namespace'] : null;

			if (isset($descriptor['require']))
			{
				WdDebug::trigger('Please use WdCore autoload feature instead of the WdDebug require feature: \1', array($descriptor));

				require_once $descriptor['require'];
			}

			#
			#
			#

			$definition = $descriptor[0][$name];

			if (!is_array($definition))
			{
				throw new WdException
				(
					'Hook definition must be an array, %definition given', array
					(
						'%definition' => $definition
					)
				);
			}

			list($callback) = $definition;

			if ($classname && is_string($callback))
			{
				$definition[0] = array($classname, $callback);
			}

			self::$hooks[$ns][$name] = new WdHook($definition);
		}

		return self::$hooks[$ns][$name];
	}

	public $callback;
	public $params = array();
	public $tags = array();

	public function __construct($callback, array $params=array(), array $tags=array())
	{
		$this->callback = $callback;
		$this->params = $params;
		$this->tags = $tags;
	}

	public $args = array();

	public function call(array $args=array())
	{
		$user_args = func_get_args();

		array_shift($user_args);

		$clone = clone $this;

		$clone->args = $args;

		array_unshift($user_args, $clone);

		$rc = call_user_func_array($clone->callback, $user_args);

		unset($clone);

		return $rc;
	}
}