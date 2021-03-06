<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once 'helpers/utils.php';
require_once 'helpers/debug.php';
require_once 'wdobject.php';

/**
 * @property WdConfigsAccessor $configs Configurations accessor.
 * @property WdConnectionsAccessor $connections Database connections accessor.
 * @property WdDatabase $db The primary database connection.
 * @property WdModelsAccessor $models Models accessor.
 * @property WdModulesAccessor $modules Modules accessor.
 * @property WdSession $session User's session. Injected by the WdSession class.
 * @property WdVarsAccessor $vars Persistant variables accessor.
 * @property array $config The "core" configuration.
 */
class WdCore extends WdObject
{
	const VERSION = '0.10.0-dev';

	/**
	 * @var boolean true if core is running, false otherwise.
	 */
	static public $is_running = false;

	/**
	 * Echos the exception and kills PHP.
	 *
	 * @param Exception $exception
	 */
	static public function exception_handler(Exception $exception)
	{
		exit($exception);
	}

	static protected $autoload = array();
	static protected $classes_aliases = array();

	/**
	 * Loads the file defining the specified class.
	 *
	 * The 'autoload' config property is used to define an array of 'class_name => file_path' pairs
	 * used to find the file required by the class.
	 *
	 * Class alias
	 * -----------
	 *
	 * Using the 'classes aliases' config property, one can specify aliases for classes. The
	 * 'classes aliases' config property is an array where the key is the alias name and the value
	 * the class name.
	 *
	 * When needed, a final class is created for the alias by extending the real class. The class
	 * is made final so that it cannot be subclassed.
	 *
	 * Class initializer
	 * -----------------
	 *
	 * If the loaded class defines the '__static_construct' method, the method is invoked to
	 * initialize the class.
	 *
	 * @param string $name The name of the class
	 * @return boolean Whether or not the required file could be found.
	 */
	static private function autoload_handler($name)
	{
		if ($name == 'parent')
		{
			return false;
		}

		$list = self::$autoload;

		if (isset($list[$name]))
		{
			require_once $list[$name];

			if (method_exists($name, '__static_construct'))
			{
				call_user_func(array($name, '__static_construct'));
			}

			return true;
		}

		$list = self::$classes_aliases;

		if (isset($list[$name]))
		{
			class_alias($list[$name], $name);

			return true;
		}

		return false;
	}

	/**
	 * Constructor.
	 *
	 * @param array $tags
	 */
	public function __construct(array $tags=array())
	{
		global $core;

		$core = $this;

		$path = dirname(__FILE__);

		$tags = wd_array_merge_recursive
		(
			array
			(
				'paths' => array
				(
					'config' => array($path),
					'i18n' => array($path)
				)
			),

			$tags
		);

		$class = get_class($this);

		spl_autoload_register(array($class, 'autoload_handler'));
		set_exception_handler(array($class, 'exception_handler'));

		# the order is important, there's magic involved.

		$this->configs->add($tags['paths']['config']);

		$config = $this->config;

		set_error_handler(array('WdDebug', 'error_handler'));

		WdI18n::$load_paths = array_merge(WdI18n::$load_paths, $tags['paths']['i18n']);

		if ($config['cache configs'])
		{
			$this->configs->cache_fused_fragments = true;
			$this->configs->cache_repository = $config['repository.cache'] . '/core';
		}

		if (get_magic_quotes_gpc())
		{
			wd_kill_magic_quotes();
		}
	}

	/**
	 * Returns modules accessor.
	 *
	 * @return WdModulesAccessor The modules accessor.
	 */
	protected function __get_modules()
	{
		return new WdModulesAccessor($this);
	}

	/**
	 * Returns models accessor.
	 *
	 * @return WdModelsAccessor The models accessor.
	 */
	protected function __get_models()
	{
		return new WdModelsAccessor($this);
	}

	/**
	 * Returns the non-volatile variables accessor.
	 *
	 * @return WdVarsAccessor The non-volatie variables accessor.
	 */
	protected function __get_vars()
	{
		return new WdVarsAccessor($this->config['repository.vars']);
	}

	/**
	 * Returns the connections accessor.
	 *
	 * @return WdConnectionsAccessor
	 */
	protected function __get_connections()
	{
		return new WdConnectionsAccessor($this->config['connections']);
	}

	/**
	 * Getter for the "primary" database connection.
	 *
	 * @return WdDatabase
	 */
	protected function __get_db()
	{
		return $this->connections['primary'];
	}

