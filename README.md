WdCore
======

__WdCore__ is a high-performance object-oriented framework for PHP 5.2 and above. It is written
with speed, flexibility and lightness in mind. WdCore doesn't try to be an all-in-one do-it-all
solution, prefering to provided a tiny but strong set of classes and logics as a solid ground to
build web applications. 

WdCore offers the following features: Models and activerecords, Internationalization, Modules,
a RESTful API, runtime Mixins, Autoload, Operations, Events, Hooks, Sessions, Routes, Caching,
Image resizing. 

Together with [WdElements](https://github.com/Weirdog/WdElements) and
[WdPatron](https://github.com/Weirdog/WdPatron), WdCore is the base framework for the
[Publishr](http://www.wdpublisher.com) CMS, you might want to check these projects too.



Requirements
------------

The minimum requirement for the WdCore framework is PHP5.2 *except* PHP5.2.12-13 (bug #49521, #50875).
WdCore has been tested with Apache HTTP server on Linux, MacOS and Windows operating
systems. The Apache server must support URL rewriting.


Installation
------------

The WdCore framework can be retrieved from the GitHub repository at the following URL:

	git@github.com:Weirdog/WdCore.git

The WdCore framework doesn't need to be web-accessible, thus a single instance can be used for
multiple projects.


Configuring
-----------

Low-level components of the framework are configured using multiple configuration files, usually
one per component. The default configuration files are available in the '/config/' folder. To
override the configuration or part of it, you can provide the path or pathes to your configuration
files.

For example, you want to define the primary database connection:

1. Edit your _core_ configuration file e.g. '/protected/all/config/core.php' with the following
lines:
	
	return array
	(
		'connections' => array
		(
			'primary' => array
			(
				'dsn' => 'mysql:dbname=<databasename>;host=<hostname>',
				'username' => '<username>',
				'password' => '<password>'
			)
		)
	);
	
2. Then specify your config path while creating the _core_ object:

	$core = new WdCore
	(
		array
		(
			'pathes' => array
			(
				'config' => array('/protected/all/')
			)
		)
	);
	
	
What's next
-----------

The project website is under construction, but some examples are available at the following URL
(in french):

http://www.weirdog.com/blog/wdcore/


Inspiration
-----------

[MooTools](http://mootools.net/), [Ruby on Rails](http://rubyonrails.org), [Yii](http://www.yiiframework.com)