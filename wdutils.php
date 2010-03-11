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

defined('WDCORE_CHARSET') or define('WDCORE_CHARSET', 'utf-8');

if (function_exists('mb_internal_encoding'))
{
	mb_internal_encoding(WDCORE_CHARSET);
}

function wd_entities($str, $charset=WDCORE_CHARSET)
{
	if (is_array($str))
	{
		WdDebug::trigger('str must be a string, array given: \1', array($str));

		return 'wd-entities-debug';
	}

	return htmlspecialchars($str, ENT_COMPAT, $charset);
}

function wd_entities_all($str, $charset=WDCORE_CHARSET)
{
	return htmlentities($str, ENT_COMPAT, $charset);
}

function wd_create_cloud($tags, $callback)
{
	if (empty($tags))
	{
		return;
	}

	$min = min(array_values($tags));
	$max = max(array_values($tags));

	$mid = ($max == $min) ? 1 : $max - $min;

	$rc = '';

	foreach ($tags as $tag => $value)
	{
		$rc .= call_user_func($callback, $tag, $value, ($value - $min) / $mid);
	}

	return $rc;
}

function wd_remove_accents($str, $charset=WDCORE_CHARSET)
{
    $str = htmlentities($str, ENT_NOQUOTES, $charset);

    $str = preg_replace('#\&([A-za-z])(?:uml|circ|tilde|acute|grave|cedil|ring)\;#', '\1', $str);
    $str = preg_replace('#\&([A-za-z]{2})(?:lig)\;#', '\1', $str); // pour les ligatures e.g. '&oelig;'
    $str = preg_replace('#\&[^;]+\;#', '', $str); // supprime les autres caractéres

    return $str;
}

function wd_unaccent_compare($a, $b)
{
    return strcmp(wd_remove_accents($a), wd_remove_accents($b));
}

function wd_unaccent_compare_ci($a, $b)
{
    return strcmp(strtolower(wd_remove_accents($a)), strtolower(wd_remove_accents($b)));
}

function wd_normalize($str, $separator='-', $charset=WDCORE_CHARSET)
{
	$str = str_replace('\'', '', $str);
	$str = wd_remove_accents($str, $charset);
	$str = strtolower($str);
	$str = preg_replace('#[^a-z0-9]+#', $separator, $str);
	$str = trim($str, $separator);

	return $str;
}

function wd_discard_substr_by_length($string, $len=3, $separator='-')
{
	if (!$len)
	{
		return $string;
	}

	$ar = explode($separator, $string);
	$ar = array_map('trim', $ar);

	foreach ($ar as $i => $value)
	{
		if (is_numeric($value))
		{
			continue;
		}

		if (strlen($value) < $len)
		{
			unset($ar[$i]);
		}
	}

	$string = implode($separator, $ar);

	return $string;
}

function wd_deepStripSlashes($value)
{
	return is_array($value) ? array_map(__FUNCTION__, $value) : stripslashes($value);
}

function wd_killMagicQuotes()
{
	if (get_magic_quotes_gpc())
	{
		$_GET = array_map('wd_deepStripSlashes', $_GET);
		$_POST = array_map('wd_deepStripSlashes', $_POST);
		$_COOKIE = array_map('wd_deepStripSlashes', $_COOKIE);
		$_REQUEST = array_map('wd_deepStripSlashes', $_REQUEST);

		ini_set('magic_quotes_gpc', 'Off');
	}
}

