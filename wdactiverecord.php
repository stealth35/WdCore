<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2011 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdActiveRecord extends WdObject
{
	/**
	 * Constructor.
	 *
	 * The constructor function is required when retrieving rows as objects.
	 */
	public function __construct()
	{

	}

	/**
	 * Returns the model of the activerecord.
	 *
	 * @param string $name The name of the model.
	 * @return WdModel
	 */
	protected function model($name=null)
	{
		global $core;

		return $core->models[$name];
	}

	/**
	 * Saves the activerecord to the database using the activerecord model.
	 *
	 * @return int|bool the primary key value of the record, or false if the record could not be
	 * saved.
	 */
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

	/**
	 * Deletes the activerecord from the database.
	 */
	public function delete()
	{
		$model = $this->model();
		$primary = $model->primary;

		return $model->delete($this->$primary);
	}
}