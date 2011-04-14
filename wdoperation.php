<?php

/*
 * This file is part of the WdCore package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

abstract class WdOperation extends WdObject
{
	const DESTINATION = '#destination';
	const NAME = '#operation';
	const KEY = '#key';

	const RESTFUL_BASE = '/api/';
	const RESTFUL_BASE_LENGHT = 5;

	static protected $formats = array
	(
		'json' => array('application/json', 'format_json'),
		'xml' => array('application/xml', 'format_xml')
	);

	/**
	 * Encodes a RESful operation.
	 *
	 * @param string $pattern
	 * @param array $params
	 * @uses WdCore::contextualize_api_string() to contextualize the API string.
	 */
	static public function encode($pattern, array $params=array())
	{
		global $core;

		$destination = null;
		$name = null;
		$key = null;

		if (isset($params[self::DESTINATION]))
		{
			$destination = $params[self::DESTINATION];

			unset($params[self::DESTINATION]);
		}

		if (isset($params[self::NAME]))
		{
			$name = $params[self::NAME];

			unset($params[self::NAME]);
		}

		if (isset($params[self::KEY]))
		{
			$key = $params[self::KEY];

			unset($params[self::KEY]);
		}

		$qs = http_build_query($params, '', '&');

		$rc = self::RESTFUL_BASE . strtr
		(
			$pattern, array
			(
				'{destination}' => $destination,
				'{name}' => $name,
				'{key}' => $key
			)
		)

		. ($qs ? '?' . $qs : '');

		return $core->contextualize_api_string($rc);
	}

	/**
	 * Decodes the specified request into a WdOperation instance.
	 *
	 * An operation can be defined as a route, in which case the path of the request starts with
	 * "/api/". An operation can also be defined using the request parameters, in which case
	 * the DESTINATION, NAME and optionaly KEY parameters are defined within the request
	 * parameters.
	 *
	 * When the operation is defined as a route, the method searches for a matching route.
	 *
	 * If a matching route is found, the captured parameters of the matching route are merged
	 * with the request parameters and the method tries to create a WdOperation instance using
	 * the route.
	 *
	 * If no matching route could be found, the method tries to extract the DESTINATION, NAME and
	 * optional KEY parameters from the URL using the `/api/:destination(/:key)/:name` pattern.
	 * If the URL matches this pattern, captured parameters are merged with the request
	 * parameters and the operation decoding continues as if the operation was defined using
	 * parameters instead of the REST API.
	 *
	 * Finally, the method searches for the DESTINATION, NAME and optional KEY aparameters within
	 * the request parameters to create the WdOperation instance.
	 *
	 * If no operation was found in the request, the method simply returns.
	 *
	 *
	 * Instancing using the matching route
	 * -----------------------------------
	 *
	 * The matching route must define either the class of the operation instance (by defining the
	 * `class` key) or a callback that would create the operation instance (by defining the
	 * `callback` key).
	 *
	 * If the route defines the instance class, it is used to create the instance. Otherwise, the
	 * callback is used to create the instance.
	 *
	 *
	 * Instancing using the request parameters
	 * ---------------------------------------
	 *
	 * The operation destination (specified by the DESTINATION parameter) is the id of the
	 * destination module. The class and the operation name (specified by the NAME
	 * parameter) are used to search for the corresponding operation class to create the instance:
	 *
	 *     <module_class_fragment>__<operation_name>_WdOperation
	 *
	 * The inheritence of the module class is used the find a suitable class. For example,
	 * these are the classes tried for the "contents.articles" module and the "save" operation:
	 *
	 *     contents_articles__save_WdOperation
	 *     contents__save_WdOperation
	 *     system_nodes__save_WdOperation
	 *
	 * An instance of the found class is created with the request arguments and returned. If the
	 * class could not be found to create the operation instance, an exception is raised.
	 *
	 * @param string $uri The request URI.
	 * @param array $params The request parameters.
	 * @throws WdException When there is an error in the operation request.
	 * @throws WdHTTPException When the specified operation doesn't exists.
	 * @uses WdCore::decontextualize_api_string() to decontextualize the API string.
	 */
	static public function decode($uri, array $params=array())
	{
		global $core;

		$uri = $core->decontextualize_api_string($uri);

		if (substr($uri, 0, self::RESTFUL_BASE_LENGHT) == self::RESTFUL_BASE)
		{
			if ($_SERVER['QUERY_STRING'])
			{
				$uri = substr($uri, 0, -strlen($_SERVER['QUERY_STRING']) - 1);
			}

			foreach (self::$formats as $extension => $format)
			{
				$extension = '.' . $extension;
				$extension_length = strlen($extension);

				if (substr($uri, -$extension_length) == $extension)
				{
					$_SERVER['HTTP_ACCEPT'] = $format[0];

					$uri = substr($uri, 0, -$extension_length);

					break;
				}
			}

			$routes = WdConfig::get_constructed('api', array(__CLASS__, 'api_constructor'), 'routes');

			foreach ($routes as $pattern => $route)
			{
				$match = WdRoute::match($uri, $pattern);

				if (!$match)
				{
					continue;
				}

				#
				# We found a matching route. The arguments captured from the route are merged with
				# the request parameters. The route must define either a class for the operation
				# instance (defined using the `class` key) or a callback to create that instance
				# (defined using the `callback` key).
				#

				if (is_array($match))
				{
					$params = $match + $params;
				}

				if (isset($route['callback']) && isset($route['class']))
				{
					throw new WdException('Ambiguous definition for operation route, both callback and class are defined.');
				}
				else if (isset($route['callback']))
				{
					$operation = call_user_func($route['callback'], $params);

					if (!$operation instanceof WdOperation)
					{
						throw new WdException('The operation route callback %callback failed to produce an operation object.', array('%callback' => implode('::', $route['callback'])));
					}
				}
				else if (isset($route['class']))
				{
					$class = $route['class'];

					if (!class_exists($class, true))
					{
						throw new WdException('Unable to create operation instance, the %class class is not defined.', array('%class' => $class));
					}

					$operation = new $class($route, $pattern, $params);
				}
				else
				{
					throw new WdException('The operation route must either define a class or a callback.');
				}

				$operation->terminus = true;
				$operation->method = 'GET';

				return $operation;
			}

			#
			# We could not find a matching route, we try to extract the DESTINATION, NAME and
			# optional KEY from the URI.
			#

			preg_match('#^([a-z\.]+)/(([^/]+)/)?([a-zA-Z0-9_\-]+)$#', substr($uri, self::RESTFUL_BASE_LENGHT), $matches);

			if ($matches)
			{
				list(, $destination, , $operation_key, $name) = $matches;

				$params[self::DESTINATION] = $destination;
				$params[self::NAME] = strtr($name, '-', '_');
				$params[self::KEY] = $matches[2] ? $operation_key : null;
			}
		}

		#
		# The request is not a API operation, we try to extract the operation information from the
		# request parameters. If the DESTINATION and NAME request parameters are empty, we simply
		# return because there is no operation to process.
		#

		if (empty($params[self::DESTINATION]) && empty($params[self::NAME]))
		{
			return;
		}
		else if (empty($params[self::DESTINATION]))
		{
			throw new WdException('The destination for the %operation operation is missing', array('%operation' => $params[self::NAME]));
		}
		else if (empty($params[self::NAME]))
		{
			throw new WdException('The operation for the %destination destination is missing', array('%destination' => $params[self::DESTINATION]));
		}

		$name = $params[self::NAME];
		$destination = $params[self::DESTINATION];

		unset($params[self::DESTINATION]);
		unset($params[self::NAME]);

		$module = $core->modules[$destination];
		$class = self::resolve_operation_class($name, $module);

		if (!$class)
		{
			throw new WdHTTPException('Uknown operation %operation for the %module module.', array('%module' => (string) $module, '%operation' => $name), 404);
		}

		$operation = new $class($module, $name, $params);

		$method = $_SERVER['REQUEST_METHOD'];

		if (substr($uri, 0, self::RESTFUL_BASE_LENGHT) == self::RESTFUL_BASE || $method == 'GET')
		{
			$operation->terminus = true;
		}

		$operation->method = $method;

		return $operation;
	}

	/**
	 * Constructs the "api" configuration by filtering API routes from the "routes" fragments.
	 *
	 * @param array $fragments Configuration fragments.
	 */
	static public function api_constructor(array $fragments)
	{
		$routes = array();

		foreach ($fragments as $fragment)
		{
			foreach ($fragment as $pattern => $route)
			{
				if (substr($pattern, 0, self::RESTFUL_BASE_LENGHT) != self::RESTFUL_BASE)
				{
					continue;
				}

				$routes[$pattern] = $route;
			}
		}

		krsort($routes);

		return $routes;
	}

	static private function resolve_operation_class($name, $target)
	{
		static $suffix = '_WdModule';
		static $suffix_lenght = 9;

		$class = get_class($target);

		while ($class && substr($class, -$suffix_lenght) == $suffix)
		{
			$try = substr($class, 0, -$suffix_lenght) . "__{$name}_WdOperation";

			if (class_exists($try, true))
			{
				return $try;
			}

			$class = get_parent_class($class);
		}
	}

	public $name;
	public $key;
	public $destination;
	public $params = array();

	public $response;
	public $terminus = false;
	public $location;
	public $method;

	/**
	 * @var WdModule Target module for the operation.
	 *
	 * The property is set by the constructor.
	 */
	protected $module;

	/**
	 * @var array Controls to pass before validation.
	 */
	protected $controls;

	const CONTROL_AUTHENTICATION = 101;
	const CONTROL_PERMISSION = 102;
	const CONTROL_RECORD = 103;
	const CONTROL_OWNERSHIP = 104;
	const CONTROL_FORM = 105;

	/**
	 * Getter for the {@link $controls} property.
	 *
	 * @return array
	 */
	protected function __get_controls()
	{
		return array
		(
			self::CONTROL_AUTHENTICATION => false,
			self::CONTROL_PERMISSION => false,
			self::CONTROL_RECORD => false,
			self::CONTROL_OWNERSHIP => false,
			self::CONTROL_FORM => false
		);
	}

	/**
	 * @var WdActiveRecord The target active record object of the operation.
	 */
	protected $record;

	/**
	 * Getter for the {@link $record} property.
	 *
	 * @return WdActiveRecord
	 */
	protected function __get_record()
	{
		return $this->module->model[$this->key];
	}

	/**
	 * The form object of the operation.
	 *
	 * @var WdForm
	 */
	protected $form;

	/**
	 * Getter for the {@link $form} property.
	 *
	 * The method tries to load the form from the operation parameters using the
	 * {@link WdForm::load()} method.
	 *
	 * One can override this method to provide the form using another method. Or simply define the
	 * {@link $form} property to circumvent the getter.
	 *
	 * @return WdForm|null
	 */
	protected function __get_form()
	{
		return $this->form = WdForm::load($this->params);
	}

	/**
	 * @var array The properties for the operation.
	 */
	protected $properties;

	/**
	 * Getter for the {@link $properties} property.
	 *
	 * The getter should only be called during the {@link process()} method.
	 *
	 * @return array
	 */
	protected function __get_properties()
	{
		return array();
	}

	/**
	 * @var output Format for the operation response.
	 */
	protected $format;

	/**
	 * Constructor.
	 *
	 * The {@link $controls} property is unset in order for its getters to be called on the next
	 * access, while keeping its scope.
	 *
	 * @param WdModule|array $destination The destination of the operation, either a module or a
	 * route.
	 * @param string $name The name of the operation.
	 * @param array $params The parameters of the operation.
	 */
	public function __construct($destination, $name, array $params=array())
	{
		unset($this->controls);

		$this->destination = $destination;
		$this->name = $name;
		$this->params = $params;

		$this->target = $destination;
		$this->module = $destination instanceof WdModule ? $destination : null;

		if (isset($params[self::KEY]))
		{
			$this->key = $params[self::KEY];
		}
	}

	/**
	 * @var int Count operations nesting.
	 *
	 * _Location_ and _terminus_ are disabled for sub operations.
	 */
	static private $nesting=0;

	/**
	 * Handles the operation and prints or returns its result.
	 *
 	 * The {@link $record}, {@link $form} and {@link $properties} properties are unset in order
 	 * for their getters to be called on the next access, while keeping their scope.
	 *
	 * The response object
	 * -------------------
	 *
	 * The operation result is saved in a _response_ object, which may contain meta data describing
	 * or accompanying the result. Operations can use the response object to provide additional
	 * information with the result they return. For example, the `WdOperation` class returns the
	 * success and error messages in the `log` property.
	 *
	 * Depending on the `Accept` header of the request, the response object can be formated as
	 * JSON or XML. If the `Accept` header is "application/json" the response is formated as JSON.
	 * If the `Accept` header is "application/xml" the response is formated as XML. If the
	 * `Accept` header is not of a supported type, only the result is printed, as a string.
	 *
	 * For API requests, the output format can also be defined by appending the correspondig
	 * extension to the request path:
	 *
	 *     /api/system.nodes/12/online.json
	 *
	 *
	 * Control, validation and processing
	 * ----------------------------------
	 *
	 * Before the operation is actually processed with the {@link process()} method, it is
	 * controled and validated with the {@link control()} and {@link validate()} methods. If the
	 * control or validation fail the operation is not processed.
	 *
	 * The controls passed to the {@link control()} method are obtained through the
	 * {@link $controls} property or the {@link __get_controls()} getter if the property is not
	 * accessible.
	 *
	 *
	 * Events
	 * ------
	 *
	 * The `operation.<name>:before` event is fired before the operation is processed using the
	 * {@link process()} method with the operation destination as `target` key and the operation
	 * itself as `operation` key.
	 *
	 * The `operation.<name>` event is fired after the operation has been processed if its
	 * result is not `null` with the operation destination as `target` key, the operation itself
	 * as `operation` key and a reference to the operation result as `rc` key.
	 *
	 *
	 * Terminus and location
	 * ---------------------
	 *
	 * If the {@link $terminus} property is true after the operation has been processed, the
	 * script is ended. Remaining debug logs are added to the HTTP header as
	 * `X-Debug-<i>: <message>`. The {@link $terminus} property is always set to true when the
	 * request result is formated as JSON or XML.
	 *
	 * If the {@link $location} property is set after the operation has been processed, it is used
	 * to define the `Location` header, causing a redirection of the request. Also, the current
	 * request URL is set as `Referer`.
	 *
	 *
	 * Nested operations
	 * -----------------
	 *
	 * Operations can be nested, ie an operation can invoke another operation. A nesting counter
	 * is maintained and will disable handling of the {@link $terminus} and {@link $location}
	 * properties, until the original operation finishes.
	 *
	 *
	 * Failed operation
	 * ----------------
	 *
	 * If the result of the operation is `null`, the operation is considered as failed, in which
	 * case the result is not printed out and no event is fired. Still, the {@link $terminus} and
	 * {@link $location} properties are honored.
	 *
	 * Note that exceptions are not caught by the method.
	 *
	 * @return mixed The result of the operation.
	 */
	public function __invoke()
	{
		global $core;

		self::$nesting++;

		$name = $this->name;

		if ($name{0} == '/')
		{
			$name = substr($name, self::RESTFUL_BASE_LENGHT);
		}

		$this->response = (object) array('rc' => null, 'log' => array());

		unset($this->form);
		unset($this->record);
		unset($this->properties);

		$module = $this->destination instanceof WdModule ? $this->destination : null;

		$rc = null;

		if (!$this->control($this->controls))
		{
			WdEvent::fire('failed', array('target' => $this, 'type' => 'control'));

			wd_log('Operation control failed.');
		}
		else if (!$this->validate())
		{
			WdEvent::fire('failed', array('target' => $this, 'type' => 'validation'));

			wd_log('Operation validation failed.');
		}
		else
		{
			#
			# The 'operation.<name>:before' event is fired before the operation is processed.
			#

			WdEvent::fire
			(
				'operation.' . $name . ':before', array
				(
					'target' => $module,
					'operation' => $this
				)
			);

			WdEvent::fire
			(
				'process:before', array
				(
					'target' => $this
				)
			);

			$rc = $this->process();
		}

		$this->response->rc = $rc;

		#
		# If the operation succeed (its result is not null), the 'operation.<name>' event is fired.
		# Listeners might use the event for further processing. For example, a _comment_ module
		# might delete the comments related to an _article_ module from which an article was
		# deleted.
		#

		if ($rc !== null)
		{
			WdEvent::fire
			(
				'operation.' . $name, array
				(
					'target' => $module,
					'operation' => $this,
					'rc' => &$this->response->rc
				)
			);

			WdEvent::fire
			(
				'process', array
				(
					'target' => $this,
					'rc' => &$this->response->rc
				)
			);
		}

		if (--self::$nesting)
		{
			return $this->response->rc;
		}

		$terminus = $this->terminus;

		#
		# The operation response can be requested as JSON or XML, in which case the script is
		# terminated with the formated output of the response.
		#

		$rc = null;
		$rc_type = null;

		// FIXME-20101117: using $_SERVER is too global, we have to use an object related property

		if (isset($_SERVER['HTTP_ACCEPT']))
		{
			$accept = $_SERVER['HTTP_ACCEPT'];

			foreach (self::$formats as $format)
			{
				list($format_mime, $format_callback) = $format;

				if ($format_mime != $accept)
				{
					continue;
				}

				$logs = array('done', 'error');

				foreach ($logs as $type)
				{
					$this->response->log[$type] = WdDebug::fetchMessages($type);
				}

				$rc = $this->$format_callback();

				header('Content-Type: ' . $format_mime);
				header('Content-Length: '. strlen($rc));

				$terminus = true;

				break;
			}
		}

		if ($this->location && !headers_sent())
		{
			header('Location: ' . $this->location);
			header('Referer: ' . $_SERVER['REQUEST_URI']);

			exit;
		}

		#
		# If the `terminus` is set the script stops.
		#
		# note: The remaining messages in the WdDebug class logs are added in the HTTP header. This
		# might be usefull for debugging.
		#

		if ($terminus)
		{
			if (!headers_sent())
			{
				$logs = array('done', 'error', 'debug');

				foreach ($logs as $type)
				{
					$n = 1;

					foreach (WdDebug::fetchMessages($type) as $message)
					{
						$message = strip_tags($message);
						$message = str_replace("\r\n", "\n", $message);
						$message = str_replace("\n", ' ### ', $message);

						header(sprintf('X-WdDebug-%s-%04d: %s', $type, $n++, $message));
					}
				}
			}

			echo $rc ? $rc : $this->response->rc;

			exit;
		}

		return $this->response->rc;
	}

	/**
	 * Controls the operation.
	 *
	 * A number of controls may be passed before an operation is validated and processed. Controls
	 * are defined as an array where the key is the control identifier, and the value defines
	 * whether the control is enabled. Controls are enabled by setting their value to true:
	 *
	 *     array
	 *     (
	 *         self::CONTROL_AUTHENTICATION => true,
	 *         self::CONTROL_RECORD => true,
	 *         self::CONTROL_FORM => false
	 *     );
	 *
	 * Instead of a boolean, the "permission" control is enabled by a permission string or a
	 * permission level.
	 *
	 *     array
	 *     (
	 *         self::CONTROL_PERMISSION => WdModule::PERMISSION_MAINTAIN
	 *     );
	 *
	 * The {@link $controls} property is used to get the controls or its magic getter
	 * {@link __get_controls()} if the property is not accessible.
	 *
	 * Controls are passed in the following order:
	 *
	 * 1. CONTROL_AUTHENTICATION
	 *
	 * Controls the authentication of the user. The {@link control_authentication()} method is
	 * invoked for this control. An exception with the code 401 is thrown when the control fails.
	 * The control is enabled by setting its value to true:
	 *
	 *     array
	 *     (
	 *         self::CONTROL_AUTHENTICATION => true
	 *     );
	 *
	 * 2. CONTROL_PERMISSION
	 *
	 * Controls the permission of the guest or user. The {@link control_permission()} method is
	 * invoked for this control. An exception with code 401 is thrown when the control fails.
	 *
	 * 3. CONTROL_RECORD
	 *
	 * Controls the existence of the record specified by the operation's key. The
	 * {@link control_record()} method is invoked for this control. The value returned by the
	 * method is set in the operation objet under the {@link record} property. The callback method
	 * must throw an exception if the record could not be loaded or the control of this record
	 * failed.
	 *
	 * The {@link record} property, or the {@link __get_record()} getter, is used to get the
	 * record.
	 *
	 * 4. CONTROL_OWNERSHIP
	 *
	 * Controls the ownership of the user over the record loaded during the CONTROL_RECORD step.
	 * The {@link control_ownership()} method is invoked for the control. An exception with code
	 * 401 is thrown if the control fails.
	 *
	 * 5. CONTROL_FORM
	 *
	 * Controls the form associated with the operation by checking its existence and validity. The
	 * {@link control_form()} method is invoked for this control. Failing the control does not
	 * throw an exception, but a message is logged to the debug log.
	 *
	 * @param array $controls The controls to pass for the operation to be processed.
	 * @throws WdException|WdHTTPException Depends on the control.
	 * @return boolean true if all the controls pass, false otherwise.
	 */
	protected function control(array $controls)
	{
		$controls += $this->controls;

		if ($controls[self::CONTROL_AUTHENTICATION] && !$this->control_authentication())
		{
			throw new WdHTTPException
			(
				'The %operation operation requires authentication.', array
				(
					'%operation' => $this->name
				),

				401
			);
		}

		if ($controls[self::CONTROL_PERMISSION] && !$this->control_permission($controls[self::CONTROL_PERMISSION]))
		{
			throw new WdHTTPException
			(
				"You don't have permission to perform the %operation operation on the %module module.", array
				(
					'%operation' => $this->name,
					'%module' => $this->module->id
				),

				401
			);
		}

		if ($controls[self::CONTROL_RECORD])
		{
			$this->control_record();
		}

		if ($controls[self::CONTROL_OWNERSHIP] && !$this->control_ownership())
		{
			throw new WdHTTPException
			(
				"You don't have ownership of the record: %key.", array
				(
					'%key' => $operation->key
				),

				401
			);
		}

		if ($controls[self::CONTROL_FORM] && !$this->control_form())
		{
			wd_log('Control %control failed for operation %operation on module %module.', array('%control' => 'form', '%module' => (string) $this->module, '%operation' => $this->name));

			return false;
		}

		return true;
	}

	/**
	 * Controls the authentication of the user.
	 */
	protected function control_authentication()
	{
		global $core;

		return ($core->user_id != 0);
	}

	/**
	 * Controls the permission of the user for the operation.
	 *
	 * @param mixed $permission The required permission.
	 * @return bool true if the user has the specified permission, false otherwise.
	 */
	protected function control_permission($permission)
	{
		global $core;

		return $core->user->has_permission($permission, $this->module);
	}

	/**
	 * Controls the ownership of the user over the operation target record.
	 *
	 * @return bool true if the user as ownership of the record or there is no record, false
	 * otherwise.
	 */
	protected function control_ownership()
	{
		global $core;

		$record = $this->record;

		return (!$record || $core->user->has_ownership($this->module, $record));
	}

	/**
	 * Checks if the operation target record exists.
	 *
	 * As it is, the method simply returns the {@link $record} property, which might call the
	 * {@link __get_record()} getter method if the property is not accessible.
	 *
	 * @return WdActiveRecord
	 */
	protected function control_record()
	{
		return $this->record;
	}

	/**
	 * Control the operation's form.
	 *
	 * The form is retrieved from the {@link $form} property, which might invoke the
	 * {@link __get_form()} getter if the property is not accessible.
	 *
	 * @return bool true if the form exists and validates, false otherwise.
	 */
	protected function control_form()
	{
		$form = $this->form;

		return ($form && $form->validate($this->params));
	}

	/**
	 * Validates the operation before processing.
	 *
	 * The method is abstract and therefore must be implemented by subclasses.
	 */
	abstract protected function validate();

	/**
	 * Processes the operation.
	 *
	 * The method is abstract and therefore must be implemented by subclasses.
	 */
	abstract protected function process();

	/**
	 * Formats the operation response to the JSON format.
	 *
	 * @return string
	 */
	protected function format_json()
	{
		return json_encode($this->response);
	}

	/**
	 * Formats the operation response to the XML format.
	 *
	 * @return string
	 */
	protected function format_xml()
	{
		return wd_array_to_xml($this->response, 'response');
	}
}