<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdDatabase extends PDO
{
	public $name;
	public $prefix;
	public $charset = 'utf8';
	public $collate = 'utf8_general_ci';

	public $driver_name;

	static public $stats = array
	(
		'queries_by_connection' => array()
	);

	public function __construct($dsn, $username=null, $password=null, $options=array())
	{
		list($driver_name) = explode(':', $dsn);

		$this->driver_name = $driver_name;

		foreach ($options as $option => $value)
		{
			switch ($option)
			{
				case '#name': $this->name = $value; break;
				case '#prefix': $this->prefix = $value ? $value . '_' : null; break;
				case '#charset': $this->charset = $value; $this->collate = null; break;
				case '#collate': $this->collate = $value; break;
			}
		}

		self::$stats['queries_by_connection'][$this->name] = 0;

		parent::__construct($dsn, $username, $password, $options);

		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('WdDatabaseStatement'));

		if ($this->driver_name == 'mysql')
		{
			$this->exec('set names ' . $this->charset);
		}
		else if ($this->driver_name == 'oci')
		{
			$this->exec("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD'");
		}
	}

	/*
	**

	QUERY & EXECUTE

	**
	*/

	public function prepare($query, $options=array())
	{
		$query = $this->resolve_statement($query);

		try
		{
			$statement = parent::prepare($query, $options);
		}
		catch (PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new WdException
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>%query</code>', array
				(
					$er[0], $er[1], $er[2], '%query' => $query
				)
			);
		}

		$statement->connection = $this;

		if (isset($options['mode']))
		{
			#
			# change fetch mode
			#

			$mode = (array) $options['mode'];

			//wd_log(__CLASS__ . '::' . __FUNCTION__ . ':: mode: :mode', array(':mode' => $mode));

			try
			{
				call_user_func_array(array($statement, 'setFetchMode'), $mode);
			}
			catch (PDOException $e)
			{
				throw new WdException($e . 'fuck with: \1', array($mode));
			}
		}

		return $statement;
	}

	public function begin()
	{
		return $this->beginTransaction();
	}

	public function query($query, array $args=array(), array $options=array())
	{
		$statement = $this->prepare($query, $options);

		$statement->execute($args);

		//wd_log(__CLASS__ . '::' . __FUNCTION__ . ':: \1', $statement);

		return $statement;
	}

	public function exec($statement)
	{
		$statement = $this->resolve_statement($statement);

		try
		{
			WdDatabase::$stats['queries_by_connection'][$this->name]++;

			$rc = parent::exec($statement);

			if ((int) $this->errorCode())
			{
				throw new PDOException();
			}

			return $rc;
		}
		catch (PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new WdException
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>\4</code>', array
				(
					$er[0], $er[1], $er[2], $statement
				)
			);
		}
	}

	public function resolve_statement($query)
	{
		return strtr
		(
			$query,

			array
			(
				'{prefix}' => $this->prefix,
				'{charset}' => $this->charset,
				'{collate}' => $this->collate
			)
		);
	}

	/*
	**

	TABLES

	**
	*/

	public function parseSchema(array $schema)
	{
		if (empty($schema['fields']))
		{
			WdDebug::trigger
			(
				'Missing %tag in schema: :schema', array
				(
					'%tag' => 'fields',
					':schema' => $schema
				)
			);

			return;
		}

		$driver_name = $this->driver_name;

		foreach ($schema['fields'] as $identifier => &$params)
		{
			$params = (array) $params;

			#
			# translate special indexes to keys
			#

			if (isset($params[0]))
			{
				$params['type'] = $params[0];

				unset($params[0]);
			}

			if (isset($params[1]))
			{
				$params['size'] = $params[1];

				unset($params[1]);
			}

			#
			# handle special types
			#

			switch($params['type'])
			{
				case 'serial':
				{
					$params += array('serial' => true);
				}
				// continue to primary

				case 'primary':
				{
					$params['type'] = 'integer';

					#
					# because auto increment only work on "INTEGER AUTO INCREMENT" ins SQLite
					#

					if ($driver_name != 'sqlite')
					{
						$params += array('size' => 'big', 'unsigned' => true);
					}

					$params += array('primary' => true);
				}
				break;

				case 'foreign':
				{
					$params['type'] = 'integer';

					if ($driver_name != 'sqlite')
					{
						$params += array('size' => 'big', 'unsigned' => true);
					}

					$params += array('indexed' => true);
				}
				break;

				case 'varchar':
				{
					$params += array('size' => 255);
				}
				break;
			}

			#
			# serial
			#

			if (isset($params['primary']))
			{
				$schema['primary-key'] = $identifier;
			}

			#
			# indexed
			#

			if (!empty($params['indexed']))
			{
				$index = $params['indexed'];

				if (is_string($index))
				{
					if (isset($schema['indexes'][$index]) && in_array($identifier, $schema['indexes'][$index]))
					{
						//wd_log('<em>\1</em> is already defined in index <em>\2</em>', $identifier, $index);
					}
					else
					{
						$schema['indexes'][$index][] = $identifier;
					}
				}
				else
				{
					if (isset($schema['indexes']) && in_array($identifier, $schema['indexes']))
					{
						//wd_log('index <em>\1</em> already defined in schema', $identifier);
					}
					else
					{
						$schema['indexes'][$identifier] = $identifier;
					}
				}
			}
		}

		#
		# indexes that are part of the primary key are deleted
		#

		if (isset($schema['indexes']) && isset($schema['primary-key']))
		{
			$primary = (array) $schema['primary-key'];

			foreach ($schema['indexes'] as $identifier => $dummy)
			{
				if (!in_array($identifier, $primary))
				{
					continue;
				}

				unset($schema['indexes'][$identifier]);

				//wd_log('index in primary: \1', $identifier);
			}
		}

		return $schema;
	}

	public function createTable($name, array $schema)
	{
		// FIXME-20091201: I don't think 'UNIQUE' is properly implemented

		$collate = $this->collate;
		$driver_name = $this->driver_name;

		$schema = $this->parseSchema($schema);

		$parts = array();

		foreach ($schema['fields'] as $identifier => $params)
		{
			$definition = '`' . $identifier . '`';

			$type = $params['type'];
			$size = isset($params['size']) ? $params['size'] : 0;

			switch ($type)
			{
				case 'blob':
				case 'char':
				case 'integer':
				case 'text':
				case 'varchar':
				case 'bit':
				{
					if ($size)
					{
						if (is_string($size))
						{
							$definition .= ' ' . $size . ($type == 'integer' ? 'int' : $type);
						}
						else
						{
							$definition .= ' ' . $type . '(' . $size . ')';
						}
					}
					else
					{
						$definition .= ' ' . $type;
					}

					if (($type == 'integer') && !empty($params['unsigned']))
					{
						$definition .= ' unsigned';
					}
				}
				break;

				case 'boolean':
				case 'date':
				case 'datetime':
				case 'time':
				case 'timestamp':
				case 'year':
				{
					$definition .= ' ' . $type;
				}
				break;

				case 'enum':
				{
					$enum = array();

					foreach ($size as $identifier)
					{
						$enum[] = '\'' . $identifier . '\'';
					}

					$definition .= ' ' . $type . '(' . implode(', ', $enum) . ')';
				}
				break;

				case 'double':
				case 'float':
				{
					$definition .= ' ' . $type;

					if ($size)
					{
						$definition .= '(' . implode(', ', (array) $size) . ')';
					}
				}
				break;

				default:
				{
					throw new WdException
					(
						'Unknown type %type for row %identifier', array
						(
							'%type' => $type,
							'%identifier' => $identifier
						)
					);
				}
				break;
			}

			#
			# null
			#

			if (empty($params['null']))
			{
				$definition .= ' not null';
			}
			else
			{
				$definition .= ' null';
			}

			#
			# default
			#

			if (!empty($params['default']))
			{
				$default = $params['default'];

				$definition .= ' default ' . ($default{strlen($default) - 1} == ')' ? $default : '"' . $default . '"');
			}

			#
			# serial, unique
			#

			if (!empty($params['serial']))
			{
				if ($driver_name == 'mysql')
				{
					$definition .= ' auto_increment';
				}
				else if ($driver_name == 'sqlite')
				{
					$definition .= ' primary key';

					unset($schema['primary-key']);
				}
			}
			else if (!empty($params['unique']))
			{
				$definition .= ' unique';
			}

			$parts[] = $definition;
		}

		#
		# primary key
		#

		if (isset($schema['primary-key']))
		{
			$keys = (array) $schema['primary-key'];

			foreach ($keys as &$key)
			{
				$key = '`' . $key . '`';
			}

			$parts[] = 'primary key (' . implode(', ', $keys) . ')';
		}

		#
		# indexes
		#

		if (isset($schema['indexes']) && $driver_name == 'mysql')
		{
			foreach ($schema['indexes'] as $key => $identifiers)
			{
				$definition = 'index ';

				if (!is_numeric($key))
				{
					$definition .= '`' . $key . '` ';
				}

				$identifiers = (array) $identifiers;

				foreach ($identifiers as &$identifier)
				{
					$identifier = '`' . $identifier . '`';
				}

				$definition .= '(' . implode(',', $identifiers) . ')';

				$parts[] = $definition;
			}
		}

//		wd_log('<h3>parts</h3>\1', $parts);

		$table_name = $this->prefix . $name;

		$statement  = 'create table `' . $table_name . '` (';
		$statement .= implode(', ', $parts);
		$statement .= ')';

		if ($driver_name == 'mysql')
		{
			$statement .= ' character set ' . $this->charset . ' collate ' . $this->collate;
		}

		//wd_log('driver: \3, statement: <code>\1</code> indexes: \2', array($statement, $schema['indexes'], $this->driver_name));

		$rc = ($this->exec($statement) !== false);

		if (!$rc)
		{
			return $rc;
		}

		if (isset($schema['indexes']) && $driver_name == 'sqlite')
		{
			#
			# SQLite: now that the table has been created, we can add indexes
			#

			foreach ($schema['indexes'] as $key => $identifiers)
			{
				$statement = 'CREATE INDEX `' . $key . '` ON ' . $table_name;

				$identifiers = (array) $identifiers;

				foreach ($identifiers as &$identifier)
				{
					$identifier = '`' . $identifier . '`';
				}

				$statement .= ' (' . implode(',', $identifiers) . ')';

				//wd_log('indexes: \1 \2 == \3', array($key, $identifiers, $statement));

				$this->exec($statement);
			}
		}

		return $rc;
	}

	public function tableExists($name)
	{
		//try
		{
			$name = $this->prefix . $name;

			switch ($this->driver_name)
			{
				case 'sqlite':
				{
					$tables = $this->query('SELECT name FROM sqlite_master WHERE type = "table"')->fetchAll(self::FETCH_COLUMN);
				}
				break;

				default:
				{
					$tables = $this->query('SHOW TABLES')->fetchAll(self::FETCH_COLUMN);
				}
				break;
			}

			foreach ($tables as $table)
			{
				if ($name == $table)
				{
					return true;
				}
			}
		}
		//catch (Exception $e) {}

		return false;
	}

	public function optimize()
	{
		if ($this->driver_name == 'sqlite')
		{
			$this->exec('VACUUM');
		}
		else if ($this->driver_name == 'mysql')
		{
			$tables = $this->query('SHOW TABLES')->fetchAll(self::FETCH_COLUMN);

			$stmt = $this->query('OPTIMIZE TABLE ' . implode(', ', $tables));

			$stmt->closeCursor();
		}
	}
}

class WdDatabaseStatement extends PDOStatement
{
	public function execute($args=array())
	{
		WdDatabase::$stats['queries_by_connection'][$this->connection->name]++;

		try
		{
			return parent::execute($args);
		}
		catch (PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new WdException
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>%query</code>\5', array
				(
					$er[0], $er[1], $er[2], '%query' => $this->queryString, $args
				)
			);
		}
	}

	public function fetchAndClose($fetch_style=PDO::FETCH_BOTH, $cursor_orientation=PDO::FETCH_ORI_NEXT, $cursor_offset=0)
	{
		$args = func_get_args();

		$rc = call_user_func_array(array($this, 'parent::fetch'), $args);

		$this->closeCursor();

		return $rc;
	}

	public function fetchColumnAndClose($column_number=0)
	{
		$rc = parent::fetchColumn($column_number);

		$this->closeCursor();

		return $rc;
	}

	public function fetchPairs()
	{
		$rc = array();

		$rows = parent::fetchAll(PDO::FETCH_NUM);

		foreach ($rows as $row)
		{
			$rc[$row[0]] = $row[1];
		}

		return $rc;
	}
}