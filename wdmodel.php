<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once 'wddatabasetable.php';

define('WDMODEL_USE_APC', false);

if (!defined('WDMODEL_USE_APC'))
{
	define('WDMODEL_USE_APC', version_compare(phpversion('apc'), '3.0.17') > -1);
}

class WdModel extends WdDatabaseTable implements ArrayAccess
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

		if (empty($core->modules->descriptors[$extends][WdModule::T_MODELS]['primary']))
		{
			//wd_log('no primary for: \1', array($extends));

			return false;
		}

		$tags = $core->modules->descriptors[$extends][WdModule::T_MODELS]['primary'];

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

			$tags[self::T_NAME] = $extends->name_unprefixed;
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
	 * Override the method to handle dynamic finders.
	 *
	 * @see WdObject::__call()
	 */
	public function __call($method, $arguments)
	{
		if (preg_match('#^find_by_#', $method))
		{
			$arq = new WdActiveRecordQuery($this);

			return call_user_func_array(array($arq, $method), $arguments);
		}

		return parent::__call($method, $arguments);
	}

	/**
	 * Override the method to handle scopes.
	 */
	public function __get($property)
	{
		$callback = 'scope_' . $property;

		if (method_exists($this, $callback))
		{
			$arq = new WdActiveRecordQuery($this);

			return $this->$callback($arq);
		}

		return parent::__get($property);
	}

	/**
	 * Finds a record or a collection of records.
	 *
	 * @param mixed $key A key or an array of keys.
	 * @throws WdMissingRecordException An exception of class WdMissingRecordException is raised
	 * when the record, or one or more records of the records set, could not be found.
	 * @return WdActiveRecord|array A record or a set of records.
	 */
	public function find($key)
	{
		if (func_num_args() > 1)
		{
			$key = func_get_args();
		}

		if (is_array($key))
		{
			$records = array_combine($key, $key);
			$missing = $records;

			foreach ($records as $key)
			{
				$record = $this->retrieve($key);

				if (!$record)
				{
					continue;
				}

				$records[$key] = $record;
				unset($missing[$key]);
			}

			if ($missing)
			{
				$primary = $this->primary;
				$query_records = $this->where(array($primary => $missing))->all;

				foreach ($query_records as $record)
				{
					$key = $record->$primary;
					$records[$key] = $record;
					unset($missing[$key]);

					$this->store($record);
				}
			}

			if ($missing)
			{
				if (count($missing) > 1)
				{
					throw new WdMissingRecordException('Records %keys do not exists in model %model.', array('%model' => $this->name_unprefixed, '%keys' => implode(', ', array_keys($missing))), 404);
				}
				else
				{
					$key = array_keys($missing);
					$key = array_shift($key);

					throw new WdMissingRecordException('Record %key does not exists in model %model.', array('%model' => $this->name_unprefixed, '%key' => $key), 404);
				}
			}

			return $records;
		}

		$record = $this->retrieve($key);

		if ($record === null)
		{
			$record = $this->where(array($this->primary => $key))->one;

			if (!$record)
			{
				throw new WdMissingRecordException('Record %key does not exists in model %model.', array('%model' => $this->name_unprefixed, '%key' => $key), 404);
			}

			$this->store($record);
		}

		return $record;
	}

	/**
	 * Because records are cached, we need to removed the record from the cache when it is saved,
	 * so that loading the record again returns the updated record, not the one in the cache.
	 *
	 * @see WdDatabaseTable::save($properies, $key, $options)
	 */
	public function save(array $properties, $key=null, array $options=array())
	{
		if ($key)
		{
			$this->eliminate($key);
		}

		return parent::save($properties, $key, $options);
	}

	static protected $cached_records;

	/**
	 * Stores a record in the records cache.
	 *
	 * @param WdActiveRecord $record The record to store.
	 */
	protected function store(WdActiveRecord $record)
	{
		$key = $this->create_cache_key($record->{$this->primary});

		if (!$key)
		{
			return;
		}

		self::$cached_records[$key] = $record;

		if (WDMODEL_USE_APC)
		{
			apc_store($key, $record);
		}
	}

	/**
	 * Retrieves a record from the records cache.
	 *
	 * @param int $key
	 */
	protected function retrieve($key)
	{
		$key = $this->create_cache_key($key);

		if (!$key)
		{
			return;
		}

		$record = null;

		if (isset(self::$cached_records[$key]))
		{
			$record = self::$cached_records[$key];
		}
		else if (WDMODEL_USE_APC)
		{
			$record = apc_fetch($key, $success);

			$success ? self::$cached_records[$key] = $record : $record = null;
		}

		return $record;
	}

	/**
	 * Eliminate an object from the cache.
	 *
	 * @param int $key
	 */
	protected function eliminate($key)
	{
		$key = $this->create_cache_key($key);

		if (!$key)
		{
			return;
		}

		if (WDMODEL_USE_APC)
		{
			apc_delete($key);
		}

		self::$cached_records[$key] = null;
	}

	/**
	 * Creates a unique cache key.
	 *
	 * @param int $key
	 */
	protected function create_cache_key($key)
	{
		if ($key === null)
		{
			return;
		}

		return (WDMODEL_USE_APC ? 'ar:' . $_SERVER['DOCUMENT_ROOT'] . '/' : '') . $this->connection->id . '/' . $this->name . '/' . $key;
	}

	/**
	 * Delegation hub.
	 *
	 * @return mixed
	 */
	private function defer_to_actionrecord_query()
	{
		$trace = debug_backtrace(false);
		$arq = new WdActiveRecordQuery($this);

		return call_user_func_array(array($arq, $trace[1]['function']), $trace[1]['args']);
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::joins method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function joins($expression)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::select method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function select($expression)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::where method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function where($conditions, $conditions_args=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::group method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function group($group)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::order method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function order($order)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::limit method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function limit($limit, $offset=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::exists method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function exists($key=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	protected function __volatile_get_exists()
	{
		return $this->exists();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::count method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function count($column=null)
	{
		return $this->defer_to_actionrecord_query();
	}

	protected function __volatile_get_count()
	{
		return $this->count();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::average method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function average($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::minimum method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function minimum($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::maximum method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function maximum($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::sum method.
	 *
	 * @return WdActiveRecordQuery
	 */
	public function sum($column)
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation method for the WdActiveRecordQuery::all method.
	 *
	 * @return array An array of records.
	 */
	public function all()
	{
		return $this->defer_to_actionrecord_query();
	}

	/**
	 * Delegation getter for the WdActiveRecordQuery::all getter.
	 *
	 * @return array An array of records.
	 */
	protected function __volatile_get_all()
	{
		return $this->all();
	}

	/**
	 * Scope
	 */

	public function has_scope($name)
	{
		return method_exists($this, 'scope_' . $name);
	}

	public function scope($scope_name, $scope_args=null)
	{
		$callback = 'scope_' . $scope_name;

		if (!method_exists($this, $callback))
		{
			throw new WdException('Unknown scope %scope for model %model', array('%scope' => $scope_name, '%model' => $this->name_unprefixed));
		}

		return call_user_func_array(array($this, $callback), $scope_args);
	}

	/*
	 * ArrayAcces implementation
	 */

	public function offsetSet($key, $properties)
	{
		throw new WdException('Offsets are not settable: %key !properties', array('%key' => $key, '!properties' => $properties));
    }

    /**
     * Checks if the record identified by the given key exists.
     *
     * @see ArrayAccess::offsetExists()
     * @return bool true is the record exists, false otherwise.
     */
    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    /**
     * Deletes the record specified by the given key.
     *
     * @see ArrayAccess::offsetUnset()
     * @see WdModel::delete();
     */
    public function offsetUnset($key)
    {
        $this->delete($key);
    }

    /**
     * Returns the record corresponding to the given key.
     *
     * @see ArrayAccess::offsetGet()
     * @see WdModel::find();
     */
    public function offsetGet($key)
    {
    	return $this->find($key);
    }
}

class WdMissingRecordException extends WdException
{

}