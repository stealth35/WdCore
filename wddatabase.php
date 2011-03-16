<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2011 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdDatabase extends PDO
{
	const T_ID = '#id';
	const T_PREFIX = '#prefix';
	const T_CHARSET = '#charset';
	const T_COLLATE = '#collate';
	const T_TIMEZONE = '#timezone';

	/**
	 * Connection identifier.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Prefix for the database tables.
	 *
	 * @var string
	 */
	public $prefix;

	/**
	 * Charset for the connection. Also used to specify the charset while creating tables.
	 *
	 * @var string
	 */
	public $charset = 'utf8';

	/**
	 * Used to specify the collate while creating tables.
	 * @var unknown_type
	 */
	public $collate = 'utf8_general_ci';

	/**
	 * Driver name for the connection.
	 *
	 * @var string
	 */
	public $driver_name;

	/**
	 * The number of database queries and executions, used for statistics purpose.
	 *
	 * @var int
	 */
	public $queries_count = 0;

	/**
	 * Creates a WdDatabase instance representing a connection to a database.
	 *
	 * Custom options can be specified using the driver-specific connection options:
	 *
	 * - T_ID: Connection identifier.
	 * - T_PREFIX: Prefix for the database tables.
	 * - T_CHARSET and T_COLLATE: Charset and collate used for the connection to the database,
	 * and to create tables.
	 * - T_TIMEZONE: Timezone for the connection.
	 *
	 * @link http://www.php.net/manual/en/pdo.construct.php
	 * @link http://dev.mysql.com/doc/refman/5.5/en/time-zone-support.html
	 *
	 * @param string $dsn
	 * @param string $username
	 * @param string $password
	 * @param array $options
	 */
	public function __construct($dsn, $username=null, $password=null, $options=array())
	{
		$timezone = null;

		foreach ($options as $option => $value)
		{
			switch ($option)
			{
				case self::T_ID: $this->id = $value; break;
				case self::T_PREFIX: $this->prefix = $value ? $value . '_' : null; break;
				case self::T_CHARSET: $this->charset = $value; $this->collate = null; break;
				case self::T_COLLATE: $this->collate = $value; break;
				case self::T_TIMEZONE: $timezone = $value; break;
			}
		}		

		parent::__construct($dsn, $username, $password, $options);		

		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('WdDatabaseStatement'));
		
		$this->driver_name = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
		
		if ($driver_name == 'mysql')
		{
			$init_command = 'SET NAMES ' . $this->charset;

			if ($timezone)
			{
				$init_command .= ', time_zone = "' . $timezone . '"';
			}

			$this->exec($init_command);
		}

		if ($driver_name == 'oci')
		{
			$this->exec("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD'");
		}
	}

	/**
	 * Overrides the method to resolve the statement before it is prepared, then set its fetch
	 * mode and connection.
	 *
	 * @return WdDatabaseStatement The prepared statement.
	 *
	 * @see PDO::prepare()
	 * @see WdDatabase::resolve_statement()
	 */
	public function prepare($statement, $options=array())
	{
		$statement = $this->resolve_statement($statement);

		try
		{
			$statement = parent::prepare($statement, $options);
		}
		catch (PDOException $e)
		{
			$er = array_pad($this->errorInfo(), 3, '');

			throw new WdException
			(
				'SQL error: \1(\2) <code>\3</code> &mdash; <code>%query</code>', array
				(
					$er[0], $er[1], $er[2], '%query' => $statement
				)
			);
		}

		$statement->connection = $this;

		if (isset($options['mode']))
		{
			$mode = (array) $options['mode'];

			call_user_func_array(array($statement, 'setFetchMode'), $mode);
		}

		return $statement;
	}

	/**
	 * Overrides the method in order to prepare (and resolve) the statement and execute it with
	 * the specified arguments and options.
	 *
	 * @see PDO::query()
	 */
	public function query($statement, array $args=array(), array $options=array())
	{
		$statement = $this->prepare($statement, $options);
		$statement->execute($args);

		return $statement;
	}

	/**
	 * Overrides the method to resolve the statement before actually execute it.
	 *
	 * The execution of the statement is wrapped in a try/catch block. If an exception of class
	 * PDOException is catched, an exception of class WdException is thrown with addition
	 * information about the error.
	 *
	 * Using this method increments the `queries_by_connection` stat.
	 *
	 * @see PDO::exec()
	 */
	public function exec($statement)
	{
		$statement = $this->resolve_statement($statement);

		try
		{
			$this->queries_count++;

			return parent::exec($statement);
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

	/**
	 * Places quotes around the identifier.
	 *
	 * @param string $identifier
	 * @return string
	 */
	public function quote_identifier($identifier)
	{
		return $this->driver_name == 'oci' ? '"' . $identifier . '"' : '`' . $identifier . '`';
	}

	/**
	 * Replaces placeholders with their value. The following placeholders are supported:
	 *
	 * - {prefix}: replaced by the value of the `#prefix` construct option.
	 * - {charset}: replaced by the value of the `#charset` construct option.
	 * - {collate}: replaced by the value of the `#collate` construct option.
	 *
	 * @param string $statement
	 *
	 * @return stirng The resolved statement.
	 */
	public function resolve_statement($statement)
	{
		return strtr
		(
			$statement, array
			(
				'{prefix}' => $this->prefix,
				'{charset}' => $this->charset,
				'{collate}' => $this->collate
			)
		);
	}

	/**
	 * Alias for the `beginTransaction()` method.
	 *
	 * @see PDO::beginTransaction()
	 */
	public function begin()
	{
		return $this->beginTransaction();
	}

	/**
	 * Parses a schema to create a schema with lowlevel definitions.
	 *
	 * For example, a column defined as 'serial' is parsed as :
	 *
	 * 'type' => 'integer', 'serial' => true, 'size' => 'big', 'unsigned' => true,
	 * 'primary' => true
	 *
	 * @param array $schema
	 *
	 * @return array
	 */
	public function parse_schema(array $schema)
	{
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
			}
		}

		return $schema;
	}

	/**
	 * Creates a table of the specified name and schema.
	 *
	 * @param string $name The unprefixed name of the table.
	 * @param array $schema The schema of the table.
	 * @throws WdException
	 */
	public function create_table($unprefixed_name, array $schema)
	{
		// FIXME-20091201: I don't think 'UNIQUE' is properly implemented

		$collate = $this->collate;
		$driver_name = $this->driver_name;

		$schema = $this->parse_schema($schema);

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

		$table_name = $this->prefix . $unprefixed_name;

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

	/**
	 * Checks if a specified table exists in the database.
	 *
	 * @param string $unprefixed_name The unprefixed name of the table.
	 * @return bool true if the table exists, false otherwise.
	 */
    public function table_exists($unprefixed_name)
    {
    	$name = $this->prefix . $unprefixed_name;

    	if ($this->driver_name == 'sqlite')
		{
			$tables = $this->query('SELECT name FROM sqlite_master WHERE type = "table" AND name = ?', array($name))->fetchAll(self::FETCH_COLUMN);

			return !empty($tables);
		}
		else
		{
			$tables = $this->query('SHOW TABLES')->fetchAll(self::FETCH_COLUMN);

			return in_array($name, $tables);
		}

		return false;
    }

    /**
     * Optimizes the tables of the database.
     *
     */
	public function optimize()
	{
		if ($this->driver_name == 'sqlite')
		{
			$this->exec('VACUUM');
		}
		else if ($this->driver_name == 'mysql')
		{
			$tables = $this->query('SHOW TABLES')->fetchAll(self::FETCH_COLUMN);

			$this->exec('OPTIMIZE TABLE ' . implode(', ', $tables));
		}
	}
}

