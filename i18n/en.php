<?php

return array
(
	'label' => array
	(
		'cancel' => 'Cancel',
		'continue' => 'Continue',
		'save' => 'Save',
		'send' => 'Send',
		'use' => 'Use'
	),

	'permission' => array
	(
		'none' => 'None',
		'access' => 'Access',
		'create' => 'Create',
		'maintain' => 'Maintain',
		'manage' => 'Manage',
		'administer' => 'Administer'
	),










	#
	# WdUpload
	#

	'@upload.error.mime' => "The file type %mime is not supported. The file type must be %type.",
	'@upload.error.mimeList' => "The file type %mime is not supported. The file type must be of the following: :list or :last.",

	'label.salutation' => 'Salutation',

	'salutation' => array
  	(
		'misses' => 'Misses',
		'miss' => 'Miss',
		'mister' => 'Mister'
	),

	#
	# Modules categories
	#

	'system' => array
	(
		'modules' => array
		(
			'categories' => array
			(
				'contents' => 'Contents',
				'resources' => 'Resources',
				'organize' => 'Organize',
				'system' => 'System',
				'users' => 'Users',

				// TODO-20100721: not sure about those two: "feedback" and "structure"

				'feedback' => 'Feedback',
				'structure' => 'Structure'
			)
		)
	)
);