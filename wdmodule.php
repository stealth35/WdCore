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
 * @property WdModel $model The primary model of the module.
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
	 * PERMISSIONS:
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

	const OPERATION_SAVE = 'save';
	const OPERATION_DELETE = 'delete';

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

	protected function __volatile_get_id()
	{
		return $this->id;
	}

	/**
	 * Getter for the $flat_id magic property.
	 *
	 * @return string The _flat_ version of the module id.
	 */
	protected function __get_flat_id()
	{
		return strtr($this->id, '.', '_');
	}

	/**
	 * Getter for the $model magic property.
	 *
	 * @return WdModel The _primary_ model for the module.
	 */
	protected function __get_model()
	{
		return $this->model();
	}

	/**
	 * Returns the module title, translated to the current language.
	 *
	 * @return string
	 */
	protected function __get_title()
	{
		$default = isset($this->tags[WdModule::T_TITLE]) ? $this->tags[WdModule::T_TITLE] : 'Undefined';

		return t($this->flat_id, array(), array('scope' => array('module', 'title'), 'default' => $default));
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

	/**
	 * Get a block.
	 *
	 * @param $name The name of the block to get.
	 * @return mixed Depends on the implementation. Should return a string or a stringifyable object.
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