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
	static private $methods;
	static private $class_methods;

	static private function get_methods_definitions()
	{
		if (self::$methods === null)
		{
			self::$methods = WdConfig::get_constructed('objects.methods', array(__CLASS__, 'get_methods_definitions_constructor'), 'hooks');
		}

		return self::$methods;
	}

	static public function get_methods_definitions_constructor($fragments)
	{
		$methods = array();

		foreach ($fragments as $root => $config)
		{
			if (empty($config['objects.methods']))
			{
				continue;
			}

			$hooks = $config['objects.methods'];

			foreach ($hooks as $method => $definition)
			{
				if (empty($definition['instanceof']))
				{
					throw new WdException('Missing <em>instanceof</em> in config (%root): !definition', array('!definition' => $definition, '%root' => $root));
				}

				foreach ((array) $definition['instanceof'] as $class)
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

	/**
	 * Returns the value of an innaccessible property.
	 *
	 * Multiple callbacks are tried in order to retrieve the value of the property :
	 *
	 * 1. `__volatile_get_<property>`: Get and return the value of the property.The callback may
	 * not be defined by the object's class, but one can extend the class using the mixin features
	 * of the FObject class.
	 * 2. `__get_<property>`: Get, set and return the value of the property. Because the property
	 * is set, the callback is only called once. The callback may not be defined by the object's
	 * class, but one can extend the class using the mixin features of the FObject class.
	 * 3.Finaly, a `ar.property` event can be fired to try and retrieve the value of the
	 * property.
	 *
	 * @param string $property
	 * @return mixed The value of the innaccessible property. `null` is returned if the property
	 * could not be retrieved, in that cas, an error is also triggered.
	 */

	public function __get($property)
	{
		$getter = '__volatile_get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$getter();
		}

		$getter = $this->get_method_callback($getter);

		if ($getter)
		{
			return $this->$property = call_user_func($getter, $this, $property);
		}

		#

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

		#
		#
		#

		$rc = $this->__defer_get($property, $success);

		if ($success)
		{
			return $this->$property = $rc;
		}

		throw new WdException
		(
			'Unknow property %property for object of class %class (available properties: :list)', array
			(
				'%property' => $property,
				'%class' => get_class($this),
				':list' => implode(', ', array_keys(get_object_vars($this)))
			)
		);
	}

	protected function __defer_get($property, &$success)
	{
		global $core;

		$event = WdEvent::fire
		(
			'ar.property', array
			(
				'target' => $this,
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

	public function __set($property, $value)
	{
//		echo get_class($this) . '.set(' . $property . ')<br />';

		$setter = '__volatile_set_' . $property;

		if (method_exists($this, $setter))
		{
			return $this->$setter($value);
		}

		/*
		$setter = $this->get_method_callback($setter);

		if ($setter)
		{
			return $this->$property = call_user_func($setter, $this, $property, $value);
		}
		*/

		$setter = '__set_' . $property;

		if (method_exists($this, $setter))
		{
			return $this->$property = $this->$setter($value);
		}

		/*
		$setter = $this->get_method_callback($setter);

		if ($setter)
		{
			return $this->$property = call_user_func($setter, $this, $property, $value);
		}
		*/

		$this->$property = $value;
	}

	/**
	 * Checks wheter the object has the specified property.
	 *
	 * Unlike the property_exists() function, this method uses all the getters available to find
	 * the property.
	 *
	 * @param bool Returns TRUE if the object has the property, FALSE otherwise.
	 */

	public function has_property($property)
	{
		if (property_exists($this, $property))
		{
			return true;
		}

		$getter = '__volatile_get_' . $property;

		if (method_exists($this, $getter))
		{
			return true;
		}

		$getter = $this->get_method_callback($getter);

		if ($getter)
		{
			return true;
		}

		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return true;
		}

		$getter = $this->get_method_callback($getter);

		if ($getter)
		{
			return true;
		}

		#
		#
		#

		$rc = $this->__defer_get($property, $success);

		if ($success)
		{
			$this->$property = $rc;

			return true;
		}

		return false;
	}

	/**
	 * Returns the callbacks associated with the object's class.
	 */

	protected function get_methods()
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

	protected function get_method_callback($method)
	{
		$methods = $this->get_methods();

		if (isset($methods[$method]))
		{
			$callback = $methods[$method];

			if (is_array($callback) && $callback[0][1] == ':' && $callback[0][0] == 'm')
			{
				global $core;

				$callback[0] = $core->module(substr($callback[0], 2));

				// TODO-20100809: replace method definition
			}

			return $callback;
		}
	}
}