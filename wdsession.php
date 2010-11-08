<?php

class WdSession
{
	static public function hook_get_session($caller)
	{
		// FIXME-20100708: use config

		$session_name = 'wdsid';

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
		// Maybe we could trigger an event 'application.session.load', giving a chance to other
		// to handle the session, with a 'application.session.load:before' too.

		WdEvent::fire('application.session.load', array('application' => $caller, 'session' => $session));

		if (isset($session->wddebug['messages']))
		{
			WdDebug::$messages = array_merge($session->wddebug['messages'], WdDebug::$messages);
		}

		return $session;
	}

	#
	#
	#

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