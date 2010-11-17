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

#
# includes
#

require_once 'utils.php';
require_once 'wdobject.php';

#
#
#

$stats = array();

class WdCore extends WdObject
{
	const VERSION = '0.8.2-dev';

	public $locale;
	public $descriptors = array();

	/**
	 * @var array Configuration for the class
	 */

	static public $config = array();

	static public function getConfig($key, $default=null)
	{
		return isset(self::$config[$key]) ? self::$config[$key] : $default;
	}

	#
	#
	#

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

		WdLocale::addPath($tags['paths']['i18n']);

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

	static private function autoload_handler($name)
	{
		if ($name == 'parent')
		{
			return false;
		}

		#
		# the autoload list is made for `classname => required file` pairs
		#

		$list = self::$config['autoload'];

		if (isset($list[$name]))
		{
			require_once $list[$name];

			#
			# static construct
			#

			if (method_exists($name, '__static_construct'))
			{
				call_user_func(array($name, '__static_construct'));
			}

			return true;
		}

		#
		# try aliases
		#

		$list = self::$config['classes aliases'];

		if (isset($list[$name]))
		{
			$original = $list[$name];

			eval('final class ' . $name . ' extends ' . $original . ' {}');

			return true;
		}

		return false;
	}

	/*
	**

	MODULES

	**
	*/

	public function read_modules()
	{
		if (empty(self::$config['modules']))
		{
			return;
		}

		#
		# the cache object is created even if modules are not cached, allowing
		# subclasses to use the object, to clear the cache for example
		#

//		wd_log_time('cache modules start');

		if (self::$config['cache modules'])
		{
			$cache = new WdFileCache
			(
				array
				(
					WdFileCache::T_REPOSITORY => self::getConfig('repository.cache') . '/core',
					WdFileCache::T_SERIALIZE => true
				)
			);

			$aggregate = $cache->load('modules', array($this, 'read_modules_construct'));
		}
		else
		{
			$aggregate = $this->read_modules_construct();
		}

//		wd_log_time('cache modules done');

		$this->descriptors = $aggregate['descriptors'];

		WdLocale::addPath($aggregate['catalogs']);
		WdConfig::add($aggregate['configs'], 5);

//		wd_log_time('path added');

		#
		# reload config with modules fragments and add collected autoloads
		#

		$config = WdConfig::get_constructed('core', 'merge_recursive');

//		wd_log_time('get constructed');

		self::$config = wd_array_merge_recursive($config, self::$config);

		self::$config['autoload'] =  $aggregate['autoload'] + self::$config['autoload'];

//		wd_log_time('merge done');
	}

	/**
	 * The constructor for the modules cache
	 *
	 * @return array
	 */

	public function read_modules_construct()
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
				$read = $this->get_module_infos($file, $module_root . DIRECTORY_SEPARATOR);

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

