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

	public function select($expression)
	{
		$this->select = $expression;

		return $this;
	}

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

					$c .= ' AND `' . $column . '` IN(' . $joined . ')';
				}
				else
				{
					$c .= ' AND `' . $column . '` = ?';
					$conditions_args[] = $arg;
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

	public function order($order)
	{
		$this->order = $order;

		return $this;
	}

	public function group($group)
	{
		$this->group = $group;

		return $this;
	}

	public function offset($offset)
	{
		$this->offset = (int) $offset;

		return $this;
	}

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