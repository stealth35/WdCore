<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

defined('WDCORE_CHECK_CIRCULAR_DEPENDENCIES') or define('WDCORE_CHECK_CIRCULAR_DEPENDENCIES', false);
defined('WDCORE_VERBOSE_MAGIC_QUOTES') or define('WDCORE_VERBOSE_MAGIC_QUOTES', false);

require_once 'helpers/utils.php';
require_once 'helpers/debug.php';
require_once 'wdobject.php';

class WdCore extends WdObject
{
	const VERSION = '0.9.0-dev';

	static public $config = array();

	public $descriptors = array();
	public $models;

	public function __construct(array $tags=array())
	{
		$dir = dirname(__FILE__);

		$tags = wd_array_merge_recursive
		(
			array
			(
				'paths' => array
				(
					'config' => array
					(
						$dir
					),

					'i18n' => array
					(
						$dir
					)
				)
			),

			$tags
		);

		#
		# add config paths
		#

		WdConfig::add($tags['paths']['config']);

		self::$config = call_user_func_array('wd_array_merge_recursive', WdConfig::get('core'));

		#
		# register some functions
		#

		$class = get_class($this);

		spl_autoload_register(array($class, 'autoload_handler'));
		set_exception_handler(array($class, 'exception_handler'));
		set_error_handler(array('WdDebug', 'errorHandler'));

		#
		#
		#

		WdI18n::$load_paths = array_merge(WdI18n::$load_paths, $tags['paths']['i18n']);

		if (get_magic_quotes_gpc())
		{
			if (WDCORE_VERBOSE_MAGIC_QUOTES)
			{
				wd_log('You should disable magic quotes');
			}

			wd_kill_magic_quotes();
		}

		$this->models = new WdCoreModelsArrayAccess();
	}

	public static function exception_handler($exception)
	{
		die($exception);
	}

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

		$list = self::$config['autoload'];

		if (isset($list[$name]))
		{
			require_once $list[$name];

			if (method_exists($name, '__static_construct'))
			{
				call_user_func(array($name, '__static_construct'));
			}

			return true;
		}

		$list = self::$config['classes aliases'];

		if (isset($list[$name]))
		{
			eval('final class ' . $name . ' extends ' . $list[$name] . ' {}');

			return true;
		}

		return false;
	}

	protected function index_modules()
	{
		if (empty(self::$config['modules']))
		{
			return;
		}

//		wd_log_time('cache modules start');

		if (self::$config['cache modules'])
		{
			$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_REPOSITORY => self::$config['repository.cache'] . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);

			$index = $cache->load('modules', array($this, 'index_modules_construct'));
		}
		else
		{
			$index = $this->index_modules_construct();
		}

//		wd_log_time('cache modules done');

		$this->descriptors = $index['descriptors'];

		WdI18n::$load_paths = array_merge(WdI18n::$load_paths, $index['catalogs']);

		WdConfig::add($index['configs'], 5);

//		wd_log_time('path added');

		#
		# reload config with modules fragments and add collected autoloads
		#

		$config = WdConfig::get_constructed('core', 'merge_recursive');

//		wd_log_time('get constructed');

		//self::$config = wd_array_merge_recursive($config, self::$config); // this shouldn't be needed anymore since we reintroduced config weight

		self::$config = $config;
		self::$config['autoload'] = $index['autoload'] + self::$config['autoload'];

