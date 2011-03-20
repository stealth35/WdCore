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
 * Deletes a record.
 */
class delete_WdOperation extends WdOperation
{
	/**
	 * Controls for the operation: permission(manage), record and ownership.
	 *
	 * @see WdOperation::__get_controls()
	 */
	protected function __get_controls()
	{
		return array
		(
			self::CONTROL_PERMISSION => WdModule::PERMISSION_MANAGE,
			self::CONTROL_RECORD => true,
			self::CONTROL_OWNERSHIP => true
		)

		+ parent::__get_controls();
	}

	protected function validate()
	{
		return true;
	}

	/**
	 * Delete the target record.
	 *
	 * @see WdOperation::process()
	 */
	protected function process()
	{
		$key = $this->key;

		if (!$this->module->model->delete($key))
		{
			wd_log_error('Unable to delete the record %key from %module.', array('%key' => $key, '%module' => (string) $this->module));

			return;
		}

		if (isset($this->params['#location']))
		{
			$this->location = $this->params['#location'];
		}

		wd_log_done('The record %key has been delete from %module.', array('%key' => $key, '%module' => $this->module->title), 'delete');

		return $key;
	}
}