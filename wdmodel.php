<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

require_once 'wddatabasetable.php';

define('WDMODEL_USE_APC', false);

if (!defined('WDMODEL_USE_APC'))
{
	define('WDMODEL_USE_APC', function_exists('apc_store'));
}

class WdModel extends WdDatabaseTable
{
	const T_CLASS = 'class';
	const T_ACTIVERECORD_CLASS = 'activerecord-class';

	static public function is_extending($tags, $instanceof)
	{
		if (is_string($tags))
		{
			wd_log('is_extending is not competent with string references: \1', array($tags));

			return true;
		}

		// TODO-2010630: The method should handle submodels to, not just 'primary'

		if (empty($tags[self::T_EXTENDS]))
		{
			//wd_log('no extends in \1', array($model));

			return false;
		}

		$extends = $tags[self::T_EXTENDS];

		if ($extends == $instanceof)
		{
			//wd_log('found instance of with: \1', array($model));

			return true;
		}

		global $core;

		if (empty($core->descriptors[$extends][WdModule::T_MODELS]['primary']))
		{
			//wd_log('no primary for: \1', array($extends));

			return false;
		}

		$tags = $core->descriptors[$extends][WdModule::T_MODELS]['primary'];

		//wd_log('try: \1', array($extends));

		return self::is_extending($tags, $instanceof);
	}

	public $ar_class;
	protected $loadall_options = array('mode' => PDO::FETCH_OBJ);

	public function __construct($tags)
	{
		if (isset($tags[self::T_EXTENDS]) && empty($tags[self::T_SCHEMA]))
		{
			$extends = $tags[self::T_EXTENDS];

//			wd_log('extending a model without schema: \1', array($extends));

			$tags[self::T_NAME] = $extends->name;
			$tags[self::T_SCHEMA] = $extends->schema;
			$tags[self::T_EXTENDS] = $extends->parent;

			if (empty($tags[self::T_ACTIVERECORD_CLASS]))
			{
				$tags[self::T_ACTIVERECORD_CLASS] = $extends->ar_class;
			}
		}










		parent::__construct($tags);

		#
		# Resolve the active record class.
		#

		if ($this->parent)
		{
			$this->ar_class = $this->parent->ar_class;
		}

		if (isset($tags[self::T_ACTIVERECORD_CLASS]))
		{
			$ar_class = $tags[self::T_ACTIVERECORD_CLASS];

			/*
			if (!class_exists($ar_class, true))
			{
				throw new WdException
				(
					'Unknown class %class for active records of the %model model', array
					(
						'%class' => $ar_class,
						'%model' => get_class($this)
					)
				);
			}
			*/

			$this->ar_class = $ar_class;
		}

		if ($this->ar_class)
		{
			$this->loadall_options = array('mode' => array(PDO::FETCH_CLASS, $this->ar_class));
		}
	}

	/**
	 * Overrides the WdDatabaseTable::load() method for the cache implementation.
	 *
	 * @see $wd/wdcore/WdDatabaseTable#load($id)
	 */

	public function load($key)
	{
		$entry = $this->retrieve($key);

		if ($entry === null)
		{
			$entry = parent::load($key);

			$this->store($key, $entry);
		}

		return $entry;
	}

	/**
	 * Override to load objects instead of arrays
	 *
	 * @see support/wdcore/WdDatabaseTable#loadAll()
	 */

	public function loadAll($completion=null, array $args=array(), array $options=array())
	{
		return parent::loadAll($completion, $args, $options + $this->loadall_options);
	}

	/**
	 * Because objects are cached, we need to removed the object from the cache when it's saved, so
	 * that loading the object again returns the updated object, not the one in cache.
	 *
	 * @see $wd/wdcore/WdDatabaseTable#save($values, $id, $options)
	 */

	public function save(array $properties, $key=null, array $options=array())
	{
		$this->eliminate($key);

		return parent::save($properties, $key, $options);
	}

	static protected $cached_objects;

	/**
	 * Stores an object into the cache.
	 *
	 * Note that only WdActiveRecord instance object are actually stored.
	 *
	 * @param unknown_type $key
	 * @param unknown_type $value
	 */

	protected function store($key, $value)
	{
		if (!is_object($value) || !($value instanceof WdActiveRecord))
		{
			return;
		}

		$key = $this->createCacheKey($key);

		if (!$key)
		{
			return;
		}

		self::$cached_objects[$key] = $value;

		if (WDMODEL_USE_APC)
		{
			apc_store($key, $value);
		}
	}

	/**
	 * Retrieves an object from the cache.
	 *
	 * @param unknown_type $key
	 */

	protected function retrieve($key)
	{
		$key = $this->createCacheKey($key);

		if (!$key)
		{
			return;
		}

		$entry = null;

		if (isset(self::$cached_objects[$key]))
		{
			$entry = self::$cached_objects[$key];
		}
		else if (WDMODEL_USE_APC)
		{
			$entry = apc_fetch($key, $success);

			$success ? self::$cached_objects[$key] = $entry : $entry = null;
		}

		return $entry;
	}

	/**
	 * Eliminate an object from the cache.
	 *
	 * @param unknown_type $key
	 */

	protected function eliminate($key)
	{
		$key = $this->createCacheKey($key);

		if (!$key)
		{
			return;
		}

		if (WDMODEL_USE_APC)
		{
			apc_delete($key);
		}

		self::$cached_objects[$key] = null;
	}

	/**
	 * Creates a unique cache key.
	 *
	 * @param unknown_type $key
	 */

	protected function createCacheKey($key)
	{
		if ($key === null)
		{
			return;
		}

		if (is_array($key))
		{
			$key = implode('-', $key);
		}

		return (WDMODEL_USE_APC ? 'ar:' . $_SERVER['DOCUMENT_ROOT'] . '/' : '') . $this->connection->name . '/' . $this->name . '/' . $key;
	}





	private function defer_to_actionrecord_query()
	{
		$trace = debug_backtrace(false);
		$arr = new WdActiveRecordQuery($this);

		return call_user_func_array(array($arr, $trace[1]['function']), $trace[1]['args']);
	}

	public function joins($expression)
	{
		return $this->defer_to_actionrecord_query();
	}

	public function _select($expression)
	{
		$arr = new WdActiveRecordQuery($this);
		$args = func_get_args();

		return call_user_func_array(array($arr, 'select'), $args);
	}

	public function where($conditions, $conditions_args=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	public function order($order)
	{
		return $this->defer_to_actionrecord_query();
	}
}