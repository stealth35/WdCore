/**
 * This file is part of the WdCore framework
 *
 * @author Olivier Laviale <olivier.laviale@gmail.com>
 * @link http://www.weirdog.com/wdcore/
 * @copyright Copyright (c) 2007-2010 Olivier Laviale
 * @license http://www.weirdog.com/wdcore/license/
 */

"use strict";

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

WdOperation.encode = function(destination, operation, params)
{
	var query = '/api/' + destination + '/' + operation;
	var parts = [];

	Object.each
	(
		params, function (value, key)
		{
			parts.push(key + '=' + encodeURIComponent(value));
		}
	);

	if (parts)
	{
		query += '?' + parts.join('&');
	}

	return query;
};

function wd_update_assets(assets, done)
{
	var base = window.location.protocol + '//' + window.location.hostname;

	//console.info('base: %s', base);

	//
	// initialize css
	//

	var css = [];

	if (typeof(document_cached_css_assets) !== 'undefined')
	{
		//console.log('cached css assets: %a', document_cached_css_assets);

		css = document_cached_css_assets;
	}

	if (assets.css)
	{
		css = assets.css;

		$(document.head).getElements('link[type="text/css"]').each
		(
			function(el)
			{
				var href = el.href.substring(base.length);

				if (css.indexOf(href) != -1)
				{
					//console.info('css already exists: %s', href);

					css.erase(href);
				}
			}
		);
	}

	//console.info('css final: %a', css);

	css.each
	(
		function(href)
		{
			new Asset.css(href);
		}
	);

	//
	// initialize javascript
	//

	var js = [];

	if (typeof(document_cached_js_assets) !== 'undefined')
	{
		//console.log('cached js assets: %a', document_cached_js_assets);

		js = document_cached_js_assets;
	}

	if (assets.js)
	{
		js = assets.js;

		$(document.html).getElements('script').each
		(
			function(el)
			{
				var src = el.src.substring(base.length);

				if (js.indexOf(src) != -1)
				{
					//console.info('script alredy exixts: %s', src);

					js.erase(src);
				}
			}
		);
	}

	//console.info('js: %a', js);

	if (js.length)
	{
		var js_count = js.length;

		js.each
		(
			function(src)
			{
				new Asset.javascript
				(
					src,
					{
						onload: function()
						{
							//console.info('loaded: %a', src);

							js_count--;

							if (!js_count)
							{
								//console.info('no js remaingn, initialize editor');

								/*
								if (response.rc.initialize)
								{
									eval(response.rc.initialize);
								}
								*/

								done();
							}
						}
					}
				);
			}
		);
	}
	else
	{
		/*
		if (response.rc.initialize)
		{
			eval(response.rc.initialize);
		}
		*/

		done();
	}
}