<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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

	static public function get_methods_definitions_constructor(array $fragments)
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
					throw new WdException('Missing <em>instanceof</em> in config (%root): !definition.', array('!definition' => $definition, '%root' => $root));
				}

				foreach ((array) $definition['instanceof'] as $class)
				{
					$methods[$class][$method] = $definition[0];
				}
			}
		}

		return $methods;
	}

	/**
	 * Adds a method to a class.
	 *
	 * @param string $method The name of the method.
	 * @param array $definition Definition of the method:
	 *
	 * 0 => callback - The callback for the method.
	 * 'instanceof' => string|array The instance or instances to which the method is added.
	 */
	static public function add_method($method, array $definition)
	{
		self::add_methods(array($method => $definition));
	}

	/**
	 * Adds methods to classes.
	 *
	 * @param array $definitions
	 */
	static public function add_methods(array $definitions)
	{
		if (self::$methods === null)
		{
			self::get_methods_definitions();
		}

		self::$class_methods = null;

		foreach ($definitions as $method => $definition)
		{
			{
				if (empty($definition['instanceof']))
				{
					throw new WdException('Missing <em>instanceof</em> in definition: !definition.', array('!definition' => $definition));
				}

				foreach ((array) $definition['instanceof'] as $class)
				{
					self::$methods[$class][$method] = $definition[0];
				}
			}
		}
	}

	/**
	 * Calls the method callback associated with the nonexistant method called or throws an
	 * exception if none is defined.
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return mixed The result of the callback.
	 */
	public function __call($method, $arguments)
	{
		$callback = $this->get_method_callback($method);

		if (!$callback)
		{
			throw new WdException
			(
				'Unknown method %method for object of class %class.', array
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
	 * could not be retrieved, in which case an exception is raised.
	 */
	public function __get($property)
	{
		$getter = '__volatile_get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$getter();
		}

		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		#
		# The object does not define any getter for the property, let's see if a getter is defined
		# in the methods.
		#

		$getter = $this->get_method_callback('__volatile_get_' . $property);

		if ($getter)
		{
			return $this->$property = call_user_func($getter, $this, $property);
		}

		$getter = $this->get_method_callback('__get_' . $property);

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

		$properties = array_keys(get_object_vars($this));

		if ($properties)
		{
			throw new WdException
			(
				'Unknow property %property for object of class %class (available properties: :list).', array
				(
					'%property' => $property,
					'%class' => get_class($this),
					':list' => implode(', ', $properties)
				)
			);
		}
		else
		{
			throw new WdException
			(
				'Unknow property %property for object of class %class (the object has no accessible property).', array
				(
					'%property' => $property,
					'%class' => get_class($this)
				)
			);
		}
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
		# The operation is considered a success if the `value` property exists in the event
		# object. Thus, even a `null` value is considered a success.
		#

		if (!$event || !property_exists($event, 'value'))
		{
			return;
		}

		$success = true;

		return $event->value;
	}

	/**
	 * Sets the value of inaccessible properties.
	 *
	 * If the `__volatile_set_<property>` or `__set_<property>` setter methods exists, they are
	 * used to set the value to the property, otherwise the value is set _as is_.
	 *
	 * For performance reason, external setters are not used.
	 *
	 * @param string $property
	 * @param mixed $value
	 */
	public function __set($property, $value)
	{
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
	 * Checks if the object has the specified property.
	 *
	 * Unlike the property_exists() function, this method uses all the getters available to find
	 * the property.
	 *
	 * @param string $property The property to check.
	 * @return bool true if the object has the property, false otherwise.
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

		$rc = $this->__defer_get($property, $success);

		if ($success)
		{
			$this->$property = $rc;

			return true;
		}

		return false;
	}

	/**
	 * Checks whether this object supports the specified method.
	 *
	 * @param string $method Name of the method.
	 * @return bool true if the object supports the method, false otherwise.
	 */
	public function has_method($method)
	{
		return method_exists($this, $method) || $this->get_method_callback($method);
	}

	/**
	 * Returns the callbacks associated with the class of the object.
	 *
	 * @return array The callbacks associated with the class of the object.
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

		$classes = array($class => $class) + class_parents($class);

		foreach ($classes as $c)
		{
			if (empty($methods[$c]))
			{
				continue;
			}

			$methods_by_class += $methods[$c];
		}

		self::$class_methods[$class] = $methods_by_class;

		return $methods_by_class;
	}

	/**
	 * Returns the callback for a given unimplemented method.
	 *
	 * Callbacks defined as 'm:<module_id>' are supported and resolved when the method is called.
	 *
	 * @param $method
	 * @return mixed Callback for the given unimplemented method.
	 */
	protected function get_method_callback($method)
	{
		global $core;

		$methods = $this->get_methods();

		if (isset($methods[$method]))
		{
			$callback = $methods[$method];

			if (is_array($callback) && $callback[0][1] == ':' && $callback[0][0] == 'm')
			{
				$callback[0] = $core->modules[substr($callback[0], 2)];

				// TODO-20100809: replace method definition
			}

			return $callback;
		}
	}
}