	protected function get_module_infos($module_id, $module_root)
	{
		$descriptor_path = $module_root . 'descriptor.php';
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

		#
		# add the module's root to the descriptor
		#

		$descriptor[WdModule::T_ROOT] = $module_root;
		$descriptor[WdModule::T_ID] = $module_id;

		#
		# autoloads for the module
		#

		$flat_module_id = strtr($module_id, '.', '_');

		$autoload = array
		(
			$flat_module_id . '_WdModule' => $module_root . 'module.php'
		);

		$autoload_root = $module_root . 'autoload' . DIRECTORY_SEPARATOR;

		if (is_dir($autoload_root))
		{
			$dh = opendir($autoload_root);

			if (!$dh)
			{
				throw new WdException
				(
					'Unable to open direcotry %root', array
					(
						'%root' => $autoload_root
					)
				);
			}

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

		#
		#
		#

		if (file_exists($module_root . 'hooks.php'))
		{
			$autoload[$flat_module_id . '_WdHooks'] = $module_root . 'hooks.php';
		}

		#
		# autoloads for models and activerecords
		#

		if (isset($descriptor[WdModule::T_MODELS]))
		{
			foreach ($descriptor[WdModule::T_MODELS] as $model => $dummy)
			{
				$class_base = $flat_module_id . ($model == 'primary' ? '' : '_' . $model);
				$file_base = $module_root . $model;

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

		#
		# return what we've collected
		#

		return array
		(
			'descriptor' => $descriptor,
			'autoload' => $autoload
		);
	}

	/**
	 * Checks the availability of a module
	 * @param $id
	 * @return boolean
	 */

	public function hasModule($id)
	{
		if (empty($this->descriptors[$id]) || !empty($this->descriptors[$id][WdModule::T_DISABLED]))
		{
			return false;
		}

		return true;
	}

	/**
	 * @var array Used to cache loaded modules.
	 */

	protected $loaded_modules = array();

	/**
	 * Gets a module object
	 * @param $id
	 * @return object The module object as a WdModule instance
	 */

	public function getModule($id)
	{
		#
		# Modules are cached in the loaded_modules property. If the module is not defined
		# we need to create it.
		#

		if (empty($this->loaded_modules[$id]))
		{
			#
			# is the module described ?
			#

			if (empty($this->descriptors[$id]))
			{
				throw new WdException
				(
					'The module %id does not exists ! (available modules are: :list)', array
					(
						'%id' => $id,
						':list' => implode(', ', array_keys($this->descriptors))
					),

					404
				);
			}

			$descriptor = $this->descriptors[$id];

			#
			# if the module is disabled we throw an exception
			#

			if (!empty($descriptor[WdModule::T_DISABLED]))
			{
				throw new WdException
				(
					'The module %id is disabled', array('%id' => $id), 404
				);
			}

			#
			# The module object is saved in the cache before running the module
			# to avoid double loading which might happen if the module get himself
			# during the run process.
			#

			$this->loaded_modules[$id] = $module = $this->loadModule($id, $descriptor);

			#
			# if the core is running, we start the module
			#

			if (self::$is_running)
			{
				if (method_exists($module, 'startup'))
				{
					WdDebug::trigger
					(
						'Module %module still defines the startup() method instead of the run() method', array
						(
							'%module' => (string) $module
						)
					);
				}

				$module->run();
			}
		}

		return $this->loaded_modules[$id];
	}

	/**
	 * Load a module.
	 *
	 * Note: Because the function is used during the installation process to load module without starting them,
	 * until we find a better solution, the function needs to remain public.
	 *
	 * @param $id
	 * @return WdModule A module object
	 */

	public function loadModule($id)
	{
		if (empty($this->descriptors[$id]))
		{
			throw new WdException
			(
				'The module %id does not exists ! (available modules are: :list)', array
				(
					'%id' => $id,
					':list' => implode(', ', array_keys($this->descriptors))
				)
			);
		}

		$descriptor = $this->descriptors[$id];

		#
		# because we rely on class autoloading, we need to check whether the class
		# has been defined or not
		#

		$class = strtr($id, '.', '_') . '_WdModule';

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

		return new $class($descriptor);
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

	protected function runModules()
	{
		#
		# list module ids by their T_STARTUP priority
		#

		$list = array();

		foreach ($this->descriptors as $module_id => $descriptor)
		{
			if (!isset($descriptor[WdModule::T_STARTUP]))
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

		foreach ($list as $m_id => $priority)
		{
			#
			# discard disabled modules
			#

			if (!$this->hasModule($m_id))
			{
				continue;
			}

			#
			# note: we don't have to start the module ourselves, this is
			# done automatically when the module is loaded with the @getModule() method.
			# Thus we only need to _get_ the module.
			#

//			wd_log_time(" run module $m_id - start");

			$this->getModule($m_id);

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
	 * @return object The connection as a WdDatabase object.
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
				$this->connections[$name] = new WdDatabase($params['dsn'], $params['username'], $params['password'], $params['options']);
			}
			catch (PDOException $e)
			{
				throw new WdException($e->getMessage());
			}
		}

		return $this->connections[$name];
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
		# `is_running` is used by getModule() to automatically start module as they are loaded
		#

		self::$is_running = true;

		#
		# load and run modules
		#

//		wd_log_time('read modules start');
		$this->read_modules();
//		wd_log_time('read modules finish');

		#
		#
		#

//		wd_log_time('run modules start');
		$this->runModules();
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

			$this->models[$offset] = $core->getModule($module_id)->model($model_id);
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
					WdFileCache::T_REPOSITORY => WdCore::getConfig('repository.cache') . '/core',
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