<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class WdSession
{
	/**
	 * Returns the session object.
	 *
	 * This is the getter for the `session` property injected in the {@link WdCore} class.
	 *
	 * @param WdCore $core
	 * @return WdSession
	 */
	static public function hook_get_session(WdCore $core)
	{
		$session_name = $core->config['session_id'];

		$session = new WdSession
		(
			array
			(
				'id' => isset($_POST[$session_name]) ? $_POST[$session_name] : null,
				'name' => $session_name
			)
		);

		// TODO-20100525: we restore _by hand_ the messages saved by the WdDebug class.
		// I'm not sure this is the right place for this.
		// Maybe we could trigger an event 'application.session.load', giving a chance to others
		// to handle the session, with a 'application.session.load:before' too.

		WdEvent::fire('application.session.load', array('application' => $core, 'session' => $session));

		if (isset($session->wddebug['messages']))
		{
			WdDebug::$messages = array_merge($session->wddebug['messages'], WdDebug::$messages);
		}

		return $session;
	}

	/**
	 * Checks if a session identifier can be found to retrieve a session.
	 *
	 * @return bool true if the session identifier exists in the cookie, false otherwise.
	 */
	static public function exists()
	{
		global $core;

		return !empty($_COOKIE[$core->config['session_id']]);
	}

	/**
	 * Constructor.
	 *
	 * The constructor is protected, only the `session` getter injected in the {@link WdCore} class
	 * can create an instance of the class.
	 *
	 * @param array $options
	 */
	protected function __construct(array $options=array())
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
			'cache_limiter' => null,
			'module_name' => 'files'
		)

		+ session_get_cookie_params();

		$id = $options['id'];

		if ($id)
		{
			session_id($id);
		}

		session_name($options['name']);
		session_set_cookie_params($options['lifetime'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);

		if ($options['cache_limiter'] !== null)
		{
			session_cache_limiter($options['cache_limiter']);
		}

		if($options['module_name'] != session_module_name())
		{
			session_module_name($options['module_name']);
		}

		if($options['use_trans_sid'])
		{
			output_add_rewrite_var(session_name(), session_id());
		}
		else
		{
			output_reset_rewrite_vars();
		}

		session_start();
	}

	/**
	 * Regenerates the id of the session.
	 */
	public function regenerate_id($delete_old_session=false)
	{
		return session_regenerate_id($delete_old_session);
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