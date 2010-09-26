<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

/*
 * PERMISSIONS
 *
 * NONE: Well, you can't do anything
 *
 * ACCESS: You can acces the module and view its resources
 *
 * CREATE: You can create new entry
 *
 * MAINTAIN: You can edit the entries you created
 *
 * MANAGE: You can delete the entries you created
 *
 * ADMINISTER: You have complete control over the module
 *
 */

// TODO: Might not be the best place here...

define('PERMISSION_NONE', 0);
define('PERMISSION_ACCESS', 1);
define('PERMISSION_CREATE', 2);
define('PERMISSION_MAINTAIN', 3);
define('PERMISSION_MANAGE', 4);
define('PERMISSION_ADMINISTER', 5);

require_once 'wdmodel.php';

class WdModule
{
	const T_CATEGORY = 'category';
	const T_DESCRIPTION = 'description';
	const T_DISABLED = 'disabled';
	const T_ID = 'id';
	const T_MANDATORY = 'mandatory';
	const T_MODELS = 'models';
	const T_PERMISSION = 'permission';
	const T_PERMISSIONS = 'permissions';
	const T_ROOT = 'root';
	const T_STARTUP = 'startup';
	const T_TITLE = 'title';

	protected $id;
	protected $root;
	protected $tags;

	public function __construct($tags)
	{
		global $core;

		if (empty($tags[self::T_ID]))
		{
			throw new WdException
			(
				'The %tag tag is mandatory', array
				(
					'%tag' => 'T_ID'
				)
			);
		}

		$this->tags = $tags;
		$this->id = $tags[self::T_ID];
		$this->root = $tags[self::T_ROOT];
	}

	public function __get($property)
	{
		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		WdDebug::trigger
		(
			'Unknow property %property for object of class %class', array
			(
				'%property' => $property, '%class' => get_class($this)
			)
		);
	}

	public function __toString()
	{
		return $this->id;
	}

	protected function __get_model()
	{
		return $this->model();
	}

	/**
	 * Check wheter or not the module is installed.
	 *
	 * @return mixed TRUE if the module is installed, FALSE if the module
	 * (or parts of) is not installed, NULL if the module has no installation.
	 *
	 */

	public function isInstalled()
	{
		if (empty($this->tags[self::T_MODELS]))
		{
			return null;
		}

		$rc = true;

		foreach ($this->tags[self::T_MODELS] as $name => $tags)
		{
			if (!$this->model($name)->isInstalled())
			{
				$rc = false;
			}
		}

		return $rc;
	}

	/**
	 * Install the module.
	 *
	 * It basically install the models defined by the module.
	 *
	 * One may override the method to extend the installation.
	 *
	 * @return mixed TRUE if the module has succeffuly been installed. FALSE if the
	 * module (or parts of the module) fails to install. NULL if the module has
	 * not installation process.
	 *
	 */

	public function install()
	{
		if (empty($this->tags[self::T_MODELS]))
		{
			return null;
		}

		$rc = true;

		foreach ($this->tags[self::T_MODELS] as $name => $tags)
		{
			//wd_log('install %module, install model %model', array('%module' => $this->id, '%model' => $name));

			$model = $this->model($name);

			if ($model->isInstalled())
			{
				//wd_log('model %model already installed', array('%model' => $name));

				continue;
			}

			//wd_log('install model: %model', array('%model' => $name));

			if (!$model->install())
			{
				//wd_log('Model %model failed to install', array('%model' => $name));

				$rc = false;
			}
		}

		return $rc;
	}

	/**
	 * Uninstall the module.
	 *
	 * Basically it uninstall the models installed by the module.
	 *
	 * @return mixed TRUE is the module has successfully been uninstalled. FALSE if the module
	 * (or parts of the module) failed to uninstall. NULL if there is no unistall process.
	 */