	/**
	 * Returns the configs accessor.
	 *
	 * @return WdConfigsAccessor
	 */
	protected function __get_configs()
	{
		return new WdConfigsAccessor();
	}

	/**
	 * Returns the code configuration.
	 *
	 * @return array
	 */
	protected function __get_config()
	{
		$config = $this->configs['core'];

		self::$autoload = $config['autoload'];
		self::$classes_aliases = $config['classes aliases'];

		$this->configs->constructors += $config['config constructors'];

		return $config;
	}

	/**
	 * Run the core object.
	 *
	 * Running the core object implies running startup modules, decoding operation, dispatching
	 * operation.
	 */
	public function run()
	{
		self::$is_running = true;

//		wd_log_time('read modules start');
		$this->modules->autorun = true;
//		wd_log_time('read modules finish');

//		wd_log_time('run modules start');
		$this->run_modules();
//		wd_log_time('run modules finish');

		$this->run_context();

//		wd_log_time('run operation start');
		$this->run_operation($_SERVER['REQUEST_URI'], $_POST + $_GET);
//		wd_log_time('run operation start');
	}

	/**
	 * Run the enabled modules.
	 *
	 * Before the modules are actually ran, their index is used to alter the I18n load paths, the
	 * config paths and the core's `autoload` and `classes aliases` config properties.
	 */
	protected function run_modules()
	{
		$index = $this->modules->index;

		WdI18n::$load_paths = array_merge(WdI18n::$load_paths, $index['catalogs']);

		$this->configs->add($index['configs'], 5);

		if ($index['autoload'])
		{
			self::$autoload += $index['autoload'];
		}

		if ($index['classes aliases'])
		{
			self::$classes_aliases += $index['classes aliases'];
		}

		if ($index['config constructors'])
		{
			$this->configs->constructors += $index['config constructors'];
		}

		$this->modules->run();
	}

	/**
	 * One can override this method to provide a context for the application.
	 */
	protected function run_context()
	{

	}

	/**
	 * Contextualize the API string.
	 *
	 * One can override the method to modify the API string in order to provide a context for the
	 * operation e.g. prefixing the API string with a site path in order to identify the site
	 * which the operation is intended for.
	 *
	 * @return string The contextualize API string.
	 */
	public function contextualize_api_string($string)
	{
		return $string;
	}

	/**
	 * Decontextualize the API string.
	 *
	 * @return string The decontextualized API string.
	 */
	public function decontextualize_api_string($string)
	{
		return $string;
	}

	/**
	 * Dispatch the operation associated with the current request, if any.
	 */
	protected function run_operation($uri, array $params)
	{
		$operation = WdOperation::decode($uri, $params);

		if (!$operation)
		{
			return;
		}

		$operation->__invoke();

		return $operation;
	}
}

if (!function_exists('class_alias'))
{
	function class_alias($original, $alias)
	{
		eval('final class ' . $alias . ' extends ' . $original . ' {}');

		return true;
	}
}

/**
 * Accessor class for the modules of the framework.
 */
class WdModulesAccessor extends WdObject implements ArrayAccess
{
	/**
	 * @var WdCore Core object, better than using globals.
	 */
	protected $core;

	/**
	 * @var boolean If true, loaded module are run when loaded for the first time.
	 */
	public $autorun = false;

	/**
	 * @var array The descriptors for the modules.
	 */
	public $descriptors = array();

	/**
	 * @var array Loaded modules.
	 */
	private $modules = array();

	/**
	 * The index for the available modules is created with the accessor object.
	 */
	public function __construct(WdCore $core)
	{
		$this->core = $core;
		$this->index;
	}

	/**
	 * Used to enable or disable a module using the specified offset as the module's id.
	 *
	 * The module is enabled or disabled by modifying the value of the T_DISABLED key of the
	 * module's descriptor.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($id, $enable)
	{
		if (empty($this->descriptors[$id]))
		{
			return;
		}

		$this->descriptors[$id][WdModule::T_DISABLED] = empty($enable);
	}

	/**
	 * Checks the availability of a module.
	 *
	 * A module is considered available when its descriptor is defined, and the T_DISABLED tag of
	 * its descriptor is empty.
	 *
	 * @param string $id The module's id.
	 * @return boolean Whether or not the module is available.
	 */
	public function offsetExists($id)
	{
		$descriptors = $this->core->modules->descriptors;

		if (empty($descriptors[$id]) || !empty($descriptors[$id][WdModule::T_DISABLED]))
		{
			return false;
		}

		return true;
	}

