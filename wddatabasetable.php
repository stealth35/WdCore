<?php

/* ***** BEGIN LICENSE BLOCK *****
 *
 * This file is part of WdCore (http://www.weirdog.com/wdcore/).
 *
 * Software License Agreement (New BSD License)
 *
 * Copyright (c) 2007-2010, Olivier Laviale
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Olivier Laviale nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * ***** END LICENSE BLOCK ***** */

require_once 'wddatabase.php';

// TODO: get rid of this WdTags extends

class WdTags
{
	protected $tags = array();

	public function __construct($tags)
	{
		$this->tags = $tags;
	}

	public function getTag($tag, $default=null)
	{
		return isset($this->tags[$tag]) ? $this->tags[$tag] : $default;
	}

	public function getTags()
	{
		return $this->tags;
	}
}

class WdDatabaseTable extends WdTags
{
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

	protected $schema;
	protected $parent;
	protected $implements = array();

	protected $select_join;

	public function __construct($tags)
	{
		foreach ($tags as $tag => $value)
		{
			switch ($tag)
			{
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
			throw new WdException('The %tag tag is mandatory', array('%tag' => 'T_CONNECTION'));
		}

		$this->name = $this->connection->prefix . $this->name_unprefixed;

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

		$join = ' as t1 ';

		$parent = $this->parent;

		if ($parent)
		{
			$i = 2;

			while ($parent)
			{
				$name = $parent->name;

				$join .= "inner join `$name` as t$i using(`{primary}`) ";

				$i++;
				$parent = $parent->parent;
			}
		}

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

				$join .= empty($implement['loose']) ? 'inner' : 'left';
				$join .= " join `$name` as i$i using(`$primary`) ";

				$i++;
			}
		}

//		wd_log('join query for %name: <code>:join</code>', array('%name' => $this->name, ':join' => $join));

		$this->select_join = $join;

		parent::__construct($tags);
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

	protected function resolveStatement($query, array $args=array())
	{
		return strtr
		(
			$query, array
			(
				'{self}' => $this->name,
				'{prefix}' => $this->connection->prefix,
				'{primary}' => $this->primary
			)
		);
	}

	public function prepare($query, $options=array())
	{
		$query = $this->resolveStatement($query);

		return $this->connection->prepare($query, $options);
	}

	public function execute($query, array $args=array(), array $options=array())
	{
		$statement = $this->prepare($query, $options);

		return $statement->execute($args);
	}

	public function query($query, array $args=array(), array $options=array())
	{
		$query = $this->resolveStatement($query, $args);

		$statement = $this->prepare($query, $options);

		$statement->execute($args);

		//wd_log(__CLASS__ . '::' . __FUNCTION__ . ':: query: \1', $statement);

		return $statement;
	}

	/*
	**

	INSERT & UPDATE

	TODO: move save() to WdModel

	**
	*/

