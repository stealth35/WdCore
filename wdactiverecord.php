<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

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