	/**
	 * Disables a module by setting the T_DISABLED key of its descriptor to TRUE.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($id)
	{
		if (empty($this->descriptors[$id]))
		{
			return;
		}

		$this->descriptors[$id][WdModule::T_DISABLED] = true;
	}

	/**
	 * Gets a module object.
	 *
	 * If the `autorun` property is TRUE, the `run()` method of the module is invoked upon its
	 * first loading.
	 *
	 * @param string $id The module's id.
	 * @throws WdException If the module's descriptor is not defined, or the module is disabled.
	 * @return WdModule The module object.
	 */
	public function offsetGet($id)
	{
		if (isset($this->modules[$id]))
		{
			return $this->modules[$id];
		}

		$descriptors = $this->descriptors;

		if (empty($descriptors[$id]))
		{
			throw new WdException
			(
				'The module %id does not exists ! (available modules are: :list)', array
				(
					'%id' => $id,
					':list' => implode(', ', array_keys($descriptors))
				),

				404
			);
		}

		$descriptor = $descriptors[$id];

		if (!empty($descriptor[WdModule::T_DISABLED]))
		{
			throw new WdException
			(
				'The module %id is disabled', array('%id' => $id), 404
			);
		}

		$class = strtr($id, '.', '_') . '_WdModule';

		#
		# Because we rely on class autoloading, we need to check whether the class
		# has been defined or not.
		#

		if (!class_exists($class, true))
		{
			throw new WdException
			(
				'Missing class %class for module %id', array
				(
					'%class' => $class,
					'%id' => $id
				)
			);
		}

		$this->modules[$id] = $module = new $class($descriptors[$id]);

		if ($this->autorun)
		{
			$module->run();
		}

		return $module;
	}

	/**
	 * Build the index for the available modules, specified using the 'modules' core's config key.
	 *
	 * The method not only indexes modules it also alters the I18n load paths, adds the modules'
	 * configuration paths to the configuration and alters the core's configuration for autoloads
	 * and more.
	 */
	protected function index()
	{
		$config = $this->core->config;
		$paths = $config['modules'];

		if (!$paths)
		{
			return;
		}

//		wd_log_time('cache modules start');

		if ($config['cache modules'])
		{
			$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_REPOSITORY => $config['repository.cache'] . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);

			$index = $cache->load('modules_' . md5(implode($paths)), array($this, 'index_construct'));
		}
		else
		{
			$index = $this->index_construct();
		}

//		wd_log_time('cache modules done');

		$this->descriptors = $index['descriptors'];