	protected function filterValues(array $values)
	{
		$filtered = array();
		$holders = array();
		$identifiers = array();

		$fields = $this->schema['fields'];

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
			$this->update($values, $id);

			return $id;
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
				WdDebug::trigger('Parent save failed: \1 returning \2', array((string) $this->parent, $parent_id));

				return;
			}
		}

		$driver_name = $this->connection->driver_name;

		//wd_log('<h3>\1 (id: \3::\2)</h3>', $this->name, $id, $parent_id);

		//echo t('here in \1, parent: \2<br />', array($this->name, $this->parent ? $this->parent->name : 'NONE'));

		list($filtered, $holders) = $this->filterValues($values);

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
					if ($parent_id)
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

			$filtered[] = $parent_id;
			$holders[] = '`{primary}` = ?';

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
		list($values, $holders, $identifiers) = $this->filterValues($values);

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
					list($update_values, $update_holders) = $this->filterValues($on_duplicate);
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


	public function update(array $values, $id)
	{
		if (!$id)
		{
			throw new WdException
			(
				'The id of the row to be updated must be defined: <code>\1</code> \2', array
				(
					$query, $values
				)
			);
		}

		return $this->update_callback($values, $id);
	}

	protected function update_callback(array $values, $id)
	{
		if (empty($this->schema['primary-key']))
		{
			throw new WdException
			(
				'There is no primary key defined for %name, schema: :schema', array
				(
					'%name' => $this->name_unprefixed,
					':schema' => $this->schema
				)
			);
		}


		if ($this->parent)
		{
			$this->parent->update_callback($values, $id);
		}




		#
		#
		#

		list($values, $holders) = $this->filterValues($values);

		//wd_log(__CLASS__ . '::' . __FUNCTION__ . 'values \1 holders \2', $values, $holders);

		if (!$holders)
		{
			return;
		}

		$query = 'UPDATE `{self}` SET ' . implode(', ', $holders);

		$query .= ' WHERE `{primary}` = ?';
		$values[] = $id;

		return $this->execute($query, $values);
	}

	/*
	**

	DELETE & TRUNCATE

	TODO: move delete() to WdModel

	**
	*/

	public function delete($id)
	{
		if ($this->parent)
		{
			$this->parent->delete($id);
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
			'delete from `{self}` ' . $where, (array) $id
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

	/*
	**

	SELECT

	**
	*/

	public function select($fields, $completion=null, array $args=array(), array $options=array())
	{
		$join = $this->select_join;

		if (is_array($completion))
		{
			$completion = $completion ? 'WHERE ' . implode(' AND ', $completion) : '';
		}

		#
		# build query
		#

		$fields = (array) $fields;

		foreach ($fields as $as => &$field)
		{
			$lindex = strlen($field) - 1;

			if (($field != '*') && ($field{$lindex} != '*') && ($field{0} != '(') && ($field{$lindex} != ')') && (strpos($field, '.') === false))
			{
				$field = '`' . $field . '`';
			}

			if (is_string($as))
			{
				$field .= ' as `' . $as . '`';
			}
		}

		$query = 'select ' . implode(', ', $fields) . ' from `{self}` ' . $join . ' ' . $completion;

		return $this->query($query, $args, $options + array('mode' => PDO::FETCH_ASSOC));
	}

	public function count($which=null, $order='asc', $completion=null, array $args=array(), array $options=array())
	{
		/*
		$func_args = func_get_args();
		wd_log('DISABLED - \1::\2 args: \3', __CLASS__, __FUNCTION__, $func_args);
		*/

		$query = $completion;
		/*
		if ($where)
		{
			$query = 'where ' . $where;
		}
		*/

		if ($which)
		{
//			throw new WdException('\1::\2 <em>which</em> is not yet handled: \3', __CLASS__, __FUNCTION__, $which);

			$query .= ' group by `' . $which . '` order by `' . $which . '` ' . $order;

			$identifiers = array('count(`' . $which . '`)', $which);
		}
		else
		{
//			$identifiers = array('count(' . ($this->primary ? '`{primary}`' : '*') . ')');
			$identifiers = array('count(t1.{primary})');
		}

		$statement = $this->select($identifiers, $query, $args, $options);

		//wd_log('count statement: \1', $statement);

		#
		# return result
		#

		if (!$which)
		{
			return $statement->fetchColumnAndClose();
		}

		$counts = array();

		foreach ($statement->fetchAll(PDO::FETCH_NUM) as $row)
		{
			$counts[$row[1]] = $row[0];
		}

		return $counts;
	}

	/*
	 * TODO: move the following methods to WdModel
	 * TODO: loaded entries are objects of the WdActiveRecord class, using the WdModel factory
	 */

	public function loadAll($completion=null, array $args=array(), array $options=array())
	{
		return $this->select(array('*'), $completion, $args, $options);
	}

	public function loadRange($start, $limit=null, $where=null, array $args=array(), array $options=array())
	{
		if ($limit === null)
		{
			$limit = '18446744073709551615';
		}

		if (is_array($where))
		{
			$where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
		}

		$where .= ' limit ';

		if ($start)
		{
			$where .= (int) $start . ', ';
		}

		$where .= (int) $limit;

		return $this->loadAll($where, $args, $options);
	}

	public function load($id)
	{
		if (is_array($this->primary))
		{
			$where = array();

			foreach ($this->primary as $identifier)
			{
				$where[] = "`$identifier` = ?";
			}

			$statement = $this->loadRange(0, 1, 'where ' . implode(' AND ', $where), $id);
		}
		else
		{
			$statement = $this->loadRange(0, 1, 'where `{primary}` = ?', array($id));
		}

		return $statement->fetchAndClose();
	}
}

/*

20091111 # 01.02

[NEW] Parent implements are now inherited too.

*/