	public function uninstall()
	{
		if (empty($this->tags[self::T_MODELS]))
		{
			return;
		}

		$rc = true;

		foreach ($this->tags[self::T_MODELS] as $name => $tags)
		{
			$model = $this->model($name);

			if (!$model->isInstalled())
			{
				continue;
			}

			if (!$model->uninstall())
			{
				$rc = false;
			}
		}

		return $rc;
	}

	public function run()
	{
		return true;
	}

	/**
	 * @var array Used to cache loaded models.
	 */

	protected $models = array();

	/**
	 * Get a model from the module.
	 *
	 * If the model has not been created yet, it is created on the fly.
	 *
	 * @param $which
	 * @return unknown_type
	 */

	public function model($which='primary')
	{
		global $core;

		if (empty($this->models[$which]))
		{
			if (empty($this->tags[self::T_MODELS][$which]))
			{
				throw new WdException
				(
					'Unknown model %model for the %module module', array
					(
						'%model' => $which,
						'%module' => $this->id
					)
				);
			}

			#
			# default
			#

			//$name = str_replace('_WdModule', '', get_class($this));

			$prefix = '_WdModule';
			$prefixLength = strlen($prefix);

			$name = substr(get_class($this), 0, -$prefixLength);

			if ($which != 'primary')
			{
				$name .= '_' . $which;
			}

			$tags = $this->tags[self::T_MODELS][$which] += array
			(
				WdModel::T_CLASS => file_exists($this->root . $which . '.model.php') ? $name . '_WdModel' : null,
				WdModel::T_ACTIVERECORD_CLASS => file_exists($this->root . $which . '.ar.php') ? $name . '_WdActiveRecord' : null,
				WdModel::T_NAME => $name,
				WdModel::T_CONNECTION => 'primary'
			);

			#
			# relations
			#

			global $core;

			if (isset($tags[WdModel::T_EXTENDS]))
			{
				$extends = &$tags[WdModel::T_EXTENDS];

				if (is_string($extends))
				{
					//$extends = $core->getModule($extends)->model();
					$extends = $core->models[$extends];
				}

				if (!$tags[WdModel::T_CLASS])
				{
					$tags[WdModel::T_CLASS] = get_class($extends);

					//wd_log('model class for \1 is \2', array($this . '/' . $which, $extends->name_unprefixed));
				}
			}

			if (!$tags[WdModel::T_CLASS])
			{
				$tags[WdModel::T_CLASS] = 'WdModel';
			}

			if (isset($tags[WdModel::T_IMPLEMENTS]))
			{
				$implements =& $tags[WdModel::T_IMPLEMENTS];

				foreach ($implements as &$implement)
				{
					if (isset($implement['model']))
					{
						list($i_module, $i_which) = array_pad(explode('/', $implement['model']), 2, 'primary');

						if ($this->id == $i_module && $which == $i_which)
						{
							throw new WdException('Model %module/%model implements itself !', array('%module' => $this->id, '%model' => $which));
						}

						$module =  ($i_module == $this->id) ? $this : $core->getModule($i_module);

						$implement['table'] = $module->model($i_which);
					}
					else if (is_string($implement['table']))
					{
						throw new WdException
						(
							'Model %model of module %module implements a table: %table', array
							(
								'%model' => $which,
								'%module' => $this->id,
								'%table' => $implement['table']
							)
						);

						//$implement['table'] = $core->getModule($implement['table'])->model();
						$implement['table'] = $core->models[$implement['table']];
					}
				}
			}

			#
			# connection
			#

			if (is_string($tags[WdModel::T_CONNECTION]))
			{
				$tags[WdModel::T_CONNECTION] = $core->db($tags[WdModel::T_CONNECTION]);
			}

			#
			# create model
			#

			$class = $tags[WdModel::T_CLASS];

			//wd_log('create model \2 with tags: \1', array($tags, $this->id . '/' . $which));

			$this->models[$which] = new $class($tags);
		}

		#
		# return cached model
		#

		return $this->models[$which];
	}

	/*
	**

	OPERATIONS

	**
	*/

	const OPERATION_SAVE = 'save';
	const OPERATION_DELETE = 'delete';

