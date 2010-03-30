<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdOperation
{
	const DESTINATION = '#destination';
	const NAME = '#operation';
	const KEY = '#key';

	static public function encode($destination, $name, array $params=array(), $encode=false)
	{
		$query = http_build_query
		(
			array
			(
				'do' => $destination . '.' . $name
			)

			+ $params,

			'', '&'
		);

		if ($encode)
		{
			$query = base64_encode($query);

			#
			# We need the '=' as a final character to recognize the query as encoded, if the base64
			# encoding didn't need padding, we pad it anyway to get some '='.
			#

			if (substr($query, -1, 1) != '=')
			{
				$query .= '===';
			}

			$query = 'do=' . $query;
		}

		return '?' . $query;
	}

	static public function decode($request)
	{
		$destination = null;
		$operation = null;

		/*
		try
		{*/
			if (isset($request['!do']))/*
		{
			throw new WdException('The "!do" operation identifier is no longer supported', array(), 500);
		}
		}
		catch (Exception $e)*/
		{
			$request['do'] = $request['!do'];
		}

		if (isset($request['do']) && isset($request[self::NAME]))
		{
			throw new WdException('Ambiguous operation: both GET and POST methods are used.');
		}
		else if (isset($request['do']))
		{
			$do = $request['do'];
			unset($request['do']);

			#
			# Decode encoded operation, and build union with the actual request
			#

			if (substr($do, -1, 1) == '=')
			{
				parse_str(base64_decode($do), $request_base);

				if (get_magic_quotes_gpc())
				{
					$request_base = wd_deepStripSlashes($request_base);
				}

				#
				# because we make a union, additionnal parameters may be defined to override
				# encoded ones.
				#

				$request += $request_base;
				$do = $request['do'];
			}

			$pos = strrpos($do, '.');

			if (!$pos)
			{
				return false;
			}

			$name = substr($do, $pos + 1);
			$destination = substr($do, 0, $pos);
		}
		else if (isset($request[self::NAME]))
		{
			$name = $request[self::NAME];

			if (empty($request[self::DESTINATION]))
			{
				throw new WdException('Missing destination for operation %operation', array('%operation' => $name));
			}

			$destination = $request[self::DESTINATION];

			unset($request[self::DESTINATION]);
			unset($request[self::NAME]);
		}

		if (!$destination || !$name)
		{
			return false;
		}

		return new WdOperation($destination, $name, $request);
	}

	/**
	 * Results from previous operations.
	 * @var array
	 */

	static protected $results = array();

	static function setResult($name, $result)
	{
		self::$results[$name] = $result;
	}

	/**
	 * Return the result from previous operations.
	 *
	 * @param string $name The name of the previous operation.
	 * @return mixed The result of the previous operation, or null if the operation did not occur.
	 */

	static function getResult($name=null)
	{
		if (!$name)
		{
			$keys = array_keys(self::$results);
			$name = array_shift($keys);
		}

		return isset(self::$results[$name]) ? self::$results[$name] : null;
	}

	public $name;
	public $destination;
	public $key;
	public $params = array();

	public $response;
	public $terminus = false;
	public $location;

	public function __construct($destination, $name, array $params=array(), array $tags=array())
	{
		$this->destination = $destination;
		$this->name = $name;
		$this->params = $params;

		if (isset($params[self::KEY]))
		{
			$this->key = $params[self::KEY];
		}

		$this->response = (object) array
		(
			'rc' => null,
			'log' => array()
		);
	}

	public function dispatch()
	{
		global $core;

		$destination = $this->destination;
		$name = $this->name;
		$module = $core->getModule($destination);

		#
		# We trigger the 'operation.<name>:before' event, listeners might use the event to
		# tweak the operation before the destination module processes the operation.
		#

		$event = WdEvent::fire
		(
			'operation.' . $name . ':before', array
			(
				'operation' => $this,
				'module' => $module
			)
		);

		#
		# We ask the module to handle the operation. In return we get a response or `null` if the
		# operation failed.
		#

		$rc = $module->handleOperation($this);

		#
		# If the operation succeed, we trigger a 'operation.<name>' event, listeners might use the
		# event for further processing. For example, a _comment_ module might delete the comments
		# related to an _article_ module from which an article has been deleted.
		#

		if ($rc !== null)
		{
			$this->response->rc = $rc;
			self::setResult($name, $rc);

			WdEvent::fire
			(
				'operation.' . $name, array
				(
					'rc' => &$this->response->rc,
					'operation' => $this,
					'module' => $module
				)
			);
		}

		$terminus = $this->terminus;
		$response = $this->response;

		#
		# The operation response can be requested as JSON or XML, in which case the script is
		# terminated with the formated output of the response.
		#

		$rc = null;
		$rc_type = null;

		if (isset($_SERVER['HTTP_ACCEPT']))
		{
			$accept = $_SERVER['HTTP_ACCEPT'];

			if ($accept == 'application/json' || $accept == 'application.xml')
			{
				$logs = array('done', 'error');

				foreach ($logs as $type)
				{
					$response->log[$type] = WdDebug::fetchMessages($type);
				}

				switch ($accept)
				{
					case 'application/json':
					{
						$rc = json_encode($response);
						$rc_type = 'application/json';
					}
					break;

					case 'application/xml':
					{
						$rc = wd_array_to_xml($response, 'response');
						$rc_type = 'application/xml';
					}
					break;
				}

				if ($rc !== null)
				{
					header('Content-Type: ' . $rc_type);
					header('Content-Length: '. strlen($rc));
				}

				$terminus = true;
			}
		}

		#
		#
		#

		if ($this->location)
		{
			header('Location: ' . $this->location);

			exit;
		}

		#
		# If the `terminus` is set the script stops.
		#
		# note: The remaining messages in the WdDebug class logs are added in the HTTP header. This
		# might be usefull for debugging.
		#

		if ($terminus)
		{
			$logs = array('done', 'error', 'debug');

			foreach ($logs as $type)
			{
				$n = 1;

				foreach (WdDebug::fetchMessages($type) as $message)
				{
					$message = strip_tags($message);
					$message = str_replace("\r\n", "\n", $message);
					$message = str_replace("\n", ' ### ', $message);

					header(sprintf('X-WdDebug-%s-%04d: %s', $type, $n++, $message));
				}
			}

			echo $rc;

			exit;
		}
	}

	public function handleBooleans(array $booleans)
	{
		$params = &$this->params;

		foreach ($booleans as $identifier)
		{
			if (empty($params[$identifier]))
			{
				$params[$identifier] = false;

				continue;
			}

			$params[$identifier] = filter_var($params[$identifier], FILTER_VALIDATE_BOOLEAN);
		}

		return $params;
	}
}