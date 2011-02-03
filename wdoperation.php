<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2011 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdOperation
{
	const DESTINATION = '#destination';
	const NAME = '#operation';
	const KEY = '#key';

	const RESTFUL_BASE = '/api/';
	const RESTFUL_BASE_LENGHT = 5;

	/**
	 * Encodes a module operation as a RESful URL:
	 *
	 * '/api/<destination>/(<key>/)?<method>?param1=...'
	 *
	 * @param mixed $destination
	 * @param string $name
	 * @param array $params
	 * @param integer $key
	 */

	static public function encode($destination, $name, array $params=array(), $key=null)
	{
		$query = http_build_query($params, '', '&');

		return self::RESTFUL_BASE . $destination . '/' . ($key !== null ? $key . '/' : '') . $name . ($query ? '?' . $query : '');
	}

	static public function decode($request)
	{
		$method = 'GET';
		$destination = null;
		$operation = null;

		//DIRTY:COMPAT

		if (isset($request['!do']))
		{
			$request['do'] = $request['!do'];
		}

		#
		# RESTful
		#

		$uri = $_SERVER['REQUEST_URI'];

		if (substr($uri, 0, 4) == '/do/')
		{
			if (WdDebug::$config['mode'] == 'test')
			{
				WdDebug::trigger('The URL for RESTful operation is now "/api/" instead of "/do/": %uri', array('%uri' => $uri));
			}

			$uri = '/api/' + substr($uri, 4);
		}

		// /COMPAT

		if (substr($uri, 0, self::RESTFUL_BASE_LENGHT) == self::RESTFUL_BASE)
		{
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

			$routes = WdConfig::get_constructed('restful_operations', array(__CLASS__, 'restful_operations_constructor'), 'routes');

			foreach ($routes as $pattern => $route)
			{
				$match = WdRoute::match($uri, $pattern);

				if (!$match)
				{
					continue;
				}

				if (is_array($match))
				{
					$request += $match;
				}

				$operation = new WdOperation($route, $pattern, $request);

				$operation->terminus = true;
				$operation->method = 'GET';

				return $operation;
			}

			#
			#
			#

			preg_match('#^([a-z\.]+)/(([^/]+)/)?([a-zA-Z0-9_\-]+)$#', substr($uri, self::RESTFUL_BASE_LENGHT), $matches);

			if ($matches)
			{
				list(, $destination, , $operation_key, $name) = $matches;

				$name = strtr($name, '-', '_');

				$request[self::KEY] = $matches[2] ? $operation_key : null;
			}
			else
			{
				throw new WdException('Uknown operation: %operation', array('%operation' => substr($uri, 4)), array(404 => 'Unknow operation'));
			}
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

	/**
	 * Constructs the configuration "restful_operations" by filtering RESTful routes from the
	 * "routes" config.
	 *
	 * @param array $fragments Configuration fragments.
	 */

	static public function restful_operations_constructor(array $fragments)
	{
		$routes = array();

		foreach ($fragments as $fragment)
		{
			foreach ($fragment as $pattern => $route)
			{
				if (substr($pattern, 0, self::RESTFUL_BASE_LENGHT) != self::RESTFUL_BASE)
				{
					continue;
				}

				$routes[$pattern] = $route;
			}
		}

		krsort($routes);

		return $routes;
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
		global $core;

		$name = $this->name;

		if ($name{0} == '/')
		{
			$name = substr($name, self::RESTFUL_BASE_LENGHT);
		}

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
		$module = is_array($destination) ? null : $module = $core->modules[$destination];

		WdEvent::fire
		(
			'operation.' . $name . ':before', array
			(
				'target' => $module,
				'operation' => $this
			)
		);

		if (is_array($destination))
		{
			$rc = call_user_func($destination['callback'], $this);
		}
		else
		{
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

		if ($rc !== null)
		{
			WdEvent::fire
			(
				'operation.' . $name, array
				(
					'target' => $module,
					'operation' => $this,
					'rc' => &$this->response->rc
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

		// FIXME-20101117: using $_SERVER is too global, we have to use an object related property

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
			else if ($this->method == 'GET')
			{
				$rc = $this->response->rc;
			}
		}

		if ($this->location && !headers_sent())
		{
			header('Referer: ' . $_SERVER['REQUEST_URI']);
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

	public function handle_booleans($booleans)
	{
		$params = &$this->params;

		if (!is_array($booleans))
		{
			$booleans = func_get_args();
		}

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