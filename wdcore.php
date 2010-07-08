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

require_once 'wdlocale.php';
require_once 'wddebug.php';
require_once 'wdexception.php';
require_once 'wdmodule.php';

#
#
#

$stats = array();

class WdCore
{
	const VERSION = '0.7.16 (2010-07-08)';

	public $locale;
	public $descriptors = array();

	/**
	 * @var array Configuration for the class
	 */

	static protected $config = array();
	static protected $pending_configs = array();
	static protected $constructed_configs = array();

	static public function autoconfig(array $configs)
	{
		array_unshift($configs, self::$config);

		self::$config = call_user_func_array('wd_array_merge_recursive', $configs);
	}

	static public function getConfig($key, $default=null)
	{
		return isset(self::$config[$key]) ? self::$config[$key] : $default;
	}

	static public function getConstructedConfig($ns, $constructor=null)
	{
		if (isset(self::$constructed_configs[$ns]))
		{
			return self::$constructed_configs[$ns];
		}

//		wd_log_time("construct config '$ns' - start");

		if ($constructor == 'merge')
		{
			$constructor = array(__CLASS__, 'mergeA');
		}

		$cache = self::cache();

		$pending = isset(self::$pending_configs[$ns]) ? self::$pending_configs[$ns] : array();

		if (self::$config['cache configs'])
		{
			$rc = $cache->load($ns . '.config', $constructor, $pending);
		}
		else
		{
			$cache->delete($ns . '.config');

			$rc = call_user_func($constructor, $pending);
		}

//		wd_log_time("construct config '$ns' - finish");

		return $rc;
	}

	static public function mergeA($arrays)
	{
		return call_user_func_array('array_merge', $arrays);
	}

	#
	#
	#

	public $models;

	public function __construct()
	{
		#
		# handle magic quotes
		#

		if (/*version_compare(PHP_VERSION, '5.3', '<') &&*/ get_magic_quotes_gpc())
		{
			#
			# warn about magic quotes
			#

			if (WDCORE_VERBOSE_MAGIC_QUOTES)
			{
				wd_log('You should disable magic quotes');
			}

			#
			# bad magic quotes !
			#

			wd_kill_magic_quotes();
		}

		$class = get_class($this);

		#
		# register some functions
		#

		spl_autoload_register(array($class, 'autoload_handler'));
		set_exception_handler(array($class, 'exceptionHandler'));
		set_error_handler(array('WdDebug', 'errorHandler'));

		#
		#
		#

		WdLocale::addPath(dirname(__FILE__));

		#
		# access
		#

		$this->models = new WdCoreModelsArrayAccess();
	}

	public static function exceptionHandler($exception)
	{
		echo $exception;

		exit;
	}

	static private function autoload_handler($name)
	{
		static $lastcount;

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

			#
			# autoconfig
			#

			// TODO-20100701: autoconfig should die

			if (method_exists($name, 'autoconfig'))
			{
				//wd_log("The $name class still uses autoconfig instead of __static_construct");

				$handlers = array_flip(self::$config['autoconfig']);

				if (isset($handlers[$name]))
				{
					$config_name = $handlers[$name];

//					wd_log("the class $name has an autoconfig method for the config $config_name]");

					if (isset(self::$pending_configs[$config_name]))
					{
						//wd_log('autoload: config found for class \1', array($name));

						call_user_func(array($name, 'autoconfig'), self::$pending_configs[$config_name]);
					}
				}
			}

			return true;
		}

		#
		# try aliases
		#

		$aliases = self::$config['classes aliases'];
		//$aliases = array_change_key_case(self::$config['classes aliases']);

