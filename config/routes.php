<?php

return array
(
	'/api/core/aloha' => array
	(
		'callback' => array('WdCore', 'operation_aloha')
	),

	'/api/core/ping' => array
	(
		'callback' => array('WdCore', 'operation_ping')
	)
);