	public function handle_operation(WdOperation $operation)
	{
		#
		# We check if the operation is handled by the module.
		#

		$name = $operation->name;
		$callback = 'operation_' . $name;

		if (!method_exists($this, $callback))
		{
			throw new WdException
			(
				'Unknown operation %operation for the %module module', array
				(
					'%operation' => $name,
					'%module' => $this->id
				),

				404
			);
		}

		#
		# Before we process the operation, we ask for its validation.
		#

		if (!$this->handle_operation_control($operation))
		{
			return;
		}

		#
		# The operation access has been controled and validated, we can now call the operation
		# callback method.
	  	#

		return $this->$callback($operation);
	}

	/*
	 *
	 */

	const CONTROL_AUTHENTICATION = 101;
	const CONTROL_PERMISSION = 102;
	const CONTROL_ENTRY = 103;
	const CONTROL_OWNERSHIP = 104;
	const CONTROL_FORM = 105;
	const CONTROL_VALIDATOR = 106;

	protected function getOperationsAccessControls()
	{
		return array
		(
			self::OPERATION_SAVE => array
			(
				self::CONTROL_PERMISSION => PERMISSION_CREATE,
				self::CONTROL_OWNERSHIP => true,
				self::CONTROL_FORM => true,
				self::CONTROL_VALIDATOR => true
			),

			self::OPERATION_DELETE => array
			(
				self::CONTROL_PERMISSION => PERMISSION_MANAGE,
				self::CONTROL_OWNERSHIP => true,
				self::CONTROL_FORM => false,
				self::CONTROL_VALIDATOR => true
			)
		);
	}

	protected function handle_operation_control(WdOperation $operation)
	{
		$operation_name = $operation->name;

		$controls = $this->getOperationsAccessControls();
		$controls = isset($controls[$operation_name]) ? $controls[$operation_name] : array();

		#
		# Add some controls for the ownership control. The controls are added using a union so that
		# they won't override user defined controls.
		#

		if (!empty($controls[self::CONTROL_OWNERSHIP]))
		{
			$controls += array
			(
				self::CONTROL_AUTHENTICATION => true,
				self::CONTROL_ENTRY => true
			);
		}

		#
		# Fill in with defaults
		#

		$controls += array
		(
			self::CONTROL_AUTHENTICATION => false,
			self::CONTROL_PERMISSION => PERMISSION_NONE,
			self::CONTROL_ENTRY => false,
			self::CONTROL_OWNERSHIP => false,
			self::CONTROL_FORM => false,
			self::CONTROL_VALIDATOR => true
		);

		$callback = 'control_operation_' . $operation->name;

		if (!method_exists($this, $callback))
		{
			$callback = 'control_operation';
		}

		return $this->$callback($operation, $controls);
	}

