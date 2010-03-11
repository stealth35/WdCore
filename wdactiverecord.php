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

class WdActiveRecord
{
	public function __construct()
	{

	}

	public function __get($property)
	{
		$getter = '__get_' . $property;

		if (method_exists($this, $getter))
		{
			return $this->$property = $this->$getter();
		}

		#
		#
		#

		$rc = $this->__get_by_event($property, $success);

		if ($success)
		{
			return $rc;
		}

		WdDebug::trigger
		(
			'Unknow property %property for object of class %class', array
			(
				'%property' => $property, '%class' => get_class($this)
			)
		);
	}

	protected function __get_by_event($property, &$success)
	{
		global $core;

		$event = WdEvent::fire
		(
			'ar.property', array
			(
				'ar' => $this,
				'property' => $property
			)
		);

		#
		# The operation is considered a sucess if the `value` property exists in the event
		# object. Thus, even a `null` value is considered a success.
		#

		if (!$event || !property_exists($event, 'value'))
		{
			return;
		}

		$success = true;

		return $event->value;
	}

	static protected $models;

	protected function model($name=null)
	{
		if (!$name)
		{
			throw new WdException("Missing model's name");
		}

		if (empty(self::$models[$name]))
		{
			global $core;

			//echo t(__CLASS__ . '::' . __FUNCTION__ . ":&gt; load model $modelName<br />");

			list($module_id, $model_id) = explode('/', $name) + array(1 => 'primary');

			self::$models[$name] = $core->getModule($module_id)->model($model_id);
		}

		return self::$models[$name];
	}

	public function save()
	{
		$model = $this->model();
		$primary = $model->primary;

		$properties = get_object_vars($this);

		return $model->save
		(
			$properties, isset($properties[$primary]) ? $properties[$primary] : null
		);
	}
}