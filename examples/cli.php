<?php

require __DIR__ . '/../gameq3/gameq3.php';

// Define your servers,
// see list.php for all supported games and identifiers.
$servers = array(
	array(
		'id' => 'CS 1.6',
		'type' => 'cs',
		'connect_host' => 'simhost.org:27015',
	),
	array(
		'id' => 'L4D',
		'type' => 'left4dead',
		'connect_host' => 'simhost.org:27009',
	),
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

foreach($servers as $server) {
	try {
		$gq->addServer($server);
	}
	catch(Exception $e) {
		die($e->getMessage());
	}
}



while(true) {
	$t = microtime(true);

	$results = $gq->requestAllData();

	$t = (microtime(true) - $t);

	$onl = 0;
	$offl = 0;
	$retrys = 0;
	$pavg = 0;
	

	foreach($results as $id => &$val) {
		if ($val['info']['online']) {
			$onl++;
			$pavg += $val['info']['ping_average'];
			
			echo "[".str_pad($id."]", 12)
				." ".str_pad($val['info']['short_name'], 10)
				." ".str_pad($val['info']['retry_count'], 2)
				." ".str_pad(sprintf("%.2f", $val['info']['ping_average'])."ms", 10)
				." ".($val['general']['password'] ? 'X' : 'O')
				." ".($val['general']['secure'] ? 'V' : '-')
				." ".str_pad($val['general']['hostname'], 50)
				." ".str_pad(isset($val['general']['map']) ? $val['general']['map'] . (isset($val['general']['mode']) ? " [" . $val['general']['mode'] . "]" : "") : '', 20)
				." ".$val['general']['num_players']." / ".$val['general']['max_players']
				. (is_int($val['general']['private_players']) ? " (" . ( $val['general']['max_players'] + $val['general']['private_players']) . ")" : "")
				. (isset($val['general']['version']) ? " [" . $val['general']['version'] ."]": "")
				."\n";
			
			$retrys += $val['info']['retry_count'];


		} else {
			$offl++;
			echo "[".str_pad($id."]", 12)
				." ".str_pad($val['info']['protocol'], 10)
				." ---------------- offline\n";
		}
	}
	
	echo "Online: " . $onl . ". Offline: " . $offl .". Retrys: " . $retrys . ". Total: " . (count($results)) . ". Ping avg: " . sprintf("%.2f", ($pavg/$onl)) . "ms.\n";
	echo "Time elapsed: " . ($t) . "s.\n";

	$mu1 = memory_get_usage()/1024;
	$results = null;
	unset($results);



	echo sprintf("Memory result/unset/max (kb) %.0f/%.0f/%.0f\n\n", $mu1, memory_get_usage()/1024, memory_get_peak_usage()/1024);

	sleep(2);
}