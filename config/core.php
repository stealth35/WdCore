<?php

return array
(
	'autoload' => array
	(
		'WdActiveRecord' => $root . 'wdactiverecord.php',
		'WdApplication' => $root . 'wdapplication.php',
		'WdArray' => $root . 'wdarray.php',
		'WdDatabase' => $root . 'wddatabase.php',
		'WdDatabaseTable' => $root . 'wddatabasetable.php',
		'WdDate' => $root . 'wddate.php',
		'WdDebug' => $root . 'wddebug.php',
		'WdEvent' => $root . 'wdevent.php',
		'WdException' => $root . 'wdexception.php',
		'WdFileCache' => $root . 'wdfilecache.php',
		'WdHook' => $root . 'wdhook.php',
		'WdImage' => $root . 'wdimage.php',
		'WdLocale' => $root . 'wdlocale.php',
		'WdMailer' => $root . 'wdmailer.php',
		'WdModel' => $root . 'wdmodel.php',
		'WdModule' => $root . 'wdmodule.php',
		'WdObject' => $root . 'wdobject.php',
		'WdOperation' => $root . 'wdoperation.php',
		'WdUploaded' => $root . 'wduploaded.php'
	),

	'cache configs' => false,
	'cache modules' => false,

	'classes aliases' => array
	(

	),

	'repository' => '/repository',
	'repository.temp' => '/repository/temp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',

	'sessionId' => 'wdsid'
);