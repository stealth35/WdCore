<?php

/**
 * This file is part of the WdPublisher software
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.wdpublisher.com/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.wdpublisher.com/license.html
 */

class WdRoute
{
	static protected $routes = array();

	static public function routes()
	{
		if (!self::$routes)
		{
			self::$routes = WdConfig::get_constructed('routes', array(__CLASS__, 'routes_constructor'));
		}

		return self::$routes;
	}

	static public function routes_constructor($configs)
	{
		global $core;
		static $specials = array('manage', 'create', 'config', 'edit');

		//WdDebug::trigger('routes() called baby');

		$routes = array();

		foreach ($configs as $root => $definitions)
		{
			$local_module_id = null;

			if (basename(dirname($root)) == 'modules')
			{
				$local_module_id = basename($root);
			}

			//wd_log("read routes from $root: $local_module_id" . wd_dump($definitions));

			if (isset($definitions[0]))
			{
				// TODO-20100920: COMPAT, the 'defaults' key should die !

				$definitions = $definitions[0];
			}

			foreach ($definitions as $pattern => $route)
			{
				if (isset($route['block']))
				{
					if (empty($route['module']))
					{
						$route['module'] = $local_module_id;
					}

					if (empty($route['visibility']))
					{
						$route['visibility'] = 'visible';
					}
				}

				if (0 && empty($route['module']) && !in_array($pattern, $specials))
				{
					echo '<h3>no module for <em>' . wd_entities($pattern) . '</em></h3>' . wd_dump($route);
				}

				$module_id = isset($route['module']) ? $route['module'] : $local_module_id;

				if ($module_id && !$core->has_module($module_id))
				{
					continue;
				}

				if (in_array($pattern, $specials))
				{
					$workspace = null;

					if ($module_id && isset($core->descriptors[$module_id]) )
					{
						$descriptor = $core->descriptors[$module_id];

						if (empty($route['workspace']) && isset($descriptor[WdModule::T_CATEGORY]))
						{
							$workspace = $descriptor[WdModule::T_CATEGORY];
						}
						else
						{
							list($workspace) = explode('.', $module_id);
						}
					}

					$route += array
					(
						'module' => $module_id,
						'workspace' => $workspace
					);

					switch ($pattern)
					{
						case 'manage':
						{
							$pattern = "/admin/$module_id";

							$route += array
							(
								'title' => 'Liste',
								'block' => 'manage',
								'index' => true,
								'visibility' => 'visible'
							);
						}
						break;

						case 'create':
						{
							$pattern = "/admin/$module_id/create";

							$route += array
							(
								'title' => 'Nouveau',
								'block' => 'edit',
								'permission' => WdModule::PERMISSION_CREATE,
								'visibility' => 'visible'
							);
						}
						break;

						case 'edit':
						{
							$pattern = "/admin/$module_id/<\d+>/edit";

							$route += array
							(
								'title' => 'Éditer',
								'block' => 'edit',
								'visibility' => 'auto'
							);
						}
						break;

						case 'config':
						{
							$pattern = "/admin/$module_id/config";

							$route += array
							(
								'title' => 'Config.',
								'block' => 'config',
								'permission' => WdModule::PERMISSION_ADMINISTER,
								'visibility' => 'visible'
							);
						}
						break;
					}
				}

				$pattern = strtr
				(
					$pattern, array
					(
						'{self}' => $module_id,
						'{module}' => $module_id,
						'{block}' => isset($route['block']) ? $route['block'] : 'not-defined'
					)
				);

				$routes[$pattern] = $route;
			}
		}

		ksort($routes);

		return $routes;
	}

	static private $parse_cache = array();

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

		if (0)
		{
			$parts = preg_split('#<(\w+)?(:?)(.*?)?>#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

			var_dump($parts);

			$key = null;
			$y = count($parts);

			for ($i = 0 ; $i < $y ; $i++)
			{
				$part = $parts[$i];

				switch ($i % 4)
				{
					case 0:
					{
						$regex .= $part;
						$interleave[] = $part;
					}
					break;

					case 1:
					{
						echo t('start: "\1", "\2", "\3"<br />', array($parts[$i], $parts[$i+1], $parts[$i+2]));

						$i += 2;
					}
					break;

					default:
					{
						echo "I shouldn't be here: " . wd_entities($i . '::' . $part) . '<br />';
					}
					break;
				}
			}
		}
		else
		{
			$parts = preg_split('#<((\w+):)?(.*?)?>#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

	//		var_dump($parts);

			foreach ($parts as $i => $part)
			{
				switch ($i % 4)
				{
					case 0:
					{
						$regex .= $part;
						$interleave[] = $part;
					}
					break;

					case 2:
					{
						if (!$part)
						{
							$part = $n++;
						}

						$interleave[] = array($part, $parts[$i + 1]);
						$params[] = $part;
					}
					break;

					case 3:
					{
						$regex .= '(' . $part . ')';
					}
					break;
				}
			}
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

	static public function find_matching($uri)
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
	 *
	 * Returns a route formated using a pattern and values.
	 *
	 * @param string $pattern The route pattern
	 * @param mixed $values The values to format the pattern, either as an array or an object.
	 */

	static public function format($pattern, $values=array())
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

	// TODO-20100629: this method should die in favour of the format() method.

	static public function encode($route, array $params=array())
	{
		if ($params)
		{
			WdDebug::trigger('params are not implemented');
		}

		//return $_SERVER['SCRIPT_NAME'] . $route;

		return '/admin' . $route;
	}
}