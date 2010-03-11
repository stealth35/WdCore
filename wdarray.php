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
