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
	const SESSION_LOGGED_USER_ID = 'app.loggedUserId';

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
}