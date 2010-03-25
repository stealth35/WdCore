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

class WdModel extends WdDatabaseTable
{
	const T_CLASS = 'class';
	const T_ACTIVERECORD_CLASS = 'activerecord-class';

	static public function doesExtends($descriptor, $instanceof)
	{
		if (empty($descriptor[WdModel::T_EXTENDS]))
		{
			//wd_log('no extends in \1', array($model));

			return false;
		}

		$extends = $descriptor[WdModel::T_EXTENDS];

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

		//wd_log('try: \1', array($extends));

		return self::doesExtends($core->descriptors[$extends][WdModule::T_MODELS]['primary'], $instanceof);
	}


	protected $loadall_options = array('mode' => PDO::FETCH_OBJ);

	public function __construct($tags)
	{
		if (isset($tags[self::T_ACTIVERECORD_CLASS]))
		{
			$this->loadall_options = array('mode' => array(PDO::FETCH_CLASS, $tags[self::T_ACTIVERECORD_CLASS]/*, array($this)*/));
		}

		return parent::__construct($tags);
	}

	static protected $objects_cache;

	public function load($key)
	{
		$cache_key = is_array($key) ? implode('-', $key) : $key;

		if (empty(self::$objects_cache[$this->name][$cache_key]))
		{
			$rc = parent::load($key);

			if (!is_object($rc))
			{
				return $rc;
			}

			//wd_log('\1.\2 loaded', array($this->name, $key));

			self::$objects_cache[$this->name][$cache_key] = $rc;
		}
		/*
		else
		{
			wd_log('\1.\2 loaded from object cache: \3 !', array($this->name, $key, self::$objects_cache[$this->name][$key] ));
		}
		*/

		return self::$objects_cache[$this->name][$cache_key];
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
		if ($key)
		{
			$cache_key = is_array($key) ? implode('-', $key) : $key;

			self::$objects_cache[$this->name][$cache_key] = null;
		}

		return parent::save($properties, $key, $options);
	}
}