<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2011 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdModule extends WdObject
{
	const T_CATEGORY = 'category';
	const T_DESCRIPTION = 'description';
	const T_DISABLED = 'disabled';
	const T_EXTENDS = 'extends';
	const T_ID = 'id';
	const T_REQUIRED = 'required';
	const T_MODELS = 'models';
	const T_PERMISSION = 'permission';
	const T_PERMISSIONS = 'permissions';
	const T_PATH = 'path';
	const T_STARTUP = 'startup';
	const T_TITLE = 'title';

	/*
	 * PERMISSIONS
	 *
	 * NONE: Well, you can't do anything
	 *
	 * ACCESS: You can acces the module and view its records
	 *
	 * CREATE: You can create new records
	 *
	 * MAINTAIN: You can edit the records you created
	 *
	 * MANAGE: You can delete the records you created
	 *
	 * ADMINISTER: You have complete control over the module
	 *
	 */

	const PERMISSION_NONE = 0;
	const PERMISSION_ACCESS = 1;
	const PERMISSION_CREATE = 2;
	const PERMISSION_MAINTAIN = 3;
	const PERMISSION_MANAGE = 4;
	const PERMISSION_ADMINISTER = 5;

	static public function is_extending($module_id, $extending_id)
	{
		global $core;

		while ($module_id)
		{
			if ($module_id == $extending_id)
			{
				return true;
			}

			$descriptor = $core->modules->descriptors[$module_id];

			$module_id = isset($descriptor[self::T_EXTENDS]) ? $descriptor[self::T_EXTENDS] : null;
		}

		return false;
	}

	protected $id;
	protected $path;
	protected $tags;

	public function __construct($tags)
	{
		if (empty($tags[self::T_ID]))
		{
			throw new WdException
			(
				'The %tag tag is required', array
				(
					'%tag' => 'T_ID'
				)
			);
		}

		$this->tags = $tags;
		$this->id = $tags[self::T_ID];
		$this->path = $tags[self::T_PATH];
	}

	public function __toString()
	{
		return $this->id;
	}

	protected function __get_flat_id()
	{
		return strtr($this->id, '.', '_');
	}

	/**
	 * Getter for the primary model.
	 *
	 * @return WdModel The _primary_ model for the module.
	 */

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
	public function is_installed()
	{
		if (empty($this->tags[self::T_MODELS]))
		{
			return null;
		}

		$rc = true;

		foreach ($this->tags[self::T_MODELS] as $name => $tags)
		{
			if (!$this->model($name)->is_installed())
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
	 * @return mixed TRUE if the module has successfully been installed. FALSE if the
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

			if ($model->is_installed())
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

			if (!$model->is_installed())
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
	 * @return WdModel The requested model.
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
			# resolve model tags
			#

			$callback = "resolve_{$which}_model_tags";

			if (!method_exists($this, $callback))
			{
				$callback = 'resolve_model_tags';
			}

			$tags = $this->$callback($this->tags[self::T_MODELS][$which], $which);

			#
			# COMPAT WITH 'inherit'
			#

			if ($tags instanceof WdModel)
			{
				$this->models[$which] = $tags;

				return $tags;
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

	protected function resolve_model_tags($tags, $which)
	{
		global $core;

		$ns = $this->flat_id;

		$has_model_class = file_exists($this->path . $which . '.model.php');
		$has_ar_class = file_exists($this->path . $which . '.ar.php');

		$table_name = $ns;

		if ($which != 'primary')
		{
			$table_name .= '_' . $which;
		}

		#
		# The model may use another model, in which case the model to used is defined using a
		# string e.g. 'contents' or 'taxonomy.terms/nodes'
		#

		if (is_string($tags))
		{
			$model_name = $tags;

			if ($model_name == 'inherit')
			{
				$prefix = '_WdModule';
				$prefixLength = strlen($prefix);

				$inherit_module_id = substr(get_parent_class($this), 0, -$prefixLength);

//				wd_log('inherit model %model from module %module', array('%model' => $which, '%module' => $inherit_module_id));

				$model_name = $inherit_module_id . '/' . $which;
				$model_name = strtr($model_name, '_', '.');
			}

			$tags = array
			(
				WdModel::T_EXTENDS => $model_name
			);
		}


		#
		# defaults
		#

		$tags += array
		(
			WdModel::T_CLASS => $has_model_class ? $ns . ($which == 'primary' ? '' : '_' . $which) . '_WdModel' : null,
			WdModel::T_ACTIVERECORD_CLASS => $has_ar_class ? $ns . ($which == 'primary' ? '' : '_' . $which) . '_WdActiveRecord' : null,
			WdModel::T_NAME => $table_name,
			WdModel::T_CONNECTION => 'primary'
		);

		#
		# relations
		#

		if (isset($tags[WdModel::T_EXTENDS]))
		{
			$extends = &$tags[WdModel::T_EXTENDS];

			if (is_string($extends))
			{
				$extends = $core->models[$extends];
			}

			if (!$tags[WdModel::T_CLASS])
			{
				$tags[WdModel::T_CLASS] = get_class($extends);
			}
		}

		#
		#
		#

		if (isset($tags[WdModel::T_IMPLEMENTS]))
		{
			$implements =& $tags[WdModel::T_IMPLEMENTS];

			foreach ($implements as &$implement)
			{
				if (isset($implement['model']))
				{
					list($i_module, $i_which) = explode('/', $implement['model']) + array(1 => 'primary');

					if ($this->id == $i_module && $which == $i_which)
					{
						throw new WdException('Model %module/%model implements itself !', array('%module' => $this->id, '%model' => $which));
					}

					$module = ($i_module == $this->id) ? $this : $core->modules[$i_module];

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

					$implement['table'] = $core->models[$implement['table']];
				}
			}
		}

		#
		# default class, if none was defined.
		#

		if (!$tags[WdModel::T_CLASS])
		{
			$tags[WdModel::T_CLASS] = 'WdModel';
		}

		#
		# connection
		#

		$connection = $tags[WdModel::T_CONNECTION];

		if (is_string($connection))
		{
			$tags[WdModel::T_CONNECTION] = $core->connections[$connection];
		}

		return $tags;
	}

	/*
	 * OPERATIONS
	 */

	/**
	 * Handles a specified operation.
	 *
	 * Before processing the operation, the function first checks if the operation is actually
	 * implemented by the module, and if it's valid.
	 *
	 * Checking the operation's implementation
	 * =======================================
	 *
	 * A callback method is required for any operation to be processed. The name of the method
	 * must follow the pattern "operation_<name>", where "<name>" is the name of the operation. A
	 * module is considered being capable of handling an operation if its associated method is
	 * implemented.
	 *
	 * If the required method is not implemented by the module's class, an exception with code
	 * 404 is thrown.
	 *
	 * Checking the validity of an operation
	 * =====================================
	 *
	 * A control chain, often specific to the operation, must be passed for an operation to be
	 * processed. The control chain is handled by the method handle_operation_control(), failures
	 * often resulting in an exception being thrown.
	 *
	 * Processing the operation
	 * ========================
	 *
	 * Once the controls passed successfully, the operation is processed by invoking the callback
	 * method associated with the operation.
	 *
	 * @param WdOperation $operation The operation object to handle.
	 *
	 * @throws WdHTTPException
	 */

	public function handle_operation(WdOperation $operation)
	{
		$name = $operation->name;
		$callback = 'operation_' . $name;

		if (!method_exists($this, $callback))
		{
			throw new WdHTTPException
			(
				'Unknown operation %operation for the %module module.', array
				(
					'%operation' => $name, '%module' => $this->id
				),

				404
			);
		}

		if (!$this->handle_operation_control($operation))
		{
			return;
		}

		return $this->$callback($operation);
	}

	const CONTROL_AUTHENTICATION = 101;
	const CONTROL_PERMISSION = 102;
	const CONTROL_RECORD = 103;
	const CONTROL_OWNERSHIP = 104;
	const CONTROL_FORM = 105;
	const CONTROL_VALIDATOR = 106;
	const CONTROL_PROPERTIES = 107;

	/**
	 * Returns the default controls for any operation.
	 *
	 * All controls are defined, but onyl the _validator_ control is requested.
	 *
	 * @param WdOperation $operation
	 */

	protected function controls_for_operation(WdOperation $operation)
	{
		return array
		(
			self::CONTROL_AUTHENTICATION => false,
			self::CONTROL_PERMISSION => self::PERMISSION_NONE,
			self::CONTROL_RECORD => false,
			self::CONTROL_OWNERSHIP => false,
			self::CONTROL_FORM => false,
			self::CONTROL_VALIDATOR => true,
			self::CONTROL_PROPERTIES => false
		);
	}

	/**
	 * Handles the operation control.
	 *
	 * Controls for the operation are retrieved by invoking the "controls_for_operation[_<name>]"
	 * method. The control chain is processed by invoking the "control_operation[_<name>]" method.
	 *
	 * @param WdOperation $operation
	 * @return boolean Wheter or not the controls were successfully passed.
	 */

	protected function handle_operation_control(WdOperation $operation)
	{
		$operation_name = $operation->name;

		$fallback = 'controls_for_operation';
		$callback = $fallback . '_' . $operation_name;

		if (!method_exists($this, $callback))
		{
			$callback = $fallback;
		}

		$controls = $this->$callback($operation) + $this->controls_for_operation($operation);

		if (!empty($controls[self::CONTROL_OWNERSHIP]))
		{
			$controls[self::CONTROL_RECORD] = true;
		}

		$fallback = 'control_operation';
		$callback = $fallback . '_' . $operation->name;

		if (!method_exists($this, $callback))
		{
			$callback = $fallback;
		}

		return $this->$callback($operation, $controls);
	}

	/**
	 * Controls the operation.
	 *
	 * A number of controls to pass may be requested before an operation is processed. This
	 * function tries the specified controls (or operation specific controls if they are defined).
	 * If all the specified controls are passed, the operation control is considered sucessful.
	 *
	 * Controls are passed in the following order:
	 *
	 * 1. CONTROL_AUTHENTICATION
	 *
	 * Controls the authentication of the user. The"control_authentication_for_operation[_<name>]"
	 * callback method is invoked for this control. An exception with the code 401 is thrown if
	 * the control fails.
	 *
	 * 2. CONTROL_PERMISSION
	 *
	 * Controls the permission of the guest or user. The
	 * "control_permission_for_operation[_<name>]" callback method is invoked for this control. An
	 * exception with code 401 is thrown if the control fails.
	 *
	 * 3. CONTROL_RECORD
	 *
	 * Controls the existence of the record specified by the operation's key. The
	 * "control_record_for_operation[_<name>]" callback method is invoked for this control. The
	 * value returned by the callback method is set in the operation objet under the `record`
	 * property. The callback method must throw an exception if the record could not be loaded or
	 * the control of this record failed.
	 *
	 * 4. CONTROL_OWNERSHIP
	 *
	 * Controls the ownership of the user over the record found during the CONTROL_RECORD step. The
	 * "control_ownership_for_operation[_<name>]" callback method is invoked for the control. An
	 * exception with code 401 is thrown if the control fails.
	 *
	 * 5. CONTROL_FORM
	 *
	 * Controls the form associated with the operation by checking its existence and validity.
	 * The "control_form_for_operation[_<name>]" callback method is invoked for this control.
	 * Failing the control won't throw an exception, but a message will be logged to the debug log.
	 *
	 * 6. CONTROL_PROPERTIES
	 *
	 * Controls the operation's params and process them to create properties suitable for the
	 * module's primary model. The "control_properties_for_operation[_<name>]" callback method is
	 * invoked for this control. Failling the control won't throw an exception, but a message will
	 * be logged to the debug log.
	 *
	 * 7. CONTROL_VALIDATOR
	 *
	 * Validate the operation using the "validate_operation[_<name>]" callback method. Failing the
	 * control won't throw an exception, but a message will be logged to the debug log.
	 *
	 * @param WdOperation $operation The operation object.
	 * @param array $controls The controls to pass for the operation to be processed.
	 * @return boolean Wheter or not the controls where passed.
	 */

	protected function control_operation(WdOperation $operation, array $controls)
	{
		$operation_name = $operation->name;

		if ($controls[self::CONTROL_AUTHENTICATION])
		{
			$fallback = 'control_authentication_for_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
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
			$fallback = 'control_permission_for_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
			}

			$this->$callback($operation, $controls[self::CONTROL_PERMISSION]);
		}

		if ($controls[self::CONTROL_RECORD])
		{
			$fallback = 'control_record_for_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
			}

			$operation->record = $this->$callback($operation);

			/*
			if (!$record instanceof WdActiveRecord)
			{
				throw new WdHTTPException
				(
					"The requested record could not be loaded from the %module module: %key", array
					(
						'%key' => $operation->key,
						'%module' => $this->id
					),

					404
				);
			}
			*/
		}

		if ($controls[self::CONTROL_OWNERSHIP])
		{
			$fallback = 'control_ownership_for_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
			}

			if (!$this->$callback($operation))
			{
				throw new WdHTTPException
				(
					"You don't have ownership of the record: %key", array
					(
						'%key' => $operation->key
					),

					401
				);
			}
		}

		if ($controls[self::CONTROL_FORM])
		{
			$fallback = 'control_form_for_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
			}

			if (!$this->$callback($operation))
			{
				wd_log('Control %control failed for operation %operation on module %module.', array('%control' => 'form', '%module' => $this->id, '%operation' => $operation_name));

				return false;
			}
		}

		if ($controls[self::CONTROL_PROPERTIES])
		{
			$fallback = 'control_properties_for_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
			}

			try
			{
				$operation->properties = $this->$callback($operation);
			}
			catch (Exception $e)
			{
				wd_log
				(
					"Control %control failed for operation %operation on module %module: :exception", array
					(
						'%control' => 'properties', '%module' => $this->id, '%operation' => $operation_name, ':exception' => $e->getMessage()
					)
				);

				return false;
			}
		}

		if ($controls[self::CONTROL_VALIDATOR])
		{
			$fallback = 'validate_operation';
			$callback = $fallback . '_' . $operation_name;

			if (!method_exists($this, $callback))
			{
				$callback = $fallback;
			}

			if (!$this->$callback($operation))
			{
				wd_log('Control failed on validator. Module: %module, operation: %operation', array('%module' => $this->id, '%operation' => $operation_name));

				return false;
			}
		}

		return true;
	}

	/**
	 * Controls the authentication of the user for the operation.
	 *
	 * @param WdOperation $operation
	 */

	protected function control_authentication_for_operation(WdOperation $operation)
	{
		global $core;

		return ($core->user_id != 0);
	}

	/**
	 * Controls the permission of the user for the operation.
	 *
	 * @param WdOperation $operation The operation object.
	 * @param mixed $permission The required permission.
	 * @throws WdException if the user doesn't have the specified permission.
	 */

	protected function control_permission_for_operation(WdOperation $operation, $permission)
	{
		global $core;

		if (!$core->user->has_permission($permission, $this))
		{
			throw new WdHTTPException
			(
				"You don't have permission to perform the %operation operation on the %module module.", array
				(
					'%operation' => $operation->name,
					'%module' => $this->id
				),

				401
			);
		}

		return true;
	}

	/**
	 * Controls the properties for the operation.
	 *
	 * Currently, this generic method returns an empty array as properties.
	 *
	 * @param WdOperation $operation
	 * @return array An empty array.
	 */

	protected function control_properties_for_operation(WdOperation $operation)
	{
		return array();
	}

	/**
	 * Control the existence of the record the operation is to be applied to.
	 *
	 * The operation's key is used to find the record in the module's primary model. The found
	 * record is stored in the 'record' property of the operation object.
	 *
	 * @param $operation The operation object.
	 * @throws WdException when the record cannot be found in the model.
	 */

	protected function control_record_for_operation(WdOperation $operation)
	{
		return $this->model[$operation->key];
	}

	/**
	 * Override the record control for the "save" operation in order for the control to pass even
	 * if the operation's key is empty, which is the case when creating a new record.
	 *
	 * @param WdOperation $operation
	 */

	protected function control_record_for_operation_save(WdOperation $operation)
	{
		return $operation->key ? $this->control_record_for_operation($operation) : null;
	}

	/**
	 * Controls the ownership of the user over the operation's record.
	 *
	 * The control is failed if a record was found but the user has no ownership on that record.
	 *
	 * The control is sucessful if there is no record in the operation object, or there is a record
	 * and the user has ownership on that record.
	 *
	 * @param WdOperation $operation
	 * @return bool
	 */

	protected function control_ownership_for_operation(WdOperation $operation)
	{
		global $core;

		$record = $operation->record;

		if ($record && !$core->user->has_ownership($this, $record))
		{
			return false;
		}

		return true;
	}

	/**
	 * Control the form for the operation.
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

	protected function control_form_for_operation(WdOperation $operation)
	{
		$params = &$operation->params;

		if (empty($operation->form))
		{
			$operation->form = WdForm::load($params);
		}

		$form = $operation->form;

		if (!$form || !$form->validate($params))
		{
			return false;
		}

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

	const OPERATION_SAVE = 'save';

	/**
	 * Returns the controls for the "save" operation.
	 *
	 * @param WdOperation $operation
	 * @return array The controls of the operation.
	 */

	protected function controls_for_operation_save(WdOperation $operation)
	{
		return array
		(
			self::CONTROL_AUTHENTICATION => false,
			self::CONTROL_PERMISSION => self::PERMISSION_CREATE,
			self::CONTROL_OWNERSHIP => true,
			self::CONTROL_FORM => true,
			self::CONTROL_VALIDATOR => true,
			self::CONTROL_PROPERTIES => true
		);
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
	 * @param WdOperation $operation
	 *
	 * @return array The controled properties.
	 */
	protected function control_properties_for_operation_save(WdOperation $operation)
	{
		$schema = $this->model->get_extended_schema();
		$fields = $schema['fields'];
		$properties = array_intersect_key($operation->params, $fields);

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
	 * Saves a record to the primary model associated with the module.
	 *
	 * A record is either created or updated. A record is created if the operation's key is empty,
	 * otherwise an existing record is updated.
	 *
	 * The method uses the operation's `properties` property, created by the
	 * control_properties_for_operation() method, to save the record.
	 *
	 * @param WdOperation $operation An operation object.
	 * @return array An array composed of the save mode ('update' or 'create') and the record's
	 * key.
	 * @throws WdException if the method fails to save the record.
	 */

	// TODO-20110121: the operation should throw exceptions on failure. Will the current
	// implementation support this ? Those relying on test for cleanup would have to use
	// _try/catch_, the others could just forget about checking the return value, assuming it is
	// good since no exception was raised.

	protected function operation_save(WdOperation $operation)
	{
		$operation_key = $operation->key;
		$key = $this->model->save($operation->properties, $operation_key);
		$log_params = array('%key' => $operation_key, '%module' => $this->id);

		if (!$key)
		{
			#
			# We need to return `null` because `false` is a valid result for the
			# WdOperation::dispatch() method, and will trigger an event, which is something we
			# don't want to happen since the operation failed.
			#

			throw new WdException($operation_key ? 'Unable to update record %key in %module.' : 'Unable to create record in %module.', $log_params);
		}

		$operation->location = $_SERVER['REQUEST_URI'];

		wd_log_done($operation_key ? 'The record %key in %module has been saved.' : 'A new record has been saved in %module.', $log_params, 'save');

		return array
		(
			'mode' => $operation_key ? 'update' : 'create',
			'key' => $key
		);
	}

	const OPERATION_DELETE = 'delete';

	/**
	 * Returns controls for the "delete" operation.
	 *
	 * @param WdOperation $operation
	 *
	 * @return array The controls for the "delete" operation.
	 */
	protected function controls_for_operation_delete(WdOperation $operation)
	{
		return array
		(
			self::CONTROL_PERMISSION => self::PERMISSION_MANAGE,
			self::CONTROL_RECORD => true,
			self::CONTROL_OWNERSHIP => true,
			self::CONTROL_FORM => false,
			self::CONTROL_VALIDATOR => true
		);
	}

	/**
	 * Validates the "delete" operation.
	 *
	 * The operation is validated only if the operation key is defined.
	 *
	 * @param WdOperation $operation
	 */
	protected function validate_operation_delete(WdOperation $operation)
	{
		return (!empty($operation->key) || !empty($operation->params[WdOperation::KEYS]));
	}

	/**
	 * Performs the "delete" operation.
	 *
	 * @param WdOperation $operation
	 * @throws WdException
	 */
	protected function operation_delete(WdOperation $operation)
	{
		$params = &$operation->params;

		if (isset($params[WdOperation::KEYS]))
		{
			$keys = $params[WdOperation::KEYS];

			foreach ($keys as $key => $dummy)
			{
				if ($this->model->delete($key))
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

			if ($this->model->delete($key))
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

		$method_name = 'handle_block_' . $name;

		if (method_exists($this, $method_name))
		{
			array_shift($args);

			return call_user_func_array(array($this, $method_name), $args);
		}

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