		return $index;
	}

	protected function __get_index()
	{
		return $this->index();
	}

	/**
	 * Construct the index for the modules.
	 *
	 * @return array
	 */
	public function index_construct()
	{
		$index = array
		(
			'descriptors' => array(),
			'catalogs' => array(),
			'configs' => array(),

			'autoload' => array(),
			'classes aliases' => array(),
			'config constructors' => array()
		);

		foreach ($this->core->config['modules'] as $root)
		{
			try
			{
				$dir = new DirectoryIterator($root);
			}
			catch(Exception $e)
			{
				throw new WdException
				(
					'Unable to open directory %root', array
					(
						'%root' => $root
					)
				);
			}

			foreach ($dir as $file)
			{
				if ($file->isDot() || !$file->isDir())
				{
					continue;
				}

				$module_id = $file->getFilename();

				$module_path = $root . DIRECTORY_SEPARATOR . $module_id;
				$read = $this->index_module($module_id, $module_path . DIRECTORY_SEPARATOR);

				if ($read)
				{
					$index['descriptors'][$module_id] = $read['descriptor'];

					if (is_dir($module_path . '/i18n'))
					{
						$index['catalogs'][] = $module_path;
					}

					if (is_dir($module_path . '/config'))
					{
						$index['configs'][] = $module_path;

						$core_config_path = $module_path . '/config/core.php';

						if (is_file($core_config_path))
						{
							$core_config = wd_isolated_require($core_config_path, array('path' => $module_path . '/', 'root' => $module_path . '/'));

							if (isset($core_config['autoload']))
							{
								$index['autoload'] += $core_config['autoload'];
							}

							if (isset($core_config['classes aliases']))
							{
								$index['classes aliases'] += $core_config['classes aliases'];
							}

							if (isset($core_config['config constructors']))
							{
								$index['config constructors'] += $core_config['config constructors'];
							}
						}
					}

					if ($read['autoload'])
					{
						$index['autoload'] = $read['autoload'] + $index['autoload'];
					}
				}
			}
		}

		return $index;
	}

	/**
	 * Indexes a specified module by reading its descriptor and creating an array of autoload
	 * references based on the files available.
	 *
	 * The module's descriptor is altered by adding the module's path (T_PATH) and the module's
	 * identifier (T_ID).
	 *
	 * Autoload references are generated depending on the files available and the module's
	 * descriptor:
	 *
	 * If a 'hooks.php' file exists, the "<module_flat_id>_WdHooks" reference is added to the
	 * autoload array.
	 *
	 * Autoload references are also created for each model and their activerecord depending on
	 * the T_MODELS tag and the exsitance of the corresponding files.
	 *
	 * @param string $id The module's identifier
	 * @param string $path The module's directory
	 */
	protected function index_module($id, $path)
	{
		$descriptor_path = $path . 'descriptor.php';
		$descriptor = require $descriptor_path;

		if (!is_array($descriptor))
		{
			throw new WdException
			(
				'%var should be an array: %type given instead in %path', array
				(
					'%var' => 'descriptor',
					'%type' => gettype($descriptor),
					'%path' => wd_strip_root($descriptor_path)
				)
			);
		}

		$descriptor[WdModule::T_PATH] = $path;
		$descriptor[WdModule::T_ID] = $id;

		$flat_id = strtr($id, '.', '_');

		$autoload = array();

		$operations_dir = $path . 'operations' . DIRECTORY_SEPARATOR;

		if (is_dir($operations_dir))
		{
			$dir = new DirectoryIterator($operations_dir);
			$filter = new RegexIterator($dir, '#\.php$#');

			foreach ($filter as $file)
			{
				$name = $flat_id . '__' . $file->getBasename('.php') . '_WdOperation';
				$autoload[$name] = $operations_dir . $file;
			}
		}

		if (file_exists($path . 'module.php'))
		{
			$autoload[$flat_id . '_WdModule'] = $path . 'module.php';
		}

		if (file_exists($path . 'hooks.php'))
		{
			$autoload[$flat_id . '_WdHooks'] = $path . 'hooks.php';
		}

		if (isset($descriptor[WdModule::T_MODELS]))
		{
			foreach ($descriptor[WdModule::T_MODELS] as $model => $dummy)
			{
				$class_base = $flat_id . ($model == 'primary' ? '' : '_' . $model);
				$file_base = $path . $model;

				if (file_exists($file_base . '.model.php'))
				{
					$autoload[$class_base . '_WdModel'] = $file_base . '.model.php';
				}

				if (file_exists($file_base . '.ar.php'))
				{
					$autoload[$class_base . '_WdActiveRecord'] = $file_base . '.ar.php';
				}
			}
		}

		return array
		(
			'descriptor' => $descriptor,
			'autoload' => $autoload
		);
	}

	/**
	 * Run the modules having a non NULL T_STARTUP value.
	 *
	 * The modules to run are sorted using the value of the T_STARTUP tag.
	 *
	 * The T_STARTUP tag defines the priority of the module in the run sequence.
	 * The higher the value, the earlier the module is ran.
	 */
	public function run()
	{
		$list = array();

		foreach ($this->descriptors as $id => $descriptor)
		{
			if (!isset($descriptor[WdModule::T_STARTUP]) || empty($this[$id]))
			{
				continue;
			}

			$list[$id] = $descriptor[WdModule::T_STARTUP];
		}

		arsort($list);

		foreach ($list as $id => $priority)
		{
			$this[$id];
		}
	}
}

/**
 * Accessor for the models of the framework.
 */
class WdModelsAccessor implements ArrayAccess
{
	/**
	 * @var WdCore The core object. Better then using globals.
	 */
	protected $core;

	/**
	 * @var Loaded models.
	 */
	private $models = array();

	public function __construct(WdCore $core)
	{
		$this->core = $core;
	}

	public function offsetSet($offset, $value)
	{
		throw new WdException('Offset is not settable');
	}

	/**
	 * Checks if a models exists by first checking if the module it belongs to is enabled and that
	 * the module actually defines the model.
	 *
	 * @see ArrayAccess::offsetExists()
	 * @return true if the model exists and is accessible, false otherwise.
	 */
	public function offsetExists($offset)
	{
		list($module_id, $model_id) = explode('/', $offset) + array(1 => 'primary');

		if (empty($this->core->modules[$module_id]))
		{
			return false;
		}

		$descriptor = $this->core->modules->descriptors[$module_id];

		return isset($descriptor[WdModule::T_MODELS][$model_id]);
	}

