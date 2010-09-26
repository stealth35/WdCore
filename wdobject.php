<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdObject
{
	static protected $methods;
	static protected $class_methods;

	static private function get_methods_definitions()
	{
		if (self::$methods === null)
		{
			self::$methods = WdConfig::get_constructed('objects.methods', array(__CLASS__, 'get_methods_definitions_constructor'), 'hook');
		}

		return self::$methods;
	}

	static public function get_methods_definitions_constructor($configs)
	{
		$methods = array();

		foreach ($configs as $root => $config)
		{
			if (empty($config['objects.methods']))
			{
				continue;
			}

			$hooks = $config['objects.methods'];

			foreach ($hooks as $method => $definition)
			{
				if (empty($definition['instancesof']))
				{
					throw new WdException('Missing <em>instancesof</em> in config (%root): !definition', array('!definition' => $definition, '%root' => $root));
				}

				foreach ((array) $definition['instancesof'] as $class)
				{
					$methods[$class][$method] = $definition[0];
				}
			}
		}

		return $methods;
	}

	public function __construct()
	{

	}

	public function __call($method, $arguments)
	{
		$callback = $this->get_method_callback($method);

		if (!$callback)
		{
			throw new WdException
			(
				'Unknow method %method for object of class %class', array
				(
					'%method' => $method,
					'%class' => get_class($this)
				)
			);
		}

		array_unshift($arguments, $this);

		return call_user_func_array($callback, $arguments);
	}

	public function has_property($property)
	{
		if (property_exists($this, $property))
		{
			return true;
		}

		#
		#
		#

		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return true;
		}

		#
		# The object does not define any getter for the property, let's see if a getter is defined
		# in the methods.
		#

		$getter = $this->get_method_callback($getter);

		if ($getter)
		{
			return true;
		}

		#
		#
		#

		$rc = $this->__get_by_event($property, $success);

		if ($success)
		{
			$this->$property = $rc;

			return true;
		}

		return false;
	}

	public function __get($property)
	{
		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		#
		# The object does not define any getter for the property, let's see if a getter is defined
		# in the methods.
		#

		$getter = $this->get_method_callback($getter);

		if ($getter)
		{
			return $this->$property = call_user_func($getter, $this, $property);
		}













		$getter = '__get_them_all';

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter($property);
		}

		#
		#
		#

		$rc = $this->__get_by_event($property, $success);

		if ($success)
		{
			return $this->$property = $rc;
		}

		WdDebug::trigger
		(
			'Unknow property %property for object of class %class (available properties: :list)', array
			(
				'%property' => $property,
				'%class' => get_class($this),
				':list' => implode(', ', array_keys(get_object_vars($this)))
			)
		);
	}

	protected function __get_by_event($property, &$success)
	{
		global $core;

		$event = WdEvent::fire
		(
			'ar.property', array
			(
				'ar' => $this,
				'property' => $property
			)
		);

		#
		# The operation is considered a sucess if the `value` property exists in the event
		# object. Thus, even a `null` value is considered a success.
		#

		if (!$event || !property_exists($event, 'value'))
		{
			return;
		}

		$success = true;

		return $event->value;
	}



	public function get_methods()
	{
		$class = get_class($this);

		if (isset(self::$class_methods[$class]))
		{
			return self::$class_methods[$class];
		}

		$methods = self::get_methods_definitions();
		$methods_by_class = array();

		$c = $class;

		while ($c)
		{
			if (isset($methods[$c]))
			{
				$methods_by_class += $methods[$c];
			}

			$c = get_parent_class($c);
		}

		self::$class_methods[$class] = $methods_by_class;

		return $methods_by_class;
	}

	/**
	 * Returns the callback for a given method.
	 *
	 * Callbacks defined as 'm:<module_id>' are supported and get resolved when the method is
	 * called.
	 *
	 * @param $method
	 */

	public function get_method_callback($method)
	{
		$methods = $this->get_methods();

		if (isset($methods[$method]))
		{
			$callback = $methods[$method];

			if (is_array($callback) && $callback[0][1] == ':' && $callback[0][0] == 'm')
			{
				global $core;

				$callback[0] = $core->getModule(substr($callback[0], 2));

				// TODO-20100809: replace method definition
			}

			return $callback;
		}
	}
}