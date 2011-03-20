<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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