function wd_spamScore($body, $url, $author, $words=array(), $starters=array())
{
	#
	# score >= 1 - The message doesn't look like spam
	# score == 0 - The message should be put to moderation
	# score < 10 - The message is most certainly spam
	#

	$score = 0;

	#
	# put our body in lower case for checking
	#

	$body = strtolower($body);

	#
	# how many links are in the body ?
	#

	$n = max
	(
		array
		(
			substr_count($body, 'http://'),
			substr_count($body, 'href'),
			substr_count($body, 'ftp')
		)
	);

	if ($n > 2)
	{
		#
		# more than 2 : -1 point per link
		#

		$score -= $n;
	}
	else
	{
		#
		# 2 or less : +2 points
		#

		$score += 2;
	}

	#
	# Keyword search
	#

	$words = array_merge
	(
		$words, array
		(
			'levitra', 'viagra', 'casino', 'porn', 'sex', 'tape'
		)
	);

	foreach ($words as $word)
	{
		$n = substr_count($body, $word);

		if (!$n)
		{
			continue;
		}

		$score -= $n;
	}

	#
	# now remove links
	#

	# html style: <a> <a/>

	$body = preg_replace('#\<a\s.+\<\/a\>#', NULL, $body);

	# bb style: [url] [/url]

	$body = preg_replace('#\[url.+\/url\]#', NULL, $body);

	# remaining addresses: http://

	$body = preg_replace('#http://[^\s]+#', NULL, $body);

	#
	# how long is the body ?
	#

	$l = strlen($body);

	if ($l > 20 && $n = 0)
	{
		#
		# More than 20 characters and there's no links : +2 points
		#

		$score += 2;
	}
	else if ($l < 20)
	{
		#
		# Less than 20 characters : -1 point
		#

		$score--;
	}

	#
	# URL length
	#

	if (strlen($url) > 32)
	{
		$score--;
	}

	#
	# Body starts with...
	#

	$starters += array
	(
		'interesting', 'sorry', 'nice', 'cool', 'hi'
	);

	foreach ($starters as $word)
	{
		$pos = strpos($body, $word . ' ');

		if ($pos === false)
		{
			continue;
		}

		if ($pos > 10)
		{
			continue;
		}

		$score -= 10;

		break;
	}

	#
	# Author name has 'http://' in it
	#

	if (strpos($author, 'http://'))
	{
		$score -= 2;
	}

	#
	# How many different words are used
	#

	$count = str_word_count($body);

	if ($count < 10)
	{
		$score -= 5;
	}

	return $score;

	# TODO:
	#
	# Number of previous comments from email
	#
	# 	-> Approved comments : +1 per comment
	#	-> Marked as spam : -1 per comment
	#
	# Body used in previous comment
	#
}

function wd_dump($value)
{
	if (function_exists('xdebug_var_dump'))
	{
		ob_start();

		xdebug_var_dump($value);

		$value = ob_get_contents();

		ob_end_clean();

		/*
		$value = strtr
		(
			$value, array
			(
				'<font color=\'' => '<span style="color:',
				'</font>' => '</span>',
				'\'>' => '">',
				'<small>' => '<span style="font-size:0.8em">',
				'</small>' => '</span>'
			)
		);
		*/
	}
	else
	{
		$value = '<pre>' . wd_entities(print_r($value, true)) . '</pre>';
	}

	return $value;
}

function wd_array_merge_recursive(array $array1, array $array2=array())
{
	$arrays = func_get_args();

	$merge = array_shift($arrays);

	foreach ($arrays as $array)
	{
		foreach ($array as $key => $val)
		{
			#
			# if the value is an array and the key already exists
			# we have to make a recursion
			#

			if (is_array($val) && array_key_exists($key, $merge))
			{
				$val = wd_array_merge_recursive((array) $merge[$key], $val);
			}

			#
			# if the key is numeric, the value is pushed. Otherwise, it replaces
			# the value of the _merge_ array.
			#

			if (is_numeric($key))
			{
				$merge[] = $val;
			}
			else
			{
				$merge[$key] = $val;
			}
		}
	}

	return $merge;
}

/**
 * Sort an array of arrays using a member of the arrays.
 *
 * Unlike the new sort, the order of the array with the same value are preserved.
 *
 * @param $array
 * @param $by
 * @param $callback
 * @return array The array sorted.
 */