/**
 * Class for WdDatabase statements.
 *
 */
class WdDatabaseStatement extends PDOStatement
{
	/**
	 * Executes the statement and increments the connection queries count.
	 *
	 * The statement is executed in a try/catch block, if an exception of class PDOException is
	 * caught an exception of class WdException is thrown with additionnal information.
	 *
	 * @see PDOStatement::execute()
	 */
	public function execute($args=array())
	{
		if(!empty($this->connection))
		{
			$this->connection->queries_count++;
		}
		
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

	/**
	 * Fetches the first row of the result set and closes the cursor.
	 *
	 * @param int $fetch_style[optional]
	 * @param int $cursor_orientation[optional]
	 * @param int $cursor_offset[optional]
	 *
	 * @return mixed
	 *
	 * @see PDOStatement::fetch()
	 */
	public function fetchAndClose($fetch_style=PDO::FETCH_BOTH, $cursor_orientation=PDO::FETCH_ORI_NEXT, $cursor_offset=0)
	{
		$args = func_get_args();
		$rc = call_user_func_array(array($this, 'parent::fetch'), $args);

		$this->closeCursor();

		return $rc;
	}

	/**
	 * Fetches a column of the first row of the result set and closes the cursor.
	 *
	 * @return string;
	 *
	 * @see PDOStatement::fetchColumn()
	 */
	public function fetchColumnAndClose($column_number=0)
	{
		$rc = parent::fetchColumn($column_number);

		$this->closeCursor();

		return $rc;
	}

	/**
	 * Fetches an array of key/value pairs using the first column of the result set as keys and the
	 * second column as values.
	 *
	 * @return array
	 */
	public function fetchPairs()
	{
		$rc = array();
		
		if(parent::columnCount() === 2)
		{
			$rc = parent::fetchAll(PDO::FETCH_KEY_PAIR);
		}
		else
		{
			$rows = parent::fetchAll(PDO::FETCH_NUM);

			foreach ($rows as $row)
			{
				$rc[$row[0]] = $row[1];
			}			
		}
		
		return $rc;
	}
	
	/**
	 * Returns an array containing all of the result set rows (FETCH_LAZY supported)
	 *
	 * @param int $fetch_style
	 * @param mixed $fetch_argument[optional]
	 * @param array $ctor_args[optional]
	 * 
	 * @return array
	 */
	public function fetchGroups($fetch_style, $fetch_argument=null, array $ctor_args=array())
	{
		$args = func_get_args();		
		$rc = array();		
		
		if($fetch_style === PDO::FETCH_LAZY)
		{			
			while($row = call_user_func_array(array($this, 'parent::fetch'), $args))
			{				
				$rc[$row[0]][] = $row;
			}
			
			return $rc;
		}		
		
		$args[0] = PDO::FETCH_GROUP | $fetch_style;
			
		$rc = call_user_func_array(array($this, 'parent::fetchAll'), $args);
		
		return $rc;
	}
}