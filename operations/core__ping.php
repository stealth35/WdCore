<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Keeps the user's session alive. Only already created sessions are kept alive, new sessions
 * are *not* created.
 */
class core__ping_WdOperation extends WdOperation
{
	protected function validate()
	{
		return true;
	}

	protected function process()
	{
		global $core;

		header('Content-Type: text/plain; charset=utf-8');

		if (WdSession::exists())
		{
			$core->session;
		}

		return 'pong';
	}
}