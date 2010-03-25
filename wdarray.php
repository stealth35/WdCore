<?php

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdArray
{
	static public function flatten(array $array)
	{
//		$result = array(); FIXME: not sure about all of this :-)
		$result = $array;

		foreach ($array as $key => &$value)
		{
			self::flatten_callback($result, '', $key, $value);
		}

		return $result;
	}

	static private function flatten_callback(&$result, $pre, $key, &$value)
	{
		if (is_array($value))
		{
			foreach ($value as $vk => &$vv)
			{
				self::flatten_callback($result, $pre ? ($pre . '[' . $key . ']') : $key, $vk, $vv);
			}
		}
		else if (is_object($value))
		{
			// FIXME: WdDebug::trigger('Don\'t know what to do with objects: \1', $value);
		}
		else
		{
			#
			# a trick to create undefined values
			#

			if (!strlen($value))
			{
				$value = null;
			}

			if ($pre)
			{
				#
				# only arrays are flattened
				#

				$pre .= '[' . $key . ']';

				$result[$pre] = $value;
			}
			else
			{
				#
				# simple values are kept intact
				#

				$result[$key] = $value;
			}
		}
	}

	static public function groupBy($key, $array)
	{
		$group = array();

		foreach ($array as $sub)
		{
			$value = is_object($sub) ? $sub->$key : $sub[$key];

			$group[$value][] = $sub;
		}

		return $group;
	}

	static public function reorderByProperty(array $entries, array $order, $property)
	{
		$by_property = array();

		foreach ($entries as $entry)
		{
			$by_property[is_object($entry) ? $entry->$property : $entry[$property]] = $entry;
		}

		$rc = array();

		foreach ($order as $o)
		{
			if (array_key_exists($o, $by_property[$o]))
			{
				continue;
			}

			$rc[] = $by_property[$o];
		}

		return $rc;
	}
}

function wd_array_by_columns(array $array, $columns, $pad=false)
{
	$values_by_columns = ceil(count($array) / $columns);

	$i = 0;
	$by_columns = array();

	foreach ($array as $value)
	{
		$by_columns[$i++ % $values_by_columns][] = $value;
	}

	$finish = array();

	foreach ($by_columns as $column)
	{
		if ($pad)
		{
			$column = array_pad($column, $columns, null);
		}

		foreach ($column as $value)
		{
			$finish[] = $value;
		}
	}

	return $finish;
}

/*
			foreach ($entries as $key => $entry)
			{
				$rc_columns[$i++ % $by_columns][$key] = $entry;
			}

			var_dump($rc_columns);

			$real_finish = array();

			foreach ($rc_columns as $column)
			{
				$count = count($column);

				if ($pad)
				{
					//$column = array_pad($column, $columns, null);



				}

				foreach ($column as $key => $value)
				{
					$real_finish[] = $key . '-' . $value;
				}
			}
			*/
