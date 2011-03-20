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
 * Displays information about the core and its modules.
 */
class core__aloha_WdOperation extends WdOperation
{
	protected function validate()
	{
		return true;
	}

	protected function process()
	{
		global $core;

		$enabled = array();
		$disabled = array();

		foreach ($core->modules->descriptors as $module_id => $descriptor)
		{
			if (!empty($descriptor[WdModule::T_DISABLED]))
			{
				$disabled[] = $module_id;

				continue;
			}

			$enabled[] = $module_id;
		}

		sort($enabled);
		sort($disabled);

		header('Content-Type: text/plain; charset=utf-8');

		$rc  = 'WdCore version ' . WdCore::VERSION . ' is running here with:';
		$rc .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $enabled);
		$rc .= PHP_EOL . PHP_EOL . 'Disabled modules:';
		$rc .= PHP_EOL . PHP_EOL . implode(PHP_EOL, $disabled);
		$rc .= PHP_EOL . PHP_EOL . strip_tags(implode(PHP_EOL, WdDebug::fetchMessages('debug')));

		return $rc;
	}
}