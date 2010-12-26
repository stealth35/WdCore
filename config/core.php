<?php

return array
(
	'autoload' => array
	(
		'WdActiveRecord' => $root . 'wdactiverecord.php',
		'WdActiveRecordQuery' => $root . 'wdactiverecordquery.php',
		'WdArray' => $root . 'wdarray.php',
		'WdDatabase' => $root . 'wddatabase.php',
		'WdDatabaseTable' => $root . 'wddatabasetable.php',
		'WdDate' => $root . 'wddate.php',
		'WdDebug' => $root . 'wddebug.php',
		'WdEvent' => $root . 'wdevent.php',
		'WdException' => $root . 'wdexception.php',
		'WdHTTPException' => $root . 'wdexception.php',
		'WdFileCache' => $root . 'wdfilecache.php',
		'WdHook' => $root . 'wdhook.php',
		'WdI18n' => $root . 'wdi18n.php',
		'WdImage' => $root . 'wdimage.php',
		'WdMailer' => $root . 'wdmailer.php',
		'WdModel' => $root . 'wdmodel.php',
		'WdModule' => $root . 'wdmodule.php',
		'WdObject' => $root . 'wdobject.php',
		'WdOperation' => $root . 'wdoperation.php',
		'WdRoute' => $root . 'wdroute.php',
		'WdSession' => $root . 'wdsession.php',
		'WdUploaded' => $root . 'wduploaded.php'
	),

	'cache configs' => false,
	'cache modules' => false,
	'cache catalogs' => false,

	'classes aliases' => array
	(

	),

	'connections' => array
	(

	),

	'repository' => '/repository',
	'repository.temp' => '/repository/temp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',

	'sessionId' => 'wdsid'
);