function wd_array_sort_by(&$array, $by, $callback='ksort')
{
	$sorted_by = array();

	foreach ($array as $key => $value)
	{
		$sorted_by[$value[$by]][$key] = $value;
	}

	$callback($sorted_by);

	$array = array();

	foreach ($sorted_by as $sorted)
	{
		$array += $sorted;
	}

	return $array;
}

function wd_array_sort_and_filter($filter, array $array1)
{
	#
	# `filter` is provided as an array of values, but because we need keys we have to flip it.
	#

	$filter = array_flip($filter);

	#
	# multiple arrays can be provided, they are all merged with the `filter` as first array so that
	# values appear in the order defined in `filter`.
	#

	$arrays = func_get_args();

	array_shift($arrays);
	array_unshift($arrays, $filter);

	$merged = call_user_func_array('array_merge', $arrays);

	#
	# Now we can filter the array using the keys defined in `filter`.
	#

	return array_intersect_key($merged, $filter);
}

function wd_array_to_xml($array, $parent='root', $encoding='utf-8', $nest=1)
{
	$rc = '';

	if ($nest == 1)
	{
		#
		# first level, time to write the XML header and open the root markup
		#

		$rc .= '<?xml version="1.0" encoding="' . $encoding . '"?>' . PHP_EOL;
		$rc .= '<' . $parent . '>' . PHP_EOL;
	}

	$tab = str_repeat("\t", $nest);

	if (substr($parent, -3, 3) == 'ies')
	{
		$collection = substr($parent, 0, -3) . 'y';
	}
	else if (substr($parent, -2, 2) == 'es')
	{
		$collection = substr($parent, 0, -2);
	}
	else if (substr($parent, -1, 1) == 's')
	{
		$collection = substr($parent, 0, -1);
	}
	else
	{
		$collection = 'entry';
	}

	foreach ($array as $key => $value)
	{
		if (is_numeric($key))
		{
			$key = $collection;
		}

		if (is_array($value) || is_object($value))
		{
			$rc .= $tab . '<' . $key . '>' . PHP_EOL;
			$rc .= wd_array_to_xml((array) $value, $key, $encoding, $nest + 1);
			$rc .= $tab . '</' . $key . '>' . PHP_EOL;

			continue;
		}

		#
		# if we find special chars, we put the value into a CDATA section
		#

		if (strpos($value, '<') !== false || strpos($value, '>') !== false || strpos($value, '&') !== false)
		{
			$value = '<![CDATA[' . $value . ']]>';
		}

		$rc .= $tab . '<' . $key . '>' . $value . '</' . $key . '>' . PHP_EOL;
	}

	if ($nest == 1)
	{
		#
		# first level, time to close the root markup
		#

		$rc .= '</' . $parent . '>';
	}

	return $rc;
}

function wd_excerpt($str, $limit=55)
{
	$str = strip_tags($str, '<em><strong><code><del><ins>');

	$words = explode(' ', $str, $limit + 1);

	if (count($words) > $limit)
	{
		#
		# If the number of words is greated then the limit, the last member of the array
		# contains the rest of the string, we need to get rid of it.
		#

		array_pop($words);

		#
		# We can now put back those words together and add a little something to indicate that
		# the contents was truncated.
		#

		$str = implode(' ', $words);

		$str .= ' [...]'; // TODO: should be an option
	}

	return $str;
}

function wd_camelCase($str, $separator='-')
{
	return preg_replace_callback('/' . preg_quote($separator) . '\D/', create_function('$match', 'return mb_strtoupper($match[0]{1});'), $str);
}

function wd_shorten($str, $length=32, &$shortened)
{
	$shortened = false;

	if (mb_strlen($str) > $length + 3)
	{
		$length /= 2;
		$str = wd_entities(mb_substr($str, 0, $length)) . '<span class="light">…</span>' . wd_entities(mb_substr($str, -$length, $length));
		$shortened = true;
	}
	else
	{
		$str = wd_entities($str);
	}

	return $str;
}