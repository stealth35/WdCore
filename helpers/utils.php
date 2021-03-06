<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

defined('WDCORE_CHARSET') or define('WDCORE_CHARSET', 'utf-8');

if (function_exists('mb_internal_encoding'))
{
	mb_internal_encoding(WDCORE_CHARSET);
}

function wd_entities($str, $charset=WDCORE_CHARSET)
{
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

	$str = preg_replace('#&([A-za-z])(?:acute|cedil|circ|grave|orn|ring|slash|th|tilde|uml);#', '\1', $str);
	$str = preg_replace('#&([A-za-z]{2})(?:lig);#', '\1', $str); // pour les ligatures e.g. '&oelig;'
	$str = preg_replace('#&[^;]+;#', '', $str); // supprime les autres caractères

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

function wd_strip_slashes_recursive($value)
{
	return is_array($value) ? array_map(__FUNCTION__, $value) : stripslashes($value);
}

function wd_kill_magic_quotes()
{
	if (get_magic_quotes_gpc())
	{
		$_GET = array_map('wd_strip_slashes_recursive', $_GET);
		$_POST = array_map('wd_strip_slashes_recursive', $_POST);
		$_COOKIE = array_map('wd_strip_slashes_recursive', $_COOKIE);
		$_REQUEST = array_map('wd_strip_slashes_recursive', $_REQUEST);
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

		$value = ob_get_clean();
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
	$allowed_tags = array
	(
		'a', 'p', 'code', 'del', 'em', 'ins', 'strong'
	);

	$str = strip_tags((string) $str, '<' . implode('><', $allowed_tags) . '>');

	$parts = preg_split('#<([^\s>]+)([^>]*)>#m', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

	# i+0: text
	# i+1: markup ('/' prefix for closing markups)
	# i+2: markup attributes

	$rc = '';
	$opened = array();

	foreach ($parts as $i => $part)
	{
		if ($i % 3 == 0)
		{
			$words = preg_split('#(\s+)#', $part, 0, PREG_SPLIT_DELIM_CAPTURE);

//			var_dump($words);

			foreach ($words as $w => $word)
			{
				if ($w % 2 == 0)
				{
					if (!$word) // TODO-20100908: strip punctuation
					{
						continue;
					}

					$rc .= $word;

					if (!--$limit)
					{
						break;
					}
				}
				else
				{
					$rc .= $word;
				}
			}

			if (!$limit)
			{
				break;
			}
		}
		else if ($i % 3 == 1)
		{
			if ($part[0] == '/')
			{
				$rc .= '<' . $part . '>';

				array_shift($opened);
			}
			else
			{
				array_unshift($opened, $part);

				$rc .= '<' . $part . $parts[$i + 1] . '>';
			}
		}
	}

	if (!$limit)
	{
		$rc .= ' […]';
	}

	if ($opened)
	{
		$rc .= '</' . implode('></', $opened) . '>';
	}

	return $rc;
}


function wd_camelize($str, $separator='-')
{
	static $callback;

	if (!$callback)
	{
		$callback = create_function('$match', 'return mb_strtoupper(mb_substr($match[0], 1));');
	}

	return preg_replace_callback('/' . preg_quote($separator) . '\D/', $callback, $str);
}

function wd_hyphenate($str)
{
	static $callback;

	if (!$callback)
	{
		$callback = create_function('$match', 'return "-" . mb_strtolower(mb_substr($match[0], 0, 1));');
	}

	return trim(preg_replace_callback('/[A-Z]/', $callback, $str), '-');
}

function wd_shorten($str, $length=32, $position=.75, &$shortened=null)
{
	$l = mb_strlen($str);

	if ($l <= $length)
	{
		return $str;
	}

	$length--;
	$position = (int) ($position * $length);

	if ($position == 0)
	{
		$str = '…' . mb_substr($str, $l - $length);
	}
	else if ($position == $length)
	{
		$str = mb_substr($str, 0, $length) . '…';
	}
	else
	{
		$str = mb_substr($str, 0, $position) . '…' . mb_substr($str, $l - ($length - $position));
	}

	$shortened = true;

	return $str;
}

function wd_strip_root($str)
{
	return substr($str, strlen($_SERVER['DOCUMENT_ROOT']));
}

function wd_isolated_require($__file__, $__exposed__)
{
	extract($__exposed__);

	return require $__file__;
}