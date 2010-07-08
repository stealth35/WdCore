<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdApplication
{
	public function __get($property)
	{
		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		WdDebug::trigger
		(
			'Unknow property %property for object of class %class', array
			(
				'%property' => $property, '%class' => get_class($this)
			)
		);
	}

	protected function __get_user()
	{
		throw new WdException
		(
			'The %method method needs to be implemented by a subclass', array
			(
				'%method' => __FUNCTION__
			)
		);
	}

	/**
	 * Return the user's id.
	 */

	protected function __get_user_id()
	{
		return isset($this->session->application['user_id']) ? $this->session->application['user_id'] : null;
	}

	protected function __get_session()
	{
		// TODO-20100708: use config

		$session_name = 'wdsid';

		$session = new WdApplicationSession
		(
			array
			(
				'id' => isset($_POST[$session_name]) ? $_POST[$session_name] : null,
				'name' => $session_name
			)
		);

		// FIXME-20100525: we restore _by hand_ the messages saved by the WdDebug class.

		if (isset($this->session->wddebug['messages']))
		{
			foreach ($this->session->wddebug['messages'] as $type => $messages)
			{
				foreach ($messages as $id => $pair)
				{
					list($message, $params) = $pair;

					WdDebug::putMessage($type, $message, $params, $id);
				}
			}
		}

		return $session;
	}
}

class WdApplicationSession
{
	public function __construct(array $options=array())
	{
		if (session_id())
		{
			return;
		}

		$options += array
		(
			'id' => null,
			'name' => 'wdsid',
			'use_cookies' => true,
			'use_only_cookies' => true,
			'use_trans_sid' => false,
			'cache_limiter' => null
		)

		+ session_get_cookie_params();

		$id = $options['id'];

		if ($id)
		{
			session_id($id);
		}

		ini_set('session.use_trans_sid', $options['use_trans_sid']);

		session_name($options['name']);
		session_set_cookie_params($options['lifetime'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);

		if ($options['cache_limiter'] !== null)
		{
			session_cache_limiter($options['cache_limiter']);
		}

		session_start();
	}

	public function &__get($property)
	{
		return $_SESSION[$property];
	}

	public function __set($property, $value)
	{
		$_SESSION[$property] = $value;
	}

	public function __isset($property)
	{
		return array_key_exists($property, $_SESSION);
	}

	public function __unset($property)
	{
		unset($_SESSION[$property]);
	}
}