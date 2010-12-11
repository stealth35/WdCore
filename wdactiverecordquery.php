<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdActiveRecordQuery extends WdObject implements Iterator
{
	protected $model;

	protected $select;
	protected $join;

	protected $conditions = array();
	protected $conditions_args = array();

	protected $group;
	protected $order;

	protected $offset;
	protected $limit;

	public function __construct($model)
	{
		$this->model = $model;
	}

	/**
	 * @return WdActiveRecordQuery
	 */

	public function select($expression)
	{
		$this->select = $expression;

		return $this;
	}

	/**
	 * @return WdActiveRecordQuery
	 */

	public function joins($expression)
	{
		global $core;

		if ($expression{0} == ':')
		{
			$model = $core->models[substr($expression, 1)];

			$expression = $model->resolve_statement('INNER JOIN {self} AS {alias} USING(`{primary}`)');
		}

		$this->join .= ' ' . $expression;

		return $this;
	}

	/**
	 * Add conditions to the SQL statement.
	 *
	 * Conditions can either be specified as string or array.
	 *
	 * 1. Pure string conditions
	 *
	 * If you'de like to add conditions to your statement, you could just specify them in there,
	 * just like `$model->where('order_count = 2');`. This will find all the entries, where the
	 * `order_count` field's value is 2.
	 *
	 * 2. Array conditions
	 *
	 * Now what if that number could vary, say as an argument from somewhere, or perhaps from the
	 * user’s level status somewhere? The find then becomes something like:
	 *
	 * `$model->where('order_count = ?', 2);`
	 *
	 * or
	 *
	 * `$model->where(array('order_count' => 2));`
	 *
	 * Or if you want to specify two conditions, you can do it like:
	 *
	 * `$model->where('order_count = ? AND locked = ?', 2, false);`
	 *
	 * or
	 *
	 * `$model->where(array('order_count' => 2, 'locked' => false));`
	 *
	 * Or if you want to specify subset conditions:
	 *
	 * `$model->where(array('order_id' => array(123, 456, 789)));`
	 *
	 * This will return the orders with the `order_id` 123, 456 or 789.
	 *
	 * 3. Modifiers
	 *
	 * When using the "identifier" => "value" notation, you can switch the comparison method by
	 * prefixing the identifier with a bang "!"
	 *
	 * `$model->where(array('!order_id' => array(123, 456, 789)));`
	 *
	 * This will return the orders with the `order_id` different than 123, 456 and 789.
	 *
	 * `$model->where(array('!order_count' => 2);`
	 *
	 * This will return the orders with the `order_count` different than 2.
	 *
	 * @param mixed $conditions
	 * @param mixed $conditions_args
	 * @return WdActiveRecordQuery
	 */

	public function where($conditions, $conditions_args=null)
	{
		global $core;

		if (!$conditions)
		{
			return $this;
		}

		if ($conditions_args !== null && !is_array($conditions_args))
		{
			$conditions_args = func_get_args();
			array_shift($conditions_args);
		}

		if (is_array($conditions))
		{
			$c = '';
			$conditions_args = array();

			foreach ($conditions as $column => $arg)
			{
				if (is_array($arg))
				{
					$joined = '';

					foreach ($arg as $value)
					{
						$joined .= ',' . (is_numeric($value) ? $value : $this->model->quote($value));
					}

					$joined = substr($joined, 1);

					$c .= ' AND `' . ($column{0} == '!' ? substr($column, 1) . '` NOT' : $column . '`') . ' IN(' . $joined . ')';
				}
				else
				{
					$conditions_args[] = $arg;

					$c .= ' AND `' . ($column{0} == '!' ? substr($column, 1) . '` !' : $column . '` ') . '= ?';
				}
			}

			$conditions = substr($c, 5);
		}

		$this->conditions[] = '(' . $conditions . ')';

		if ($conditions_args)
		{
			$this->conditions_args = array_merge($this->conditions_args, $conditions_args);
		}

		return $this;
	}

	/**
	 * @return WdActiveRecordQuery
	 */

	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	/**
	 * @return WdActiveRecordQuery
	 */

	public function group($group)
	{
		$this->group = $group;

		return $this;
	}

	/**
	 * @return WdActiveRecordQuery
	 */

	public function offset($offset)
	{
		$this->offset = (int) $offset;

		return $this;
	}

	/**
	 * Apply the limit and/or offset to the SQL fired.
	 *
	 * You can use the limit to specify the number of records to be retrieved, ad use the offset to
	 * specifythe number of records to skip before starting to return records:
	 *
	 * `$model->limit(10);`
	 *
	 * Will return a maximum of 10 clients and because ti specifies no offset it will return the
	 * first 10 in the table.
	 *
	 * `$model->limit(5, 10);`
	 *
	 * Will return a maximum of 10 clients beginning with the 5th.
	 *
	 * @param unknown_type $limit
	 */

	public function limit($limit)
	{
		$offset = null;

		if (func_num_args() == 2)
		{
			$offset = $limit;
			$limit = func_get_arg(1);
		}

		$this->offset = (int) $offset;
		$this->limit = (int) $limit;

		return $this;
	}

	protected function build()
	{
		$query = '';

		if ($this->join)
		{
			$query .= ' ' . $this->join;
		}

		if ($this->conditions)
		{
			$query .= ' WHERE ' . implode(' AND ', $this->conditions);
		}

		if ($this->group)
		{
			$query .= ' GROUP BY ' . $this->group;
		}

		if ($this->order)
		{
			$query .= ' ORDER BY ' . $this->order;
		}

		if ($this->offset && $this->limit)
		{
			$query .= " LIMIT $this->offset, $this->limit";
		}
		else if ($this->offset)
		{
			$query .= " LIMIT $this->offset, 18446744073709551615";
		}
		else if ($this->limit)
		{
			$query .= " LIMIT $this->limit";
		}

//		var_dump($query);

		return $query;
	}

	/**
	 *
	 *  @return WdDatabaseStatement
	 */

	public function query()
	{
		$query = 'SELECT ';

		if ($this->select)
		{
			$query .= $this->select;
		}
		else
		{
			$query .= '*';
		}

		$query .= ' FROM {self_and_related}' . $this->build();

//		var_dump($query);

		return $this->model->query($query, $this->conditions_args);
	}

	/*
	 * FINISHER
	 */

	private function resolve_fetch_mode()
	{
		$trace = debug_backtrace(false);

		if ($trace[1]['args'])
		{
			$args = $trace[1]['args'];
		}
		else if ($this->select)
		{
			$args = array(PDO::FETCH_ASSOC);
		}
		else if ($this->model->ar_class)
		{
			$args = array(PDO::FETCH_CLASS, $this->model->ar_class);
		}
		else
		{
			$args = array(PDO::FETCH_OBJ);
		}

		return $args;
	}

	/**
	 * Execute the query and return an array of record.
	 *
	 * @return array
	 */

	public function all()
	{
		$statement = $this->query();
		$args = $this->resolve_fetch_mode();

		return call_user_func_array(array($statement, 'fetchAll'), $args);
	}

	protected function __volatile_get_all()
	{
		return $this->all();
	}

	public function one()
	{
		$statement = $this->query();
		$args = $this->resolve_fetch_mode();

		if (count($args) == 2 && $args[0] == PDO::FETCH_CLASS)
		{
			$rc = call_user_func(array($statement, 'fetchObject'), $args[1]);

			$statement->closeCursor();

			return $rc;
		}

		return call_user_func_array(array($statement, 'fetchAndClose'), $args);
	}

	protected function __volatile_get_one()
	{
		return $this->one();
	}

	/**
	 * Execute que query and returns an array of key/value, where the key is the value of the first
	 * column and the value of the key the value of the second column.
	 */

	public function pairs()
	{
		$rows = $this->all(PDO::FETCH_NUM);

		if (!$rows)
		{
			return $rows;
		}

		$rc = array();

		foreach ($rows as $row)
		{
			$rc[$row[0]] = $row[1];
		}

		return $rc;
	}

	protected function __volatile_get_pairs()
	{
		return $this->pairs();
	}

	public function column()
	{
		$statement = $this->query();

		return call_user_func_array(array($statement, 'fetchColumnAndClose'), array());
	}

	protected function __volatile_get_column()
	{
		return $this->column();
	}

	public function exists($key=null)
	{
		if ($key !== null)
		{
			if (func_get_args() > 1)
			{
				$key = func_get_args();
			}

			$this->where(array('{primary}' => $key));
		}

		$rc = $this->model->query('SELECT `{primary}` FROM {self_and_related}' . $this->build(), $this->conditions_args)->fetchAll(PDO::FETCH_COLUMN);

		if (is_array($key))
		{
			if ($rc)
			{
				$rc = array_combine($rc, array_fill(0, count($rc), true)) + array_combine($key, array_fill(0, count($key), false));
			}
		}
		else
		{
			$rc = !empty($rc);
		}

		return $rc;
	}

	protected function __volatile_get_exists()
	{
		return $this->exists();
	}

	public function count($column=null)
	{
		$query = 'SELECT ';

		if ($column)
		{
			$query .= "`$column`, COUNT(`$column`)";

			$this->group($column);
		}
		else
		{
			$query .= 'COUNT(*)';
		}

		$query .= ' AS count FROM {self_and_related}' . $this->build();

		$method = 'fetch' . ($column ? 'Pairs' : 'ColumnAndClose');

		return $this->model->query($query, $this->conditions_args)->$method();
	}

	protected function __volatile_get_count()
	{
		return $this->count();
	}


	/*
	 * MORE
	 */

	public function delete()
	{
		$query = 'DELETE FROM {self} ' . $this->build();

		return $this->model->execute($query, $this->conditions_args);
	}


	/*
	 * ITERATOR
	 */

	private $position;
	private $entries;

	function rewind()
	{
		$this->position = 0;
		$this->entries = $this->all();
	}

	function current()
	{
		return $this->entries[$this->position];
	}

	function key()
	{
		return $this->position;
	}

	function next()
	{
		++$this->position;
	}

	function valid()
	{
		return isset($this->entries[$this->position]);
	}
}