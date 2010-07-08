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
			//return $rc;
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
}

class WdActiveRecord extends WdObject
{
	protected function model($name=null)
	{
		global $core;

		return $core->models[$name];
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

	public function delete()
	{
		$model = $this->model();
		$primary = $model->primary;

		return $model->delete($this->$primary);
	}
}