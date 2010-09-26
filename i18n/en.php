<?php

return array
(
	#
	# WdUpload
	#

	'@upload.error.mime' => "The file type %mime is not supported. The file type must be %type.",
	'@upload.error.mimeList' => "The file type %mime is not supported. The file type must be of the following: :list or :last.",

	'salutation' => array
	(
		'misses' => 'Misses',
		'miss' => 'Miss',
		'mister' => 'Mister'
	),

	#
	# Date
	#

	'date' => array
	(
		'formats' => array
		(
			'default' => '%m/%d/%Y',
			'short' => '%m/%d',
			'short_named' => '%b %d',
			'long' => '%B %d, %Y',
			'complete' => '%A, %B %d, %Y'
		)
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