	public function offsetUnset($offset)
	{
		throw new WdException('Offset is not unsettable');
	}

	/**
	 * Gets the specified model of the specified module.
	 *
	 * The pattern used to request a model is "<module_id>[/<model_id>]" where "<module_id>" is
	 * the identifier for the module and "<model_id>" is the identifier of the module's model. The
	 * _model_ part is optionnal, if it's not defined it defaults to "primary".
	 *
	 * @see ArrayAccess::offsetGet()
	 * @return WdModel The model for the specified offset.
	 */
	public function offsetGet($offset)
	{
		if (empty($this->models[$offset]))
		{
			global $core;

			list($module_id, $model_id) = explode('/', $offset) + array(1 => 'primary');

			$this->models[$offset] = $core->modules[$module_id]->model($model_id);
		}

		return $this->models[$offset];
	}
}

/**
 * Accessor for the variables stored as files in the "/repository/var" directory.
 */
class WdVarsAccessor implements ArrayAccess
{
	protected $path;

	public function __construct($path)
	{
		$this->path = $_SERVER['DOCUMENT_ROOT'] . $path . '/';
	}

	public function offsetSet($name, $value)
	{
		$this->store($name, $value);
	}

	public function offsetExists($name)
	{
		$filename = $this->get_filename($name);

		return file_exists($filename);
	}

	public function offsetUnset($name)
	{
		$filename = $this->get_filename($name);

		unlink($filename);
	}

	public function offsetGet($name)
	{
		return $this->retrieve($name);
	}

	private function get_filename($name)
	{
		return $this->path . $name;
	}

	/**
	 * Cache a variable in the repository.
	 *
	 * @param string $key The key used to identify the value. Keys are unique, so storing a second
	 * value with the same key will overwrite the previous value.
	 * @param mixed $value The value to store for the key.
	 * @param int $ttl The time to live in seconds for the stored value. If no _ttl_ is supplied
	 * (or if the _tll_ is __0__), the value will persist until it is removed from the cache
	 * manualy or otherwise fails to exist in the cache.
	 */
	public function store($key, $value, $ttl=0)
	{
		$ttl_mark = $this->get_filename($key . '.ttl');

		if ($ttl)
		{
			$future = time() + $ttl;

			touch($ttl_mark, $future, $future);
		}
		else if (file_exists($ttl_mark))
		{
			unlink($ttl_mark);
		}

		$filename = $this->get_filename($key);

		file_put_contents($filename, $value);
	}

	public function retrieve($name, $default=null)
	{
		$ttl_mark = $this->get_filename($name . '.ttl');

		if (file_exists($ttl_mark) && fileatime($ttl_mark) < time())
		{
			return $default;
		}

		$filename = $this->get_filename($name);

		if (!file_exists($filename))
		{
			return $default;
		}

		return file_get_contents($filename);
	}
}

/**
 * Connections accessor.
 */
class WdConnectionsAccessor implements ArrayAccess, IteratorAggregate
{
	private $connections;
	private $established = array();

	public function __construct(array $connections)
	{
		$this->connections = $connections;
	}

	public function offsetSet($offset, $value)
	{
		throw new WdException('Offset is not settable');
	}

	/**
	 * Checks if a connection exists.
	 *
	 * @see ArrayAccess::offsetExists()
	 */
	public function offsetExists($id)
	{
		return $this->connections[$id];
	}

	public function offsetUnset($offset)
	{
		throw new WdException('Offset is not unsettable');
	}

	/**
	 * Gets a connection to the specified database.
	 *
	 * If the connection has not been established yet, it is created on the fly.
	 *
	 * Several connections may be defined.
	 *
	 * @see ArrayAccess::offsetGet()
	 * @param $id The name of the connection to get.
	 * @return WdDatabase
	 */
	public function offsetGet($id)
	{
		if (isset($this->established[$id]))
		{
			return $this->established[$id];
		}

		if (empty($this->connections[$id]))
		{
			throw new WdException('The connection %id is not defined', array('%id' => $id));
		}

		#
		# default values for the connection
		#

		$options = $this->connections[$id] + array
		(
			'dsn' => null,
			'username' => 'root',
			'password' => null,
			'options' => array
			(
				WdDatabase::T_ID => $id
			)
		);

		#
		# we catch connection exceptions and rethrow them in order to avoid displaying sensible
		# information such as the username or password.
		#

		try
		{
			$this->established[$id] = $connection = new WdDatabase($options['dsn'], $options['username'], $options['password'], $options['options']);
		}
		catch (PDOException $e)
		{
			throw new WdException($e->getMessage());
		}

		return $connection;
	}

