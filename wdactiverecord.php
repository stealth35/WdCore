<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdActiveRecord
{
	public function __construct()
	{

	}

	public function __get($property)
	{
		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		#
		#
		#

		$rc = $this->__get_by_event($property, $success);

		if ($success)
		{
			return $rc;
		}

		WdDebug::trigger
		(
			'Unknow property %property for object of class %class', array
			(
				'%property' => $property, '%class' => get_class($this)
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

	static protected $models;

	protected function model($name=null)
	{
		if (!$name)
		{
			throw new WdException("Missing model's name");
		}

		if (empty(self::$models[$name]))
		{
			global $core;

			//echo t(__CLASS__ . '::' . __FUNCTION__ . ":&gt; load model $modelName<br />");

			list($module_id, $model_id) = explode('/', $name) + array(1 => 'primary');

			self::$models[$name] = $core->getModule($module_id)->model($model_id);
		}

		return self::$models[$name];
	}

	public function save()
	{
		$model = $this->model();
		$primary = $model->primary;

		$properties = get_object_vars($this);

		return $model->save
		(
			$properties, isset($properties[$primary]) ? $properties[$primary] : null
		);
	}
}