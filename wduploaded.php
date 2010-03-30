<?php

/*

!! THIS CODE IS EXPERIMENTAL !!

*/

/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

class WdUploaded
{
	const ERR_TYPE = 'UPLOAD_ERR_TYPE';

	public $name;
	public $size;
	public $extension;
	public $mime;

	public $location;
	private $is_temporary = true;

	public $er;
	public $er_message;

	public function __construct($key, $accepted_types=null, $mandatory=false, $index=0)
	{
		#
		# does the slot exists ?
		#

		if (empty($_FILES[$key]))
		{
			#
			# the slot does not exixts, if it's mandatory we trigger an error
			#

			if ($mandatory)
			{
				$this->SetError(UPLOAD_ERR_NO_FILE);
			}

			#
			# otherwise we exit peacefully
			#

			return;
		}

		$data = $_FILES[$key];

		#
		# consolide multiple files given the 'index'
		#

		$name = $data['name'];

		if (is_array($name))
		{
			$consolided = array();

			foreach ($data as $key => $nodes)
			{
				$consolided[$key] = $nodes[$index];
			}

			$data = $consolided;
		}

		#
		# if the file has not been downloaded, but is not mandatory
		# we exit peacefully
		#

		if (($data['error'] == UPLOAD_ERR_NO_FILE) && !$mandatory)
		{
			return;
		}

		if ($data['error'] || !is_uploaded_file($data['tmp_name']))
		{
			$this->setError($data['error']);

			return;
		}

		$this->size = $data['size'];

		#
		# separate the file name from its extension
		#

		$pos = strrpos($name, '.');

		if ($pos !== false)
		{
			$this->name = substr($name, 0, $pos);
			$this->extension = strtolower(substr($name, $pos));
		}
		else
		{
			$this->name = $name;
			$this->extenstion = null;
		}

		#
		# translate exotic mime types and extract the extension
		#

		$this->mime = $data['type'];

		if (in_array($this->mime, array('application/octet-stream', 'application/force-download')))
		{
			$extension = substr($this->extension, 1);

			if (isset(self::$mimes_by_extension[$extension]))
			{
				$this->mime = self::$mimes_by_extension[$extension];
			}
		}

		switch ($this->mime)
		{
			case 'image/gif':
			{
				$this->extension = '.gif';
			}
			break;

			case 'image/png':
			case 'image/x-png':
			{
				$this->mime = 'image/png';
				$this->extension = '.png';
			}
			break;

			case 'image/jpeg':
			case 'image/pjpeg':
			{
				$this->mime = 'image/jpeg';
				$this->extension = '.jpeg';
			}
			break;

			case 'application/pdf':
			{
				$this->extension = '.pdf';
			}
			break;

			case 'application/zip':
			case 'application/x-zip':
			case 'application/x-zip-compressed':
			{
				$this->mime = 'application/zip';
				$this->extension = '.zip';
			}
			break;
		}

		#
		# check file type
		#

		if ($accepted_types)
		{
			$type = $this->mime;

			if (is_array($accepted_types))
			{
				$ok = false;

				foreach ($accepted_types as $accepted)
				{
					if ($type == $accepted)
					{
						$ok = true;
					}
				}

				if (!$ok)
				{
					$this->setError(self::ERR_TYPE);

					return;
				}
			}
			else if ($type != $accepted_types)
			{
				$this->setError(self::ERR_TYPE);

				return;
			}
		}

		#
		# finaly set the location of the file
		#

		$this->location = $data['tmp_name'];
	}

	static public function isMultiple($slot)
	{
		if (empty($_FILES[$slot]))
		{
			return false;
		}

		if (is_array($_FILES[$slot]['name']))
		{
			return count($_FILES[$slot]['name']);
		}

		return false;
	}

	private function setError($error)
	{
		$this->er = $error;

		switch ($error)
		{
			case UPLOAD_ERR_INI_SIZE:
			{
				$this->er_message = t('Maximum file size is :sizeMo', array(':size' => (int) ini_get('upload_max_filesize')));
			}
			break;

			case UPLOAD_ERR_FORM_SIZE:
			{
				$this->er_message = t('Maximum file size is :sizeMo', array(':size' => round(MAX_FILE_SIZE / 1024 / 1024, 2)));
			}
			break;

			case UPLOAD_ERR_NO_FILE:
			{
				$this->er_message = t('No file was uploaded');
			}
			break;

			case self::ERR_TYPE:
			{
				$this->er_message = t('The file type %mime is not supported', array('%mime' => $this->mime));
			}
			break;

			default:
			{
				$this->er_message = t('Error code: :code', array(':code' => $error));
			}
			break;
		}
	}

	public function move($destination, $overrite=false)
	{
		if (!$this->location)
		{
			return;
		}

		if (is_file($destination))
		{
			if ($overrite)
			{
				unlink($destination);
			}
			else
			{
				WdDebug::trigger
				(
					'Unable to move file %source to %destination, destination file already exists', array
					(
						'%source' => $this->location,
						'%destination' => $destination
					)
				);

				return false;
			}
		}

		$moved = false;

		if ($this->is_temporary)
		{
			$moved = move_uploaded_file($this->location, $destination);

			if ($moved)
			{
				$this->is_temporary = false;
			}
		}
		else
		{

			$moved = rename($this->location, $destination);
		}

		if (!$moved)
		{
			WdDebug::trigger
			(
				'Unable to move file %source to %destination', array
				(
					'%source' => $this->location,
					'%destination' => $destination
				)
			);

			return false;
		}

		$this->location = $destination;

		return true;
	}

	static $mimes_by_extension = array
	(
		'doc' => 'application/msword',
		'flv' => 'video/x-flv',
		'gif' => 'image/gif',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'js' => 'application/javascript',
		'mp3' => 'audio/mpeg',
		'odt' => 'application/vnd.oasis.opendocument.text',
		'pdf' => 'application/pdf',
		'png' => 'image/png',
		'psd' => 'application/psd',
		'rar' => 'application/rar',
		'zip' => 'application/zip'
	);

	static public function getMIME($filename)
	{
		$pos = strrpos($filename, '.');

		if ($pos === false)
		{
			return;
		}

		$extension = strtolower(substr($filename, $pos + 1));

		return isset(self::$mimes_by_extension[$extension]) ? self::$mimes_by_extension[$extension] : 'application/octet-stream';
	}
}