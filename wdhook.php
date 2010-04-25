<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdHook
{
	static protected $hooks = array();

	static public function configConstructor($configs)
	{
		$by_ns = array();
		
		foreach ($configs as $config)
		{
			foreach ($config as $namespace => $hooks)
			{
				if (isset($hooks[0]))
				{
					wd_log('COMPAT: double array no longer needed: \1', array($hooks));
					
					$hooks = array_shift($hooks);
				}
				
				foreach ($hooks as $name => $definition)
				{
					list($callback, $params) = $definition + array(1 => array());

					unset($definition[0]);
					unset($definition[1]);
					
					$hook = new WdHook($callback, $params, $definition);
					
					$by_ns[$namespace . '∋' . $name] = $hook;
				}
			}
		}
		
		#
		# the (object) cast is a workaround for an APC bug: http://pecl.php.net/bugs/bug.php?id=8118
		#
		
		return (object) $by_ns;
	}

	static public function find($ns, $name)
	{
		if (!self::$hooks)
		{
			#
			# the (array) cast is a workaround for an APC bug: http://pecl.php.net/bugs/bug.php?id=8118
			#
			
			self::$hooks = (array) WdCore::getConstructedConfig('hook', array(__CLASS__, 'configConstructor'));
		}
		
		if (empty(self::$hooks[$ns . '∋' . $name]))
		{
			throw new WdException('Undefined hook %name in namespace %ns', array('%name' => $name, '%ns' => $ns));
		}

		return self::$hooks[$ns . '∋' . $name];
	}

	public $callback;
	public $params = array();
	public $tags = array();
	
	public function __construct($callback, array $params=array(), array $tags=array())
	{
		$this->callback = $callback;
		$this->params = $params;
		$this->tags = $tags;
	}

	public $args = array();
	
	public function call(array $args=array())
	{
		$user_args = func_get_args();

		array_shift($user_args);
		
		$clone = clone $this;

		$clone->args = $args;

		array_unshift($user_args, $clone);

		$rc = call_user_func_array($clone->callback, $user_args);

		unset($clone);

		return $rc;
	}
}