	protected function control_operation(WdOperation $operation, array $controls)
	{
		$operation_name = $operation->name;

		if ($controls[self::CONTROL_AUTHENTICATION])
		{
			$callback = 'control_operation_' . $operation_name . '_authentication';

			if (!method_exists($this, $callback))
			{
				$callback = 'control_operation_authentication';
			}

			if (!$this->$callback($operation))
			{
				throw new WdHTTPException
				(
					'The %operation operation requires authentication.', array
					(
						'%operation' => $operation_name
					),

					401
				);
			}
		}

		if ($controls[self::CONTROL_PERMISSION])
		{
			$callback = 'control_operation_' . $operation_name . '_permission';

			if (!method_exists($this, $callback))
			{
				$callback = 'control_operation_permission';
			}

			if (!$this->$callback($operation, $controls[self::CONTROL_PERMISSION]))
			{
				throw new WdHTTPException
				(
					"You don't have permission to request the %operation operation on the %module module.", array
					(
						'%operation' => $operation_name,
						'%module' => $this->id
					),

					401
				);
			}
		}

		if ($controls[self::CONTROL_ENTRY])
		{
			$callback = 'control_operation_' . $operation_name . '_entry';

			if (!method_exists($this, $callback))
			{
				$callback = 'control_operation_entry';
			}

			if (!$this->$callback($operation))
			{
				throw new WdHTTPException
				(
					"The requested entry %key could not be loaded from the %module module.", array
					(
						'%key' => $key,
						'%module' => $this->id
					),

					404
				);
			}
		}

		if ($controls[self::CONTROL_OWNERSHIP])
		{
			$callback = 'control_operation_' . $operation_name . '_ownership';

			if (!method_exists($this, $callback))
			{
				$callback = 'control_operation_ownership';
			}

			if (!$this->$callback($operation))
			{
				throw new WdHTTPException
				(
					"You don't have ownership of the entry %key.", array
					(
						'%key' => $operation->key
					),

					401
				);
			}
		}

		if ($controls[self::CONTROL_FORM])
		{
			$callback = 'control_operation_' . $operation_name . '_form';

			if (!method_exists($this, $callback))
			{
				$callback = 'control_operation_form';
			}

			if (!$this->$callback($operation))
			{
				wd_log('Control %control failed for operation %operation on module %module.', array('%control' => 'form', '%module' => $this->id, '%operation' => $operation_name));

				return false;
			}
		}

		if ($controls[self::CONTROL_VALIDATOR])
		{
			$callback = 'validate_operation_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = 'validate_operation';
			}

			if (!$this->$callback($operation))
			{
				wd_log('Control down on validator. Module: %module, operation: %operation', array('%module' => $this->id, '%operation' => $operation_name));

				return false;
			}
		}