		if (isset($aliases[$name]))
		{
			$original = $aliases[$name];

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

	static protected $caches = array();

	static public function cache($name='core')
	{
		if (empty(self::$caches[$name]))
		{
			self::$caches[$name] = new WdFileCache
			(
				array
				(
					WdFileCache::T_REPOSITORY => self::getConfig('repository.cache') . '/' . $name,
					WdFileCache::T_SERIALIZE => true
				)
			);
		}

		return self::$caches[$name];
	}

	protected $cache;

	public function readModules()
	{
		if (empty(self::$config['modules']))
		{
			WdDebug::trigger('There is no module path defined !');

			return;
		}

		#
		# the cache object is created even if modules are not cached, allowing
		# subclasses to use the object, to clear the cache for example
		#

		$this->cache = new WdFileCache
		(
			array
			(
				WdFileCache::T_REPOSITORY => self::getConfig('repository.cache') . '/core',
				WdFileCache::T_SERIALIZE => true
			)
		);

//		wd_log_time('cache modules start');

		if (self::$config['cache modules'])
		{
			$aggregate = $this->cache->load('modules', array($this, 'readModules_construct'));
		}
		else
		{
			$this->cache->delete('modules');

			$aggregate = $this->readModules_construct();
		}

//		wd_log_time('cache modules done');

		#
		# locale
		#

		$this->descriptors = $aggregate['descriptors'];

		foreach ($aggregate['catalogs'] as $path)
		{
			WdLocale::addPath($path);
		}

		#
		# autoconfig
		#

		//wd_log('modules configs: \1', array($aggregate['configs']));

		$configs = $aggregate['configs'];

		/*
		foreach ($configs as $key => $pouic)
		{
			self::$pending_configs[$key] = isset(self::$pending_configs[$key]) ? array_merge(self::$pending_configs[$key], $pouic) : $pouic;
		}
		*/

		foreach ($configs as $key => $pouic)
		{
			self::$pending_configs[$key] = isset(self::$pending_configs[$key]) ? array_merge($pouic, self::$pending_configs[$key]) : $pouic;
		}

		/* the config is dispatched here in order to have autoload ready */

//		wd_log_time('modules dispatch config start');
		$this->dispatchConfig();
//		wd_log_time('modules dispatch config finish');

//		wd_log('<h1>CONFIGS</h1>\1', array(self::$pending_configs));


		#
		# check circular dependencies of extends
		#

		if (WDCORE_CHECK_CIRCULAR_DEPENDENCIES)
		{
			foreach ($this->descriptors as $module_id => $descriptor)
			{
				if (isset($descriptor[WdDatabaseTable::T_EXTENDS]))
				{
					$heritage = array();

					$extends = explode(WdPackageDescriptor::SEPARATOR, $module_id);

					while ($extends)
					{
						list($ext_p, $ext_m) = $extends;

						$ext_id = implode(WdPackageDescriptor::SEPARATOR, $extends);

						if (in_array($ext_id, $heritage))
						{
							throw new WdException
							(
								'Circular extends detected on %extends in %heritage', array
								(
									'%extends' => $ext_id,
									'%heritage' => implode(' -> ', $heritage)
								)
							);
						}

						$heritage[] = $ext_id;

						if (empty($this->descriptors[$ext_id][WdDatabaseTable::T_EXTENDS]))
						{
							break;
						}

						$extends = $this->descriptors[$ext_id][WdDatabaseTable::T_EXTENDS];
					}

					//wd_log('heritage: \1', implode(' -> ', $heritage));
				}
			}
		}
	}

	/**
	 * The constructor for the modules cache
	 *
	 * @return array
	 */

	public function readModules_construct()
	{
		$aggregate = array
		(
			'descriptors' => array(),
			'catalogs' => array(),
			'configs' => array
			(
				'core' => array
				(
					array()
				)
			),
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

				$read = $this->readModules_unit($file, $root . DIRECTORY_SEPARATOR . $file . DIRECTORY_SEPARATOR);

				if ($read)
				{
					$aggregate['descriptors'][$file] = $read['descriptor'];

					if ($read['i18n'])
					{
						$aggregate['catalogs'][] = $read['i18n'];
					}

					foreach ($read['configs'] as $name => $config)
					{
						#
						# WdCore config is handled separately, because we know how to merge
						# multiple configs, and because the merge is done here and the result will
						# probably be cached, we might save some precious time.
						#

						if ($name == 'core')
						{
							$aggregate['configs']['core'][0] = wd_array_merge_recursive($aggregate['configs']['core'][0], $config);

							continue;
						}

						//$aggregate['configs'][$name][] = $config;
						$aggregate['configs'][$name][$root . DIRECTORY_SEPARATOR . $file] = $config;
					}
				}
			}

			closedir($dh);

			chdir($location);
		}

		return $aggregate;
	}

	protected function readModules_unit($module_id, $module_root)
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
		# add locale catalog
		#

		$i18n = null;

		if (is_dir($module_root . 'i18n'))
		{
			$i18n = $module_root;
		}

		#
		# config
		#

		$configs = array();
		$config_root = $module_root . 'config' . DIRECTORY_SEPARATOR;