	/**
	 * @see IteratorAggregate::getIterator()
	 * @return Traversable
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->established);
	}
}

class WdConfigsAccessor implements ArrayAccess
{
	protected $paths = array();
	protected $configs = array();

	public $cache_fused_fragments = false;
	public $cache_repository = '/repository/cache/core/';

	public $constructors = array
	(
		'core' => array('merge_recursive', 'core'),
		'debug' => array('merge_recursive', 'debug'),
		'i18n' => array('merge', 'i18n')
	);

	public function offsetSet($offset, $value)
	{
		throw new WdException('Offset is not settable');
	}

	public function offsetExists($id)
	{
		throw new WdException('Not implemented');
	}

	public function offsetUnset($offset)
	{
		throw new WdException('Offset is not unsettable');
	}

	/**
	 * Gets a connection to the specified database.
	 *
	 * If the connection has not been established yet, it is created on the fly.
	 *
	 * Several connections may be defined.
	 *
	 * @see ArrayAccess::offsetGet()
	 * @param string $id The name of the connection to get.
	 * @return WdDatabase
	 */
	public function offsetGet($id)
	{
		if (isset($this->configs[$id]))
		{
			return $this->configs[$id];
		}

		if (empty($this->constructors[$id]))
		{
			throw new WdException('There is not constructor defined to build the %id config.', array('%id' => $id));
		}

		list($constructor, $from) = $this->constructors[$id];

		return $this->fuse($id, $constructor, $from);
	}

	public function add($path, $weight=0)
	{
		$this->sorted_paths = null;

		if (is_array($path))
		{
			$combined = array_combine($path, array_fill(0, count($path), $weight));

			$this->paths += $combined;

			return;
		}

		$this->paths[$path] = $weight;
	}

	protected $sorted_paths;

	/**
	 * Sorts paths by weight while preserving their odrer.
	 *
	 * Because PHP's sorting algorithm doesn't respect the order in which entries are added,
	 * we need to create a temporary table to sort them.
	 *
	 * @return array Sorted paths by weight, from heavier to lighter.
	 */
	protected function get_sorted_paths()
	{
		$by_weight = array();

		foreach ($this->paths as $path => $weight)
		{
			$by_weight[$weight][] = $path;
		}

		arsort($by_weight);

		return $this->sorted_paths = call_user_func_array('array_merge', array_values($by_weight));
	}

	static private $require_cache = array();

	static private function isolated_require($__file__, $path)
	{
		if (isset(self::$require_cache[$__file__]))
		{
			return self::$require_cache[$__file__];
		}

		$root = $path; // COMPAT-20110108

		return self::$require_cache[$__file__] = require $__file__;
	}

	static private function get($name, $paths)
	{
		foreach ($paths as $path)
		{
			$path = $path . '/';
			$file = $path . 'config/' . $name . '.php';

			if (!file_exists($file))
			{
				continue;
			}

			$fragments[$path] = self::isolated_require($file, $path);
		}

		return $fragments;
	}

	static private $cache;

	public function fuse($name, $constructor, $from=null)
	{
		if (isset($this->configs[$name]))
		{
			return $this->configs[$name];
		}

		if (!$from)
		{
			$from = $name;
		}

		$args = array($from, $constructor);

//		wd_log_time("construct config '$name' - start");

		if ($this->cache_fused_fragments)
		{
			$cache = self::$cache ? self::$cache : self::$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_REPOSITORY => $this->cache_repository,
					WdFileCache::T_SERIALIZE => true
				)
			);

			$rc = $cache->load('config_' . wd_normalize($name, '_'), array($this, 'fuse_constructor'), $args);
		}
		else
		{
			$rc = $this->fuse_constructor($args);
		}

		$this->configs[$name] = $rc;

//		wd_log_time("construct config '$name' - finish");

		return $rc;
	}

	public function fuse_constructor(array $userdata)
	{
		list($name, $constructor) = $userdata;

		$fragments = self::get($name, $this->sorted_paths ? $this->sorted_paths : $this->get_sorted_paths());

		if ($constructor == 'merge')
		{
			$rc = call_user_func_array('array_merge', $fragments);
		}
		else if ($constructor == 'merge_recursive')
		{
			$rc = call_user_func_array('wd_array_merge_recursive', $fragments);
		}
		else
		{
			$rc = call_user_func($constructor, $fragments);
		}

		return $rc;
	}
}