/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2011 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

var WdOperation = new Class
({
	Extends: Request.JSON,

	initialize: function(destination, operation, options)
	{
		this.destination = destination;
		this.operation = operation;

		if (!options)
		{
			options = {};
		}

		if (!options.url)
		{
			options.url = document.location.protocol + '//' + document.location.host + document.location.pathname;
		}

		this.parent(options);
	},

	post: function(params)
	{
		if (!params)
		{
			params = {};
		}

		params['#destination'] = this.destination;
		params['#operation'] = this.operation;

		return this.parent(params);
	},

	get: function(params)
	{
		this.options.url = '/api/' + this.destination + '/' + this.operation;

		return this.parent(params);
	},

	success: function(text)
	{
		this.response.json = JSON.decode(text, this.options.secure);

		if (!this.response.json)
		{
			var el = new Element('pre', { 'html': text });

			document.body.appendChild(el);

			alert(text);

			return;
		}

		this.onSuccess(this.response.json, text);
	}
});

(function() {

	var available_css;
	var available_js;

	Document.implement
	({

		/**
		 * Update the document by adding missing CSS and JS assets.
		 *
		 * @param object assets
		 * @param function done
		 */
		updateAssets: function (assets, done)
		{
			if (available_css === undefined)
			{
				available_css = [];

				if (typeof(document_cached_css_assets) !== 'undefined')
				{
					available_css = document_cached_css_assets;
				}

				$(document.head).getElements('link[type="text/css"]').each
				(
					function(el)
					{
						available_css.push(el.get('href'));
					}
				);
			}

			if (available_js === undefined)
			{
				available_js = [];

				if (typeof(document_cached_js_assets) !== 'undefined')
				{
					available_js = document_cached_js_assets;
				}

				$(document.html).getElements('script').each
				(
					function(el)
					{
						var src = el.get('src');

						if (src) available_js.push(src);
					}
				);
			}

			var css = [];

			assets.css.each
			(
				function(url)
				{
					if (available_css.indexOf(url) != -1)
					{
						return;
					}

					css.push(url);
				}
			);

			css.each
			(
				function(url)
				{
					new Asset.css(url);

					available_css.push(url);
				}
			);

			var js = [];

			assets.js.each
			(
				function(url)
				{
					if (available_js.indexOf(url) != -1)
					{
						return;
					}

					js.push(url);
				}
			);

			var js_count = js.length;

			if (!js_count)
			{
				done();

				return;
			}

			js.each
			(
				function(url)
				{
					new Asset.javascript
					(
						url,
						{
							onload: function()
							{
								available_js.push(url);

								if (!--js_count)
								{
									done();
								}
							}
						}
					);
				}
			);
		}
	});

	var api_base = $(document.html).get('data-api-base');

	if (!api_base)
	{
		api_base = '';
	}

	api_base += '/api/';

	/**
	 * Extends Request.JSON adding specific support to the Core API.
	 */
	Request.API = new Class
	({
		Extends: Request.JSON,

		options:
		{
			link: 'cancel'
		},

		initialize: function(options)
		{
			if (options.url.match(/^\/api\//))
			{
				options.url = options.url.substring(5);
			}

			options.url = api_base + options.url;

			this.parent(options);
		}
	});

}) ();

/**
 * Extends Request.API to support the loading of single HTML elements.
 */
Request.Element = new Class
({
	Extends: Request.API,

	onSuccess: function(response, text)
	{
		var el = Elements.from(response.rc).shift();

		if (!response.assets)
		{
			this.parent(el, response, text);

			return;
		}

		document.updateAssets
		(
			response.assets, function()
			{
				this.fireEvent('complete', [ response, text ]).fireEvent('success', [ el, response, text ]).callChain();
			}
			.bind(this)
		);
	}
});