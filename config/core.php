<?php

$operations_path = $path . 'operations' . DIRECTORY_SEPARATOR;

return array
(
	'autoload' => array
	(
		'WdActiveRecord' => $path . 'wdactiverecord.php',
		'WdActiveRecordQuery' => $path . 'wdactiverecordquery.php',
		'WdArray' => $path . 'wdarray.php',
		'WdDatabase' => $path . 'wddatabase.php',
		'WdDatabaseTable' => $path . 'wddatabasetable.php',
		'WdDebug' => $path . 'wddebug.php',
		'WdEvent' => $path . 'wdevent.php',
		'WdException' => $path . 'wdexception.php',
		'WdHTTPException' => $path . 'wdexception.php',
		'WdFileCache' => $path . 'wdfilecache.php',
		'WdHook' => $path . 'wdhook.php',
		'WdI18n' => $path . 'wdi18n.php',
		'WdImage' => $path . 'wdimage.php',
		'WdMailer' => $path . 'wdmailer.php',
		'WdModel' => $path . 'wdmodel.php',
		'WdModule' => $path . 'wdmodule.php',
		'WdObject' => $path . 'wdobject.php',
		'WdOperation' => $path . 'wdoperation.php',
		'WdRoute' => $path . 'wdroute.php',
		'WdSession' => $path . 'wdsession.php',
		'WdSecurity' => $path . 'wdsecurity.php',
		'WdTranslator' => $path . 'wdi18n.php',
		'WdUploaded' => $path . 'wduploaded.php',

		'delete_WdOperation' => $operations_path . 'delete.php',
		'save_WdOperation' => $operations_path . 'save.php',
		'core__aloha_WdOperation' => $operations_path . 'core__aloha.php',
		'core__ping_WdOperation' => $operations_path . 'core__ping.php'
	),

	'cache configs' => false,
	'cache modules' => false,
	'cache catalogs' => false,

	'classes aliases' => array
	(

	),

	'config constructors' => array
	(
		'objects.methods' => array(array('WdObject', 'get_methods_definitions_constructor'), 'hooks')
	),

	'connections' => array
	(

	),

	'repository' => '/repository',
	'repository.temp' => '/repository/tmp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',
	'repository.vars' => '/repository/lib',

	'session_id' => 'wdsid'
);