<?php

require __DIR__ . '/../gameq3/gameq3.php';

// Define your server,
// see list.php for all supported games and identifiers.
$server = array(
	'id' => 'CS 1.6',
	'type' => 'cs',
	'connect_host' => 'simhost.org:27015',
	// 'unset' => array('players', 'settings'),
);


$gq = new \GameQ3\GameQ3();

$gq->setLogLevel(true, true, true, true);
$gq->setFilter('colorize', array(
	'format' => 'strip'
));

$gq->setFilter('strip_badchars');

$gq->setFilter('sortplayers', array(
	'sortkeys' => array(
		array('key' => 'is_bot', 'order' => 'asc'),
		array('key' => 'score', 'order' => 'desc'),
		array('key' => 'name', 'order' => 'asc'),
	)
));

try {
	$gq->addServer($server);
}
catch(Exception $e) {
	die($e->getMessage());
}

$t = microtime(true);
$results = $gq->requestAllData();
$t = (microtime(true) - $t);

echo "Time elapsed: " . sprintf("%.2f", $t * 1000) . "ms.\n";

var_dump($results);