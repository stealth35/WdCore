<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class WdRoute
{
	static protected $routes = array();

	/**
	 * Returns the routes defined using the configuration system or added using the add() method.
	 *
	 * @return array
	 */
	static public function routes()
	{
		global $core;
		static $constructed;

		if (!$constructed)
		{
			$constructed = true;

			self::$routes += $core->configs->fuse('routes', array(__CLASS__, 'routes_constructor'));
		}

		return self::$routes;
	}

	/**
	 * Indexes routes, filtering out the route definitions which don't start with '/'
	 *
	 * @param array $fragments Configiration fragments
	 *
	 * @return array
	 */
	static public function routes_constructor(array $fragments)
	{
		$routes = array();

		foreach ($fragments as $fragment)
		{
			foreach ($fragment as $pattern => $route)
			{
				if ($pattern{0} != '/')
				{
					continue;
				}

				$routes[$pattern] = $route;
			}
		}

		return $routes;
	}

	/**
	 * Adds or replaces a route, or a set of routes,
	 *
	 * @param mixed $pattern The pattern for the route to add or replace, or an array of
	 * pattern/route.
	 * @param array $route The route definition for the pattern, or nothing if the pattern is
	 * actually a set of routes.
	 */
	static public function add($pattern, array $route=array())
	{
		if (is_array($pattern))
		{
			self::$routes = $pattern + self::$routes;

			return;
		}

		self::$routes[$pattern] = $route;
	}

	/**
	 * Removes a route from the routes using its pattern.
	 *
	 * @param string $pattern The pattern for the route to remove.
	 */
	static public function remove($pattern)
	{
		self::routes();

		unset(self::$routes[$pattern]);
	}

	static private $parse_cache = array();

	/**
	 * Parses a route pattern and return an array of interleaved paths and parameters, parameters
	 * and the regular expression for the specified pattern.
	 *
	 * @param string $pattern The route pattern.
	 *
	 * @return array
	 */
	static public function parse($pattern)
	{
		if (isset(self::$parse_cache[$pattern]))
		{
			return self::$parse_cache[$pattern];
		}

		$regex = '#^';
		$interleave = array();
		$params = array();
		$n = 0;

		$parts = preg_split('#(:\w+|<(\w+:)?([^>]+)>)#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $j = count($parts); $i < $j ;)
		{
			$part = $parts[$i++];

			$regex .= $part;
			$interleave[] = $part;

			if ($i == $j)
			{
				break;
			}

			$part = $parts[$i++];

			if ($part{0} == ':')
			{
				$identifier = substr($part, 1);
				$selector = '[^/]+';
			}
			else
			{
				$identifier = substr($parts[$i++], 0, -1);

				if (!$identifier)
				{
					$identifier = $n++;
				}

				$selector = $parts[$i++];
			}

			$regex .= '(' . $selector . ')';
			$interleave[] = array($identifier, $selector);
			$params[] = $identifier;
		}

		$regex .= '$#';

		return self::$parse_cache[$pattern] = array($interleave, $params, $regex);
	}

	static public function match($uri, $pattern)
	{
		$parsed = self::parse($pattern);

		list(, $params, $regex) = $parsed;

		$match = preg_match($regex, $uri, $values);

		if (!$match)
		{
			return false;
		}
		else if (!$params)
		{
			return true;
		}

		array_shift($values);

		return array_combine($params, $values);
	}

	static public function find($uri)
	{
		$routes = self::routes();

		foreach ($routes as $pattern => $route)
		{
			$match = self::match($uri, $pattern);

			if (!$match)
			{
				continue;
			}

			return array($route, $match, $pattern);
		}
	}

	/**
	 * Returns a route formated using a pattern and values.
	 *
	 * @param string $pattern The route pattern
	 * @param mixed $values The values to format the pattern, either as an array or an object.
	 *
	 * @return string The formated route.
	 */
	static public function format($pattern, $values=null)
	{
		if (is_array($values))
		{
			$values = (object) $values;
		}

		$url = '';
		$parsed = self::parse($pattern);

		foreach ($parsed[0] as $i => $value)
		{
			$url .= ($i % 2) ? urlencode($values->$value[0]) : $value;
		}

		return $url;
	}

	/**
	 * Checks if the given string is a route pattern.
	 *
	 * @param string $pattern
	 * @return true is the given pattern is a route pattern, false otherwise.
	 */
	static public function is_pattern($pattern)
	{
		return (strpos($pattern, '<') !== false) || (strpos($pattern, ':') !== false);
	}
}