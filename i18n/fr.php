<?php

return array
(
	'permission' => array
	(
		'none' => 'Néan',
		'access' => 'Accéder',
		'create' => 'Créer',
		'maintain' => 'Maintenir',
		'manage' => 'Gérer',
		'administer' => 'Administrer'
	),







	#
	# WdUpload
	#

	'@upload.error.mime' => "Le type de fichier %mime n'est pas supporté. Le type fichier doit être %type.",
	'@upload.error.mimeList' => "Le type de fichier %mime n'est pas supporté. Le type de fichier doit être un des suivant : :list or :last.",

	'@gender.misses' => 'Madame',
	'@gender.miss' => 'Mademoiselle',
	'@gender.mister' => 'Monsieur',

	'label.salutation' => 'Civilité',

	'salutation' => array
	(
		'misses' => 'Madame',
		'miss' => 'Mademoiselle',
		'mister' => 'Monsieur'
	),

	/*
	// http://www.btb.gc.ca/btb.php?lang=fra&cont=868
	// http://www.aidenet.eu/grammaire06b.htm

	'date.formats' => array
	(
		'default' => '%d/%m/%Y',
		'short' => '%d/%m',
		'short_named' => '%d %b',
		'long' => '%d %B %Y',
		'complete' => '%A %d %B %Y'
	),
	*/

	#
	# Modules categories
	#

	'system.modules.categories' => array
	(
		'contents' => 'Contenu',
		'resources' => 'Ressources',
		'organize' => 'Organiser',
		'system' => 'Système',
		'users' => 'Utilisateurs',

		// TODO-20100721: not sure about those two: "feedback" and "structure"

		'feedback' => 'Intéractions',
		'structure' => 'Structure'
	),

	#
	# i18n
	#

	'i18n.languages' => array
	(
		'en' => 'Anglais',
		'es' => 'Espagnol',
		'fr' => 'Français'
	),

	#
	#
	#

	":size\xC2\xA0b" => ":size\xC2\xA0o",
	":size\xC2\xA0Kb" => ":size\xC2\xA0Ko",
	":size\xC2\xA0Mb" => ":size\xC2\xA0Mo",
	":size\xC2\xA0Gb" => ":size\xC2\xA0Go"
);