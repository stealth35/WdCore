<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * The "save" operation is used to create or update a record.
 */
class save_WdOperation extends WdOperation
{
	/**
	 * Change controls:
	 *
	 * CONTROL_PERMISSION => WdModule::PERMISSION_CREATE
	 * CONTROL_OWNERSHIP => true
	 * CONTROL_FORM => true
	 *
	 * @param WdOperation $operation
	 * @return array The controls of the operation.
	 */
	protected function __get_controls()
	{
		return array
		(
			self::CONTROL_PERMISSION => WdModule::PERMISSION_CREATE,
			self::CONTROL_RECORD => true,
			self::CONTROL_OWNERSHIP => true,
			self::CONTROL_FORM => true
		)

		+ parent::__get_controls();
	}

	/**
	 * Overrides the getter to prevent exceptions when the operation key is empty.
	 *
	 * @see WdOperation::__get_record()
	 */
	protected function __get_record()
	{
		return $this->key ? parent::__get_record() : null;
	}

	/**
	 * Overrides the method in order for the control to pass if the operation key is empty, which
	 * is the case when creating a new record.
	 *
	 * @see WdOperation::control_record()
	 */
	protected function control_record()
	{
		return $this->key ? parent::control_record() : true;
	}

	/**
	 * Filters out the operation's parameters, which are not defined as fields by the
	 * primary model of the module, and take care of filtering or resolving properties values.
	 *
	 * Fields defined as 'boolean'
	 * ---------------------------
	 *
	 * The value of the property is filtered using the filter_var() function and the
	 * FILTER_VALIDATE_BOOLEAN filter. If the property in the operation params is empty, the
	 * property value is set the `false`.
	 *
	 * Fields defined as 'varchar'
	 * ---------------------------
	 *
	 * If the property is not empty in the operation params, the property value is trimed using the
	 * trim() function, ensuring that there is no leading or trailing white spaces.
	 *
	 * @see WdOperation::__get_properties()
	 * @return array The properties of the operation.
	 */
	protected function __get_properties()
	{
		$schema = $this->module->model->get_extended_schema();
		$fields = $schema['fields'];
		$properties = array_intersect_key($this->params, $fields);

		foreach ($fields as $identifier => $definition)
		{
			$type = $definition['type'];

			if ($type == 'boolean')
			{
				if (empty($properties[$identifier]))
				{
					$properties[$identifier] = false;

					continue;
				}

				$properties[$identifier] = filter_var($properties[$identifier], FILTER_VALIDATE_BOOLEAN);
			}
			else if ($type == 'varchar')
			{
				if (empty($properties[$identifier]) || !is_string($properties[$identifier]))
				{
					continue;
				}

				$properties[$identifier] = trim($properties[$identifier]);
			}
		}

		return $properties;
	}

	/**
	 * The method simply returns true.
	 *
	 * @see WdOperation::validate()
	 */
	protected function validate()
	{
		return true;
	}

	/**
	 * Creates or updates a record in the module's primary model.
	 *
	 * A record is created if the operation's key is empty, otherwise an existing record is
	 * updated.
	 *
	 * The method uses the `properties` property to get the properties used to create or update
	 * the record.
	 *
	 * @return array An array composed of the save mode ('update' or 'create') and the record's
	 * key.
	 * @throws WdException when saving the record fails.
	 */
	protected function process()
	{
		$key = $this->key;
		$record_key = $this->module->model->save($this->properties, $key);
		$log_params = array('%key' => $key, '%module' => $this->module->title);

		if (!$record_key)
		{
			throw new WdException($key ? 'Unable to update record %key in %module.' : 'Unable to create record in %module.', $log_params);
		}

		$this->location = $_SERVER['REQUEST_URI'];

		wd_log_done($key ? 'The record %key in %module has been saved.' : 'A new record has been saved in %module.', $log_params, 'save');

		return array('mode' => $key ? 'update' : 'create', 'key' => $record_key);
	}
}