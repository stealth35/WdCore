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

	/**
	 * Encode an operation as an URL.
	 *
	 * Three encoding types are available :
	 *
	 * 1. Simple : The URL is composed as '?do=<destination>.<method>&param1=...'
	 * 2. Base64 (b) : The URL is encoded using base64
	 * 3. RESTful : The URL is created as a RESTful resource : '/do/<destination>/(<key>/)?<method>&param1=...'
	 *
	 * TODO-20100615: reorder parameters as $destination, $operation, $key, $params, $type
	 *
	 * @param unknown_type $destination
	 * @param unknown_type $name
	 * @param array $params
	 * @param unknown_type $encode
	 */

	static public function encode($destination, $name, array $params=array(), $encode=false, $key=null)
	{
		if ($encode === 'r')
		{
			$query = http_build_query
			(
				$params, '', '&'
			);

			return '/do/' . $destination . '/' . ($key ? $key . '/' : '') . $name . ($query ? '?' . $query : '');
		}

		#
		#
		#

		$query = http_build_query
		(
			array
			(
				'do' => $destination . '.' . $name
			)

			+ $params,

			'', '&'
		);

		if ($encode === true || $encode == 'b')
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
		$method = 'GET';
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

		#
		# RESTful
		#

		if (substr($_SERVER['REQUEST_URI'], 0, 4) == '/do/')
		{
			$uri = $_SERVER['REQUEST_URI'];

			if ($_SERVER['QUERY_STRING'])
			{
				$uri = substr($uri, 0, -strlen($_SERVER['QUERY_STRING']) - 1);
			}

			if (substr($uri, -5, 5) == '.json')
			{
				$_SERVER['HTTP_ACCEPT'] = 'application/json';

				$uri = substr($uri, 0, -5);
			}
			else if (substr($uri, -4, 4) == '.xml')
			{
				$_SERVER['HTTP_ACCEPT'] = 'application/xml';

				$uri = substr($uri, 0, -4);
			}

			#
			# routes
			#

			if (1)
			{
				$route = null;
				$routes = WdRoute::routes();

				foreach ($routes as $pattern => $route)
				{
					if (substr($pattern, 0, 4) != '/do/')
					{
						continue;
					}

					$match = WdRoute::match($uri, $pattern);

					if ($match)
					{
						if (is_array($match))
						{
							$request += $match;
						}

						$operation = new WdOperation($route, $pattern, $request);

						$operation->terminus = true;
						$operation->method = 'GET';

						//$_SERVER['HTTP_ACCEPT'] = 'application/json';

						return $operation;
					}
				}
			}

			#
			#
			#

			//preg_match('#^([a-z\.]+)/((\d+)/)?([a-zA-Z0-9]+)$#', substr($uri, 4), $matches);
			preg_match('#^([a-z\.]+)/(([^/]+)/)?([a-zA-Z0-9]+)$#', substr($uri, 4), $matches);

			if ($matches)
			{
				//$_SERVER['HTTP_ACCEPT'] = 'application/json';

				list( , $destination, , $operation_key, $name) = $matches;

				$request[self::KEY] = $matches[2] ? $operation_key : null;
			}
			else
			{
				throw new WdException('Uknown operation: %operation', array('%operation' => substr($uri, 4)), array(404 => 'Unknow operation'));
			}
		}
		else if (isset($request['do']) && isset($request[self::NAME]))
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
					$request_base = wd_strip_slashes_recursive($request_base);
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
			$method = 'POST';
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

		$operation = new WdOperation($destination, $name, $request);

		if ($method == 'GET')
		{
			$operation->terminus = true;
		}

		$operation->method = $method;

		return $operation;
	}

	public $name;
	public $destination;
	public $key;
	public $params = array();

	public $response;
	public $terminus = false;
	public $location;
	public $method;

	public function __construct($destination, $name, array $params=array())
	{
		$this->destination = $destination;
		$this->name = $name;
		$this->params = $params;

		if (isset($params[self::KEY]))
		{
			$this->key = $params[self::KEY];
		}
	}

	public function dispatch()
	{
		global $core, $app;

		$name = $this->name;

		#
		# reset results
		#

		$this->response = (object) array
		(
			'rc' => null,
			'log' => array()
		);

		#
		# We trigger the 'operation.<name>:before' event, listeners might use the event to
		# tweak the operation before the destination module processes the operation.
		#

		$destination = $this->destination;
		$module = null;

		if (is_array($destination))
		{
			$rc = call_user_func($destination['callback'], $this);
		}
		else
		{
			$module = $core->getModule($destination);

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

			$rc = $module->handle_operation($this);
		}

		#
		# If the operation succeed, we trigger a 'operation.<name>' event, listeners might use the
		# event for further processing. For example, a _comment_ module might delete the comments
		# related to an _article_ module from which an article has been deleted.
		#

		$this->response->rc = $rc;

		if ($rc !== null && $module)
		{
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

			if ($accept == 'application/json' || $accept == 'application/xml')
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
						// https://addons.mozilla.org/en-US/firefox/addon/10869/

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
			if (!headers_sent())
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
			}

			echo $rc;

			exit;
		}

		return $this->response->rc;
	}

	public function handle_booleans(array $booleans)
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