		return true;
	}

	protected function control_operation_authentication(WdOperation $operation)
	{
		global $app;

		return ($app->user_id != 0);
	}

	protected function control_operation_permission(WdOperation $operation, $permission)
	{
		global $app;

		if (!$app->user->has_permission($permission, $this))
		{
			return false;
		}

		return true;
	}

	/**
	 * Control the existence of the entry the operation is to be applied to.
	 *
	 * The operation's key is used to load the entry from the primary model of the module. If the
	 * loading fails, the method returns false. Otherwise, the loaded entry is added to the
	 * operation object under the `entry` property and the method returns true.
	 *
	 * @param $operation The operation object.
	 */

	protected function control_operation_entry(WdOperation $operation)
	{
		$key = $operation->key;

		if (!$key)
		{
			$operation->entry = null;

			return false;
		}

		$entry = $this->model()->load($key);

		if (!$entry)
		{
			return false;
		}

		$operation->entry = $entry;

		return true;
	}

	/**
	 * Control 'entry' for the 'save' operation.
	 *
	 * Unlike the default 'entry' control, this method return true if the operation as no key.
	 *
	 * @param WdOperation $operation
	 */

	protected function control_operation_save_entry(WdOperation $operation)
	{
		if (!$operation->key)
		{
			$operation->entry = null;

			return true;
		}

		return $this->control_operation_entry($operation);
	}

	/**
	 * Control the ownership of the user over the operation destination entry.
	 *
	 * The control is considered sucessful if the entry can be loaded and the ownership of the
	 * user confirmed. The `user` and `entry` properties are added to the operation object.
	 *
	 * Note that the control is considered sucessful if the operation has no key, in which case
	 * the `user` and `entry` properties added to the operation object are null.
	 *
	 * Note: This is not control_operation_entry(). If the operation's key is not defined the control will
	 * still return TRUE.
	 *
	 * @param WdOperation $operation
	 * @return bool
	 */

	protected function control_operation_ownership(WdOperation $operation)
	{
		$key = $operation->key;

		if (!$key)
		{
			return true;
		}

		$entry = $operation->entry;

		if ($entry)
		{
			global $app;

			if (!$app->user->has_ownership($this, $entry))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Control the form associated with the operation.
	 *
	 * This is the default method callback for the `form` control.
	 *
	 * The function assumes the form was saved in the user's session.
	 *
	 * If the function fails to retieve or validate the saved form it returns false. Otherwise
	 * the retrieved form is set in the operation object under the `form` property and the function
	 * returns true.
	 *
	 * One can override this method to modify operation parameters before the form gets validated,
	 * or override the method to control unsaved forms.
	 *
	 * @param $operation
	 * @return bool
	 */

	protected function control_operation_form(WdOperation $operation)
	{
		$params = &$operation->params;

		$form = isset($operation->form) ? $operation->form : WdForm::load($params);

		if (!$form || !$form->validate($params))
		{
			return false;
		}

		$operation->form = $form;

		return true;
	}

	/**
	 * Default callback for the 'validate' control.
	 *
	 * If the module doesn't define a validator for an operation, an exception is thrown.
	 *
	 * @param array $operation
	 */

	protected function validate_operation(WdOperation $operation)
	{
		throw new WdException
		(
			'The %module module is missing a validator for the %operation operation', array
			(
				'%operation' => $operation->name,
				'%module' => $this->id
			)
		);
	}

	/**
	 * Handle the OPERATION_SAVE operation
	 *
	 * @param array $params
	 * @return array Result of the operation, or `null` if the operation failed.
	 */

	protected function operation_save(WdOperation $operation)
	{
		$operation_key = $operation->key;
		$key = $this->model()->save($operation->params, $operation_key);
		$log_params = array('%key' => $operation_key, '%module' => $this->id);

		if (!$key)
		{
			#
			# We need to return `null` because `false` is a valid result for the
			# WdOperation::dispatch() method, and will trigger an event, which is something we
			# don't want to happen since the operation failed.
			#

			wd_log_error($operation_key ? 'Unable to update entry %key in %module.' : 'Unable to create entry in %module.', $log_params, 'save');

			return;
		}

		wd_log_done($operation_key ? 'The entry %key in %module has been saved.' : 'A new entry has been saved in %module.', $log_params, 'save');

		return array
		(
			'mode' => $operation_key ? 'update' : 'create',
			'key' => $key
		);
	}

	/**
	 * Validates the 'delete' operation.
	 *
	 * The operation is validated only if the operation key is defined.
	 *
	 * @param WdOperation $operation
	 */

	protected function validate_operation_delete(WdOperation $operation)
	{
		if (empty($operation->params[WdOperation::KEY]) && empty($operation->params[WdOperation::KEYS]))
		{
			return false;
		}

		return true;
	}

	protected function operation_delete(WdOperation $operation)
	{
		$params = &$operation->params;

		if (isset($params[WdOperation::KEYS]))
		{
			$keys = $params[WdOperation::KEYS];

			foreach ($keys as $key => $dummy)
			{
				if ($this->model()->delete($key))
				{
					wd_log_done('The entry %key has been delete from %module.', array('%key' => $key, '%module' => $this->id));

					continue;
				}

				wd_log_error('Unable to delete the entry %key from %module.', array('%key' => $key, '%module' => $this->id));
			}
		}
		else if ($operation->key)
		{
			$key = $operation->key;

			if ($this->model()->delete($key))
			{
				wd_log_done('The entry %key has been delete from %module.', array('%key' => $key, '%module' => $this->id));

				return true;
			}
			else
			{
				wd_log_error('Unable to delete the entry %key from %module.', array('%key' => $key, '%module' => $this->id));

				return;
			}
		}
		else
		{
			throw new WdException('Keys are missing for the delete operation.');
		}
	}

	/**
	 * Get a block.
	 *
	 * @param $name
	 * The name of the block to get.
	 *
	 * @return mixed
	 * Depends on the implementation. Should return a string or a stringifyable object.
	 */

	public function getBlock($name)
	{
		$args = func_get_args();

		array_shift($args);

		$callback = 'block_' . $name;

		if (!method_exists($this, $callback))
		{
			throw new WdException
			(
				'There is no method defined by the %module module to create blocks of type %type', array
				(
					'%module' => $this->id,
					'%type' => $name
				)
			);
		}

		return call_user_func_array(array($this, $callback), $args);
	}
}