		if (is_dir($config_root))
		{
			$dh = opendir($config_root);

			if (!$dh)
			{
				throw new WdException
				(
					'Unable to open directory %root', array
					(
						'%root' => $config_root
					)
				);
			}

			while (($file = readdir($dh)) !== false)
			{
				if (substr($file, -4, 4) != '.php')
				{
					continue;
				}

				$configs[basename($file, '.php')] = self::isolatedRequire($config_root . $file, $config_root, $module_root);
				//$configs[$module_root] = self::isolatedRequire($config_root . $file, $config_root, $module_root);
			}

			closedir($dh);
		}

		#
		# autoloads for the module
		#

		$base = strtr($module_id, '.', '_');

		$autoload = array
		(
			$base . '_WdModule' => $module_root . 'module.php'
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
					$name = $base . $name;
				}

				$autoload[$name] = $autoload_root . $file;
			}

			closedir($dh);
		}

		#
		# autoloads for models and activerecords
		#

		if (isset($descriptor[WdModule::T_MODELS]))
		{
			foreach ($descriptor[WdModule::T_MODELS] as $model => $dummy)
			{
				$model_class_base = $base . ($model == 'primary' ? '' : '_' . $model);

				$autoload[$model_class_base . '_WdModel'] = $module_root . $model . '.model.php';
				$autoload[$model_class_base . '_WdActiveRecord'] = $module_root . $model . '.ar.php';
			}
		}

		if (empty($configs['core']['autoload']))
		{
			$configs['core']['autoload'] = array();
		}

		$configs['core']['autoload'] += $autoload;

		#
		# return what we've collected
		#

		return array
		(
			'descriptor' => $descriptor,
			'i18n' => $i18n,
			'configs' => $configs
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

	static protected function readConfig($root)
	{
		$parent_root = $root . DIRECTORY_SEPARATOR;
		$root = $parent_root . 'config';

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

		$root .= DIRECTORY_SEPARATOR;
		$configs = array();

		while (($file = readdir($dh)) !== false)
		{
			if (substr($file, -4, 4) != '.php')
			{
				continue;
			}

			$config = self::isolatedRequire($root . $file, $root, $parent_root);
			$config_name = basename($file, '.php');

			$configs[$config_name] = $config;
		}

		return $configs;
	}

	static protected function isolatedRequire($path, $config_root, $root)
	{
		return require $path;
	}

	static public function addConfig($root=null)
	{
		if (self::$is_running)
		{
			WdDebug::trigger('Too late to add config, core is already running.');
		}

		if (!self::$config)
		{
			$core_root = dirname(__FILE__) . DIRECTORY_SEPARATOR;
			$core_config_root = $core_root . 'config' . DIRECTORY_SEPARATOR;

			self::$config = self::isolatedRequire($core_config_root . 'core.php', $core_config_root, $core_root);

			// TODO-20100107: load the rest of the config.

			//self::addConfig();
		}

		if (!$root)
		{
			$trace = debug_backtrace();

			$root = dirname($trace[0]['file']);
		}

		$configs = self::readConfig($root);

		foreach ($configs as $name => $pouic)
		{
			self::$pending_configs[$name][$root] = $pouic;
		}
	}

	static protected function dispatchConfig()
	{
		//WdDebug::trigger('dispatchConfig: \1', array(self::$pending_configs));

		$handlers = self::$config['autoconfig'];

		foreach (self::$pending_configs as $id => $configs)
		{
			if (empty($handlers[$id]))
			{
				//throw new WdException('There is no autoconfig handler for %id', array('%id' => $id));

				continue;
			}

			$handler = $handlers[$id];

			#
			# The config is applyed to existing - _loaded_, rather - classes. If the class has not
			# yet been loaded, the configuration is differed. The classes will later be configured
			# by the `autoloadHandler` function.
			#

			if (!class_exists($handler, false))
			{
				//echo "la class $handler n'est pas chargée, la configuration est différée<br />";

//				wd_log("la class $handler n'est pas chargée, la configuration est différée");

//				wd_log_time('dispatch config for \1 delayed, finish', array($id));

				continue;
			}

//			wd_log_time('dispatch config for \1 start', array($id));

			//call_user_func_array(array($handler, 'autoconfig'), $configs);
			call_user_func(array($handler, 'autoconfig'), $configs);

//			wd_log_time('dispatch config for \1 finish', array($id));

			unset(self::$pending_configs[$id]);
		}
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

//		wd_log_time('dispatch config - start');
		self::dispatchConfig();
//		wd_log_time('dispatch config - finish');

		#
		# load and run modules
		#

//		wd_log_time('read modules start');
		$this->readModules();
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