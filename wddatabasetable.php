<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdDatabaseTable extends WdObject
{
	const T_ALIAS = 'alias';
	const T_CONNECTION = 'connection';
	const T_EXTENDS = 'extends';
	const T_IMPLEMENTS = 'implements';
	const T_NAME = 'name';
	const T_PRIMARY = 'primary';
	const T_SCHEMA = 'schema';

	protected $connection;

	public $name;
	public $name_unprefixed;
	public $primary;

	protected $alias;
	protected $schema;
	protected $parent;
	protected $implements = array();

	protected $update_join;
	protected $select_join;

	public function __construct($tags)
	{
		foreach ($tags as $tag => $value)
		{
			switch ($tag)
			{
				case self::T_ALIAS: $this->alias = $value; break;
				case self::T_CONNECTION: $this->connection = $value; break;
				case self::T_IMPLEMENTS: $this->implements = $value; break;
				case self::T_NAME: $this->name_unprefixed = $value;	break;
				case self::T_PRIMARY: $this->primary = $value; break;
				case self::T_SCHEMA: $this->schema = $value; break;
				case self::T_EXTENDS: $this->parent = $value; break;
			}
		}

		if (!$this->connection)
		{
			throw new WdException('The %tag tag is required', array('%tag' => 'T_CONNECTION'));
		}

		$this->name = $this->connection->prefix . $this->name_unprefixed;

		#
		# alias
		#

		if (!$this->alias)
		{
			$alias = $this->name_unprefixed;

			$pos = strrpos($alias, '_');

			if ($pos !== false)
			{
				$alias = substr($alias, $pos + 1);
			}

			if (substr($alias, -3, 3) == 'ies')
			{
				$alias = substr($alias, 0, -3) . 'y';
			}
			else if (substr($alias, -1, 1) == 's')
			{
				$alias = substr($alias, 0, -1);
			}

//			wd_log('alias: \1 => \2', array($this->name_unprefixed, $alias));

			$this->alias = $alias;
		}

		#
		# if we have a parent, we need to extend our fields with our parent primary key
		#

		$parent = $this->parent;

		if ($parent)
		{
			if (empty($this->schema['fields']))
			{
				throw new WdException('schema is empty for \1', array($this->name));
			}
			else
			{
				$primary = $parent->primary;
				$primary_definition = $parent->schema['fields'][$primary];

				unset($primary_definition['serial']);

				$this->schema['fields'] = array($primary => $primary_definition) + $this->schema['fields'];
			}

			#
			# implements are inherited too
			#

			if ($parent->implements)
			{
				$this->implements = array_merge($parent->implements, $this->implements);
			}
		}

		#
		# parse definition schema to have a complete schema
		#

		$this->schema = $this->connection->parseSchema($this->schema);

		#
		# retrieve primary key
		#

		if (isset($this->schema['primary-key']))
		{
			$this->primary = $this->schema['primary-key'];
		}

		#
		# resolve inheritence and create a lovely _inner join_ string
		#

		$join = " `{$this->alias}` ";

		$parent = $this->parent;

		while ($parent)
		{
			$join .= "INNER JOIN `{$parent->name}` `{$parent->alias}` USING(`{$this->primary}`) ";

			$parent = $parent->parent;
		}

		$this->update_join = $join;

		#
		# resolve implements
		#

		if ($this->implements)
		{
			if (!is_array($this->implements))
			{
				throw new WdException('Expecting an array for T_IMPLEMENTS, given: \1', array($this->implements));
			}

//			wd_log('implements: \1', $this->implements);

			$i = 1;

			foreach ($this->implements as $implement)
			{
				if (!is_array($implement))
				{
					throw new WdException('Expecting array for implement: \1', array($implement));
				}

				$table = $implement['table'];

				if (!($table instanceof WdDatabaseTable))
				{
					throw new WdException('Implement must be an instane of WdDatabaseTable: \1', array(get_class($table)));
				}

				$name = $table->name;
				$primary = $table->primary;

				$join .= empty($implement['loose']) ? 'INNER' : 'LEFT';
				$join .= " JOIN `$name` as i$i USING(`$primary`) ";

				$i++;
			}
		}

//		wd_log('join query for %name: <code>:join</code>', array('%name' => $this->name, ':join' => $join));

		$this->select_join = $join;
	}

	protected function __volatile_get_connection()
	{
		return $this->connection;
	}

	protected function __set_connection()
	{
		throw new WdException('Connection cannot be set');
	}

	/*
	**

	INSTALL

	**
	*/

	public function install()
	{
		if (!$this->schema)
		{
			throw new WdException('Missing schema to install table %name', array('%name' => $this->name_unprefixed));
		}

		return $this->connection->createTable($this->name_unprefixed, $this->schema);
	}

	public function uninstall()
	{
		return $this->drop();
	}

	public function isInstalled()
	{
		return $this->connection->tableExists($this->name_unprefixed);
	}

	public function getExtendedSchema()
	{
		$table = $this;
		$schemas = array();

		while ($table)
		{
			$schemas[] = $table->schema;

			$table = $table->parent;
		}

		$schema = call_user_func_array('wd_array_merge_recursive', $schemas);

		$this->connection->parseSchema($schema);

		return $schema;
	}

	public function resolve_statement($statement)
	{
		return strtr
		(
			$statement, array
			(
				'{alias}' => $this->alias,
				'{prefix}' => $this->connection->prefix,
				'{primary}' => $this->primary,
				'{self}' => $this->name,
				'{self_and_related}' => $this->name . $this->select_join
			)
		);
	}

	/**
	 * Interface to the connection's prepare method.
	 *
	 * @return WdDatabaseStatement
	 */

	public function prepare($query, $options=array())
	{
		$query = $this->resolve_statement($query);

		return $this->connection->prepare($query, $options);
	}

	public function quote($string, $parameter_type=PDO::PARAM_STR)
	{
		return $this->connection->quote($string, $parameter_type);
	}

	public function execute($query, array $args=array(), array $options=array())
	{
		$statement = $this->prepare($query, $options);

		return $statement->execute($args);
	}

	/**
	 * Interface to the connection's query() method.
	 *
	 * The statement is resolved using the resolve_statement() method and prepared.
	 *
	 * @param string $query
	 * @param array $args
	 * @param array $options
	 *
	 * @return WdDatabaseStatement
	 */

	public function query($query, array $args=array(), array $options=array())
	{
		$query = $this->resolve_statement($query);

		$statement = $this->prepare($query, $options);
		$statement->execute($args);

		return $statement;
	}

	/*
	**

	INSERT & UPDATE

	TODO: move save() to WdModel

	**
	*/

	protected function filter_values(array $values, $extended=false)
	{
		$filtered = array();
		$holders = array();
		$identifiers = array();

		$schema = $extended ? $this->getExtendedSchema() : $this->schema;
		$fields = $schema['fields'];

		foreach ($values as $identifier => $value)
		{
			if (!array_key_exists($identifier, $fields))
			{
				//wd_log('unknown identifier: \1 for \2', $identifier, $this->name);

				continue;
			}

			$filtered[] = $value;
			$holders[$identifier] = '`' . $identifier . '` = ?';
			$identifiers[] = '`' . $identifier . '`';
		}

		return array($filtered, $holders, $identifiers);
	}

	public function save(array $values, $id=null, array $options=array())
	{
		if ($id)
		{
			return $this->update($values, $id) ? $id : false;
		}

		//wd_log(__CLASS__ . '::' . __FUNCTION__ . ':: id: \1, values: \2', $id, $values);

		return $this->save_callback($values, $id, $options);
	}

	protected function save_callback(array $values, $id=null, array $options=array())
	{
		if ($id)
		{
			$this->update($values, $id);

			return $id;
		}







		if (empty($this->schema['fields']))
		{
			throw new WdException('Missing fields in schema');
		}

		//wd_log('\1 save_callback: \2', $this->name, $values);

		$parent_id = 0;

		if ($this->parent)
		{
			$parent_id = $this->parent->save_callback($values, $id, $options);

			//wd_log('parent: \1, id: \2', $this->parent->name, $parent_id);

			if (!$parent_id)
			{
				throw new WdException('Parent save failed: \1 returning \2', array((string) $this->parent->name, $parent_id));
			}
		}

		$driver_name = $this->connection->driver_name;

		//wd_log('<h3>\1 (id: \3::\2)</h3>', $this->name, $id, $parent_id);

		//echo t('here in \1, parent: \2<br />', array($this->name, $this->parent ? $this->parent->name : 'NONE'));

		list($filtered, $holders) = $this->filter_values($values);

		//wd_log('we: \3, parent_id: \1, holders: \2', $parent_id, $holders, $this->name);

		// FIXME: ALL THIS NEED REWRITE !

		if ($holders)
		{
			// faire attention à l'id, si l'on revient du parent qui a inséré, on doit insérer aussi, avec son id

			if ($id)
			{
				$filtered[] = $id;

				$statement = 'UPDATE {self} SET ' . implode(', ', $holders) . ' WHERE `{primary}` = ?';

				$statement = $this->prepare($statement);

			//wd_log('statement: \1', $statement);

				$rc = $statement->execute($filtered);
			}
			else
			{
				if ($driver_name == 'mysql')
				{
					if ($parent_id && empty($holders[$this->primary]))
					{
						$filtered[] = $parent_id;
						$holders[] = '`{primary}` = ?';
					}

					$statement = 'INSERT INTO {self} SET ' . implode(', ', $holders);

					//wd_log('statement: \1', array($statement));

					$statement = $this->prepare($statement);

					$rc = $statement->execute($filtered);
				}
				else if ($driver_name == 'sqlite')
				{
					//wd_log('filtered: \1, holders: \2, values: \3, options: \4', array($filtered, $holders, $values, $options));

					$rc = $this->insert($values, $options);
				}
			}
		}
		else if ($parent_id && !$id)
		{
			#
			# a new entry has been created, but we don't have any other fields then the primary key
			#

			if (empty($holders[$this->primary]))
			{
				$filtered[] = $parent_id;
				$holders[] = '`{primary}` = ?';
			}

			$statement = 'INSERT INTO {self} SET ' . implode(', ', $holders);

			$statement = $this->prepare($statement);

			//wd_log('statement: \1', $statement);

			$rc = $statement->execute($filtered);
		}
		else
		{
			$rc = true;
		}

		//wd_log('<h3>result: <pre>\1</pre>\2', $filtered, $rc);

		if ($parent_id)
		{
			return $parent_id;
		}

		if (!$rc)
		{
			return false;
		}

		if (!$id)
		{
			$id = $this->connection->lastInsertId();
		}

		return $id;
	}

	public function insert(array $values, array $options=array())
	{
		list($values, $holders, $identifiers) = $this->filter_values($values);

		if (!$values)
		{
			return;
		}

		$driver_name = $this->connection->driver_name;

		$on_duplicate = isset($options['on duplicate']) ? $options['on duplicate'] : null;

		if ($driver_name == 'mysql')
		{
			$query = 'INSERT ';

			if (!empty($options['ignore']))
			{
				$query .= 'IGNORE ';
			}

			$query .= 'INTO `{self}` SET ' . implode(', ', $holders);

			if ($on_duplicate)
			{
				if ($on_duplicate === true)
				{
					#
					# if 'on duplicate' is true, we use the same input values, but we take care of
					# removing the primary key and its corresponding value
					#

					$update_values = $values;
					$update_holders = $holders;

					$i = 0;

					foreach ($holders as $identifier => $dummy)
					{
	//					wd_log('id: \1 (\2)', $identifier, $i);

						if ($identifier == $this->primary)
						{
							unset($update_values[$i]);

							break;
						}

						$i++;
					}

					unset($update_holders[$this->primary]);
				}
				else
				{
					list($update_values, $update_holders) = $this->filter_values($on_duplicate);
				}

				$query .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $update_holders);

				$values = array_merge($values, $update_values);
			}
		}
		else if ($driver_name == 'sqlite')
		{
			$holders = array_fill(0, count($identifiers), '?');

			$query = 'INSERT' . ($on_duplicate ? ' OR REPLACE' : '') . ' INTO `{self}` (' . implode(', ', $identifiers) . ') VALUES (' . implode(', ', $holders) . ')';
		}

		//wd_log('<h3>insert</h3><pre>\3</pre>\2\1', $values, $holders, $query);

		return $this->execute($query, $values);
	}

	/**
	 * Update the values of an entry.
	 *
	 * Even if the entry is spread over multiple tables, all the tables are updated in a single
	 * step.
	 *
	 * @param array $values
	 * @param mixed $key
	 */

	public function update(array $values, $key)
	{
		list($values, $holders) = $this->filter_values($values, true);

		$query = 'UPDATE `{self}` ' . $this->update_join . ' SET ' . implode(', ', $holders) . ' WHERE `{primary}` = ?';
		$values[] = $key;

		return $this->execute($query, $values);
	}

	/*
	**

	DELETE & TRUNCATE

	TODO: move delete() to WdModel

	**
	*/

	public function delete($key)
	{
		if ($this->parent)
		{
			$this->parent->delete($key);
		}

		$where = 'where ';

		if (is_array($this->primary))
		{
			$parts = array();

			foreach ($this->primary as $identifier)
			{
				$parts[] = '`' . $identifier . '` = ?';
			}

			$where .= implode(' and ', $parts);
		}
		else
		{
			$where .= '`{primary}` = ?';
		}

		return $this->execute
		(
			'delete from `{self}` ' . $where, (array) $key
		);
	}

	// FIXME-20081223: what about extends ?

	public function truncate()
	{
		if ($this->connection->driver_name == 'sqlite')
		{
			$rc = $this->execute('delete from {self}');

			$this->execute('vacuum');

			return $rc;
		}

		return $this->execute('truncate table `{self}`');
	}

	public function drop(array $options=array())
	{
		$query = 'drop table ';

		if (!empty($options['if exists']))
		{
			$query .= 'if exists ';
		}

		$query .= '`{self}`';

		return $this->execute($query);
	}
}