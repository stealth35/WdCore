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

class WdHook
{
	const T_CALLBACK = 0;
	const T_PARAMS = 1;
	const T_PARENT = 'parent';

	static protected $namespaces = array();
	static protected $hooks = array();

	static public function autoconfig()
	{
		$configs = func_get_args();

		foreach ($configs as $config)
		{
			foreach ($config as $ns => $hooks)
			{
				self::$namespaces[$ns][] = $hooks;
			}
		}
	}

	static public function add($ns, $name, $tags)
	{
		self::$hooks[$ns][$name] = new WdHook($tags);
	}

	static public function find($ns, $name)
	{
		if (empty(self::$hooks[$ns][$name]))
		{
			if (empty(self::$namespaces[$ns]))
			{
				throw new WdException('Undefined namespace %ns', array('%ns' => $ns));

				return;
			}

			$descriptor = null;
			$descriptors = self::$namespaces[$ns];

			foreach ($descriptors as $descriptor)
			{
				if (empty($descriptor[0][$name]))
				{
					continue;
				}

				break;
			}

			if (empty($descriptor[0][$name]))
			{
				throw new WdException('Undefined hook %name in namespace %ns', array('%name' => $name, '%ns' => $ns));

				return;
			}

			#
			# create hook
			#

			if (isset($descriptor['namespace']))
			{
				WdDebug::trigger('The "namepsace" feature is deprecated, please update your descriptor: \1', array($descriptor));
			}


			$classname = isset($descriptor['namespace']) ? $descriptor['namespace'] : null;

			if (isset($descriptor['require']))
			{
				WdDebug::trigger('Please use WdCore autoload feature instead of the WdDebug require feature: \1', array($descriptor));

				require_once $descriptor['require'];
			}

			#
			#
			#

			$definition = $descriptor[0][$name];

			if (!is_array($definition))
			{
				throw new WdException
				(
					'Hook definition must be an array, %definition given', array
					(
						'%definition' => $definition
					)
				);
			}

			list($callback) = $definition;

			if ($classname && is_string($callback))
			{
				$definition[0] = array($classname, $callback);
			}

			self::$hooks[$ns][$name] = new WdHook($definition);
		}

		return self::$hooks[$ns][$name];
	}

	/*
	 * object
	 */

	public $tags = array();
	private $definitions = array();

	public function __construct(array $tags)
	{
		$this->tags = $tags;

		foreach ($tags as $tag => $value)
		{
			switch ($tag)
			{
				case self::T_CALLBACK:
				{
					$this->callback = $value;
				}
				break;

				case self::T_PARENT:
				{
					$this->parent = $value;
				}
				break;

				case self::T_PARAMS:
				{
					foreach ($value as $name => $definition)
					{
						if (!is_array($definition))
						{
							$definition = array('default' => $definition);
						}

						if (!array_key_exists('default', $definition))
						{
							$definition['default'] = null;
						}

						foreach ($definition as $key => $value)
						{
							$this->definitions[$key][$name] = $value;
						}
					}
				}
				break;
			}
		}

//		echo l('<h3>hook</h3>\1', $this);
	}

	public function call(array $params=array())
	{
		$args = func_get_args();

		array_shift($args);
		/*
		foreach ($params as $i => $value)
		{
			if (!is_numeric($i))
			{
				continue;
			}

			$args[$i] = $value;

			unset($params[$i]);
		}

		$this->params = $this->checkParams($params);
		*/

		$clone = clone $this;

		$clone->params = $params;

		array_unshift($args, $clone);

		$rc = call_user_func_array($clone->callback, $args);

		unset($clone);

		return $rc;
	}

	public function checkParams($params)
	{
		if (empty($this->definitions['default']))
		{
			return $params;
		}

		$defaults = $this->definitions['default'];

		#
		# check for extraneous parameters
		#

		foreach ($params as $name => $value)
		{
			if (!array_key_exists($name, $defaults))
			{
				WdDebug::trigger
				(
					'Extraneous parameter %name with value: !value', array
					(
						'%name' => $name,
						'!value' => $value
					)
				);

				continue;
			}
		}

		#
		# check mandatory attributes
		#

		if (isset($this->definitions['mandatory']))
		{
			foreach ($this->definitions['mandatory'] as $name => $value)
			{
				if (!$value)
				{
					continue;
				}

				if (!array_key_exists($name, $params))
				{
					WdDebug::trigger
					(
						'The %name parameter is mandatory', array
						(
							'%name' => $name
						)
					);
				}
			}
		}

		return $params + $defaults;
	}

	public function getDefinition($definition)
	{
		return empty($this->definitions[$definition]) ? array() : $this->definitions[$definition];
	}
}