//		wd_log_time('merge done');
	}

	/**
	 * The constructor for the modules cache
	 *
	 * @return array
	 */

	public function index_modules_construct()
	{
		$aggregate = array
		(
			'descriptors' => array(),
			'catalogs' => array(),
			'configs' => array(),
			'autoload' => array()
		);

		foreach (self::$config['modules'] as $root)
		{
			$location = getcwd();

			chdir($root);

			$dh = opendir($root);

			if (!$dh)
			{
				throw new WdException
				(
					'Unable to open directory %root', array
					(
						'%root' => $root
					)
				);
			}

			while (($file = readdir($dh)) !== false)
			{
				if ($file{0} == '.' || !is_dir($file))
				{
					continue;
				}

				$module_root = $root . DIRECTORY_SEPARATOR . $file;
				$read = $this->read_module_infos($file, $module_root . DIRECTORY_SEPARATOR);

				if ($read)
				{
					$aggregate['descriptors'][$file] = $read['descriptor'];

					if (is_dir($module_root . '/i18n'))
					{
						$aggregate['catalogs'][] = $module_root;
					}

					if (is_dir($module_root . '/config'))
					{
						$aggregate['configs'][] = $module_root;
					}

					if ($read['autoload'])
					{
						$aggregate['autoload'] = $read['autoload'] + $aggregate['autoload'];
					}
				}
			}

			closedir($dh);

			chdir($location);
		}

		return $aggregate;
	}

	/**
	 * Reads the informations about the module.
	 *
	 * The function returns the descriptor for the module and an array of autoload references
	 * automatically generated based on the files available and the module's descriptor:
	 *
	 * The module's descriptor is altered by adding the module's dir (T_ROOT) and the module's
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
	 * @param string $module_id The module's identifier
	 * @param string $module_dir The module's directory
	 */

	protected function read_module_infos($module_id, $module_dir)
	{
		$descriptor_path = $module_dir . 'descriptor.php';
		$descriptor = require $descriptor_path;

		if (!is_array($descriptor))
		{
			throw new WdException
			(
				'%var should be an array: %type given instead in %file', array
				(
					'%var' => 'descriptor',
					'%type' => gettype($descriptor),
					'%file' => substr($descriptor_path, strlen($_SERVER['DOCUMENT_ROOT']) - 1)
				)
			);
		}

		$descriptor[WdModule::T_ROOT] = $module_dir;
		$descriptor[WdModule::T_ID] = $module_id;

		$flat_module_id = strtr($module_id, '.', '_');

		$autoload = array
		(
			$flat_module_id . '_WdModule' => $module_dir . 'module.php'
		);

		$autoload_root = $module_dir . 'autoload' . DIRECTORY_SEPARATOR;

		if (is_dir($autoload_root))
		{
			$dh = opendir($autoload_root);

			while (($file = readdir($dh)) !== false)
			{
				if (substr($file, -4, 4) != '.php')
				{
					continue;
				}

				$name = basename($file, '.php');

				if ($name[0] == '_')
				{
					$name = $flat_module_id . $name;
				}

				$autoload[$name] = $autoload_root . $file;
			}

			closedir($dh);
		}

		if (file_exists($module_dir . 'hooks.php'))
		{
			$autoload[$flat_module_id . '_WdHooks'] = $module_dir . 'hooks.php';
		}

		if (isset($descriptor[WdModule::T_MODELS]))
		{
			foreach ($descriptor[WdModule::T_MODELS] as $model => $dummy)
			{
				$class_base = $flat_module_id . ($model == 'primary' ? '' : '_' . $model);
				$file_base = $module_dir . $model;

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
	 * Checks the availability of a module.
	 *
	 * A module is considered available when its descriptor is defined, and the T_DISABLED tag of
	 * its descriptor is empty.
	 *
	 * @param $id
	 * @return boolean
	 */

	public function has_module($id)
	{
		$descriptors = $this->descriptors;

		if (empty($descriptors[$id]) || !empty($descriptors[$id][WdModule::T_DISABLED]))
		{
			return false;
		}

		return true;
	}

	/**
	 * @var array Array of loaded modules.
	 */

	protected $loaded_modules = array();

	/**
	 * Loads a module.
	 *
	 * Note: Because the function is used during the installation process to load module without
	 * starting them, the function needs to remain public until we find a better solution.
	 *
	 * @param $id
	 * @return WdModule The requested module object.
	 * @throws WdException When the module requested is not defined.
	 * @throws WdException When the class for the module is not defined.
	 */

	public function load_module($id)
	{
		$descriptors = $this->descriptors;

		if (empty($descriptors[$id]))
		{
			throw new WdException
			(
				'The module %id does not exists ! (available modules are: :list)', array
				(
					'%id' => $id,
					':list' => implode(', ', array_keys($descriptors))
				)
			);
		}

		$class = strtr($id, '.', '_') . '_WdModule';

		#
		# Because we rely on class autoloading, we need to check whether the class
		# has been defined or not.
		#

		if (empty(self::$config['autoload'][$class]))
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

		$this->loaded_modules[$id] = $module = new $class($descriptors[$id]);

		return $module;
	}

	/**
	 * Gets a module object.
	 *
	 * If the core is running, the 'run' method of the module is invoked upon the module's first
	 * loading.
	 *
	 * @param string $id The module's id.
	 * @throws WdException If the module's descriptor is not defined, or the module is disabled.
	 * @return WdModule The module object.
	 */

	public function module($id)
	{
		if (!empty($this->loaded_modules[$id]))
		{
			return $this->loaded_modules[$id];
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
				'The module is disabled: %id', array('%id' => $id), 404
			);
		}

		$module = $this->load_module($id);

		if (self::$is_running)
		{
			$module->run();
		}

		return $module;
	}

	/**
	 * Run the modules having a non NULL T_STARTUP value.
	 *
	 * The modules to run are sorted using the value of the T_STARTUP tag.
	 *
	 * The T_STARTUP tag defines the priority of the module in the run sequence.
	 * The higher the value, the earlier the module is ran.
	 *
	 */

	protected function run_modules()
	{
		$list = array();

		foreach ($this->descriptors as $module_id => $descriptor)
		{
			if (!isset($descriptor[WdModule::T_STARTUP]) || !$this->has_module($module_id))
			{
				continue;
			}

			$list[$module_id] = $descriptor[WdModule::T_STARTUP];
		}

		arsort($list);

		#
		# order modules in reverse order so that modules with the higher priority
		# are run first.
		#

		foreach ($list as $module_id => $priority)
		{
//			wd_log_time(" run module $m_id - start");

			$this->module($module_id);

//			wd_log_time(" run module $m_id - finish");
		}
	}

	public function get_loaded_modules_ids()
	{
		return array_keys($this->loaded_modules);
	}

	/**
	 * @var array Used to cache established database connections.
	 */

	protected $connections = array();

	/**
	 * Get a connection to a database.
	 *
	 * If the connection has not been established yet, it is created on the fly.
	 *
	 * Several connections may be defined.
	 *
	 * @param $name The name of the connection to get.
	 * @return WdDatabase The connection as a WdDatabase object.
	 */

	public function db($name='primary')
	{
		if (empty($this->connections[$name]))
		{
			if (empty(self::$config['connections'][$name]))
			{
				throw new WdException('The connection %name is not defined', array('%name' => $name));
			}

			#
			# default values for the connection
			#

			$params = self::$config['connections'][$name] + array
			(
				'dsn' => null,
				'username' => 'root',
				'password' => null,
				'options' => array
				(
					'#name' => $name
				)
			);

			#
			# we catch connection exceptions and rethrow them in order to avoid the
			# display of sensible information such as the user's password.
			#

			try
			{
				$connection = new WdDatabase($params['dsn'], $params['username'], $params['password'], $params['options']);
			}
			catch (PDOException $e)
			{
				throw new WdException($e->getMessage());
			}

//			$connection->optimize();

			$this->connections[$name] = $connection;
		}

		return $this->connections[$name];
	}

	/**
	 * Getter for the "primary" database connection.
	 *
	 * @return WdDatabase The "primary" database connection.
	 */

	protected function __get_db()
	{
		return $this->db();
	}

	/**
	 * Display information about the core and its modules.
	 *
	 * The function is called during the special operation "core.aloha".
	 *
	 */

	protected function aloha()
	{
		$modules = array_keys($this->descriptors);

		sort($modules);

		header('Content-Type: text/plain; charset=utf-8');

		echo 'WdCore version ' . self::VERSION . ' is running here with:' . PHP_EOL . PHP_EOL;
		echo implode(PHP_EOL, $modules);

		echo PHP_EOL . PHP_EOL;
		echo strip_tags(implode(PHP_EOL, WdDebug::fetchMessages('debug')));

		exit;
	}

	static protected $is_running = false;

	/**
	 * Run the core object.
	 *
	 * Running the core object implies running startup modules, decoding operation, dispatching
	 * operation.
	 *
	 */

	public function run()
	{
//		wd_log_time('run core');

		#
		# `is_running` is used by module() to automatically start module as they are loaded
		#

		self::$is_running = true;

		#
		# load and run modules
		#

//		wd_log_time('read modules start');
		$this->index_modules();
//		wd_log_time('read modules finish');

		#
		#
		#

//		wd_log_time('run modules start');
		$this->run_modules();
//		wd_log_time('run modules finish');

//		wd_log_time('core is running');

		#
		# dispatch operations
		#

		$operation = WdOperation::decode($_POST + $_GET);

		if ($operation)
		{
			#
			# check operation and destination
			#

			if ($operation->destination == 'core')
			{
				switch ($operation->name)
				{
					case 'aloha':
					{
						$this->aloha();
					}
					break;

					case 'ping':
					{
						header('Content-Type: text/plain');

						echo 'pong';

						exit;
					}
					break;

					default:
					{
						throw new WdException
						(
							'Unknown operation %operation for %destination', array
							(
								'%operation' => $operation->name,
								'%destination' => $operation->destination
							)
						);
					}
					break;
				}

				return;
			}

			$operation->dispatch();
		}
	}
}

class WdCoreModelsArrayAccess implements ArrayAccess
{
	private $models = array();

	public function offsetSet($offset, $value)
    {
    	throw new WdException('Offset is not settable');
    }

    public function offsetExists($offset)
    {
        return isset($this->models[$offset]);
    }

    public function offsetUnset($offset)
    {
        throw new WdException('Offset is not unsettable');
    }

    public function offsetGet($offset)
    {
    	if (empty($this->models[$offset]))
    	{
    		global $core;

    		list($module_id, $model_id) = explode('/', $offset) + array(1 => 'primary');

			$this->models[$offset] = $core->module($module_id)->model($model_id);
    	}

    	return $this->models[$offset];
    }
}

class WdConfig
{
	static private $pending_paths = array();

	static public function add($path, $weight=0)
	{
		if (is_array($path))
		{
			$combined = array_combine($path, array_fill(0, count($path), $weight));

			self::$pending_paths = array_merge(self::$pending_paths, $combined);

			return;
		}

		self::$pending_paths[$path] = $weight;
	}

	static private $required = array();

	static protected function isolated_require($__file__, $root)
	{
		if (empty(self::$required[$__file__]))
		{
			self::$required[$__file__] = require $__file__;
		}

		return self::$required[$__file__];
	}

	static public function get($which)
	{
		$pending = self::$pending_paths;

		#
		# because PHP's sorting algorithm doesn't respect the order in which entries are added,
		# we need to create a temporary table to sort them.
		#

		$pending_by_weight = array();

		foreach ($pending as $path => $weight)
		{
			$pending_by_weight[$weight][] = $path;
		}

		arsort($pending_by_weight);

		$pending = call_user_func_array('array_merge', array_values($pending_by_weight));

		foreach ($pending as $path)
		{
			$file = $path . '/config/' . $which . '.php';

			if (!file_exists($file))
			{
				continue;
			}

			$fragments[$path] = self::isolated_require($file, $path . '/');
		}

		return $fragments;
	}

	static private $constructed;
	static private $cache;

	static public function get_constructed($name, $constructor, $from=null)
	{
		if (isset(self::$constructed[$name]))
		{
			return self::$constructed[$name];
		}

//		wd_log_time("construct config '$name' - start");

		if (WdCore::$config['cache configs'])
		{
			$cache = self::$cache ? self::$cache : self::$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_REPOSITORY => WdCore::$config['repository.cache'] . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);

			$rc = $cache->load($name . '.config', array(__CLASS__, 'get_constructed_constructor'), array($from ? $from : $name, $constructor));
		}
		else
		{
			$rc = self::get_constructed_constructor(array($from ? $from : $name, $constructor));
		}

		self::$constructed[$name] = $rc;

//		wd_log_time("construct config '$name' - finish");

		return $rc;
	}

	static public function get_constructed_constructor(array $userdata)
	{
		list($name, $constructor) = $userdata;

		$fragments = self::get($name);

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