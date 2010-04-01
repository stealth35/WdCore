<?php

return array
(
	'autoconfig' => array
	(
		'core' => 'WdCore',
		'debug' => 'WdDebug',
		'event' => 'WdEvent',
		'hook' => 'WdHook',
		'locale' => 'WdLocale'
	),

	'autoload' => array
	(
		'WdActiveRecord' => $root . 'wdactiverecord.php',
		'WdApplication' => $root . 'wdapplication.php',
		'WdArray' => $root . 'wdarray.php',
		'WdDatabase' => $root . 'wddatabase.php',
		'WdDatabaseTable' => $root . 'wddatabasetable.php',
		'WdDate' => $root . 'wddate.php',
		'WdEvent' => $root . 'wdevent.php',
		'WdFileCache' => $root . 'wdfilecache.php',
		'WdHook' => $root . 'wdhook.php',
		'WdImage' => $root . 'wdimage.php',
		'WdMailer' => $root . 'wdmailer.php',
		'WdModel' => $root . 'wdmodel.php',
		'WdModule' => $root . 'wdmodule.php',
		'WdOperation' => $root . 'wdoperation.php',
		'WdUploaded' => $root . 'wduploaded.php'
	),

	'cache modules' => false,

	'repository' => '/repository',
	'repository.temp' => '/repository/$temp',
	'repository.cache' => '/repository/$cache',

	'sessionId' => 'wdsid'
);