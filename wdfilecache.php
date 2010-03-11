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

// @TODO implement time rules

class WdFileCache
{
	const _VERSION = '0.10.0';

	const T_COMPRESS = 'compress';
	const T_REPOSITORY = 'repository';
	const T_REPOSITORY_DELETE_RATIO = 'repository_delete_ratio';
	const T_REPOSITORY_SIZE = 'repository_size';
	const T_SERIALIZE = 'serialize';

	public $compress = false;
	public $repository;
	public $repository_delete_ratio = .25;
	public $repository_size = 512;
	public $serialize = false;

	protected $root;

	public function __construct(array $tags)
	{
		if (empty($tags[self::T_REPOSITORY]))
		{
			throw new WdException('The %tag tag is mandatory', array('%tag' => 'T_REPOSITORY'));
		}

		foreach ($tags as $tag => $value)
		{
			$this->$tag = $value;
		}

		$this->root = realpath($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $this->repository);
	}

	/**
	 * Check if a file exists in the repository.
	 *
	 * If the file doesn't exists, it's created using the provided constructor.
	 *
	 * The working directory is changed to the repository during the process.
	 *
	 * @param $file The name of the file in the repository.
	 *
	 * @param $constructor The constructor for the file.
	 * The constructor is called with the cache object, the name of the file and the userdata.
	 *
	 * @param $userdata Userdata that will be passed to the constructor.
	 *
	 * @return mixed The URL of the file. FALSE is the file failed to be created.
	 */

	public function get($file, $constructor, $userdata=null)
	{
		if (!is_dir($this->root))
		{
			wd_log('The repository %repository does not exists', array('%repository' => $this->repository));

			return false;
		}

		$location = getcwd();

		chdir($this->root);

		if (!is_file($file))
		{
			$file = call_user_func($constructor, $this, $file, $userdata);
		}

		chdir($location);

		return $file ? $this->repository . '/' . $file : $file;
	}

	/**
	 * Load cached contents.
	 *
	 * If the content is not cached, the constructor to create the content is called.
	 * The content generated by the constructor is save to the cache.
	 *
	 * @param $file string Name of the cache file.
	 * @param $contructor callback Constructor callback. The constructor is called to
	 * generated the contents of the file. The constructor is called with the WdFileCache
	 * object, the @file and the @userdata as arguments.
	 * @param $userdata mixed User data that is passed as is to the constructor.
	 * @return mixed The contents of the file
	 */

	public function load($file, $contructor, $userdata=null)
	{
		#
		# if the repository does not exists we simply return the contents
		# created by the constructor.
		#

		if (!is_dir($this->root))
		{
			wd_log('The repository %repository does not exists', array('%repository' => $this->repository));

			return call_user_func($contructor, $this, $file, $userdata);
		}

		#
		#
		#

		$location = getcwd();

		chdir($this->root);

		$contents = null;

		if (is_readable($file))
		{
			$contents = file_get_contents($file);

			if ($this->compress)
			{
				$contents = gzinflate($contents);
			}

			if ($this->serialize)
			{
				$contents = unserialize($contents);
			}
		}

		if ($contents === null)
		{
			$contents = call_user_func($contructor, $this, $file, $userdata);

			$this->save($file, $contents);
		}

		chdir($location);

		return $contents;
	}

	/**
	 * Save contents to a cached file.
	 * @param $file string Name of the file.
	 * @param $contents mixed The contents to write.
	 * @return int Return value from @file_put_contents()
	 */

	protected function save($file, $contents)
	{
		if (!is_writable($this->root))
		{
			WdDebug::trigger('Repository %repository is not writable', array('%repository' => $this->repository));

			return false;
		}

		$location = getcwd();

		chdir($this->root);

		if ($this->serialize)
		{
			$contents = serialize($contents);
		}

		if ($this->compress)
		{
			$contents = gzdeflate($contents);
		}

		$rc = file_put_contents($file, $contents, LOCK_EX);

		chdir($location);

		return $rc;
	}

	public function delete($file)
	{
		return $this->unlink(array($file => true));
	}

	/**
	 * Read to repository and return an array of files.
	 *
	 * Each entry in the array is made up using the _ctime_ and _size_ of the file. The
	 * key of the entry is the file name.
	 *
	 * @return unknown_type
	 */

	protected function read()
	{
		$root = $this->root;

		if (!is_dir($root))
		{
			//WdDebug::trigger('%repository is not a directory', array('%repository' => $this->repository));

			return false;
		}

		$dh = opendir($root);

		if (!$dh)
		{
			return false;
		}

		#
		# create file list, with the filename as key and ctime and size as value.
		# we set the ctime first to be able to sort the file by ctime when necessary.
		#

		$location = getcwd();

		chdir($root);

		$files = array();

		while (($file = readdir($dh)) !== false)
		{
			if ($file{0} == '.')
			{
				continue;
			}

			$stat = stat($file);

			$files[$file] = array($stat['ctime'], $stat['size']);
		}

		closedir($dh);

		chdir($location);

		return $files;
	}

	protected function unlink($files)
	{
		if (!$files)
		{
			return;
		}

		#
		# change the working directory to the repository
		#

		$location = getcwd();

		chdir($this->root);

		#
		# obtain exclusive lock to delete files
		#

		$lh = fopen('.lock', 'w+');

		if (!$lh)
		{
			WdDebug::trigger('Unable to lock %repository', array('%repository' => $this->repository));

			chdir($location);

			return;
		}

		#
		# We will try $n time to obtain the exclusive lock
		#

		$n = 10;

		while (!flock($lh, LOCK_EX | LOCK_NB))
		{
			#
			# If the lock is not obtained we sleep for 0 to 100 milliseconds.
			# We sleep to avoid CPU load, and we sleep for a random time
			# to avoid collision.
			#

			usleep(round(rand(0, 100) * 1000));

			if (!--$n)
			{
				#
				# We were unable to obtain the lock in time.
				# We exit silently.
				#

				chdir($location);

				return;
			}
		}

		#
		# The lock was obtained, we can now delete the files
		#

		foreach ($files as $file => $dummy)
		{
			#
			# Because of concurent access, the file might have already been deleted.
			# We have to check if the file still exists before calling unlink()
			#

			if (!file_exists($file))
			{
				continue;
			}

			unlink($file);
		}

		chdir($location);

		#
		# and release the lock.
		#

		fclose($lh);
	}

	/**
	 *
	 * Clear all the files in the repository.
	 *
	 */

	public function clear()
	{
		$files = $this->read();

		return $this->unlink($files);
	}

	/**
	 *
	 * Clean the repository according to the size and time rules.
	 *
	 */

	public function clean()
	{
		$files = $this->read();

		if (!$files)
		{
			return;
		}

		$totalsize = 0;

		foreach ($files as $stat)
		{
			$totalsize += $stat[1];
		}

		$repository_size = $this->repository_size * 1024;

		if ($totalsize < $repository_size)
		{
			#
			# There is enough space in the repository. We don't need to delete any file.
			#

			return;
		}

		#
		# The repository is completely full, we need to make some space.
		# We create an array with the files to delete. Files are added until
		# the delete ratio is reached.
		#

		asort($files);

		$deletesize = $repository_size * $this->repository_delete_ratio;

		$i = 0;

		foreach ($files as $file => $stat)
		{
			$i++;

			$deletesize -= $stat[1];

			if ($deletesize < 0)
			{
				break;
			}
		}

		$files = array_slice($files, 0, $i);

		return $this->unlink($files);
	}
}