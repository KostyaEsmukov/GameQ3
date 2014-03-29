<?php

require __DIR__ . '/../gameq3/gameq3.php';

// Define your servers,
// see list.php for all supported games and identifiers.
$servers = array(
	array(
		'id' => 'TS3',
		'type' => 'teamspeak3',
		'connect_host' => 'simhost.org',
	),
	array(
		'id' => 'CS 1.6 server',
		'type' => 'cs',
		'connect_host' => 'simhost.org:27015',
	)
);


$gq = new \GameQ3\GameQ3();

//$gq->setLogLevel(true, true, true, true);
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

$results = $gq->requestAllData();


// Some functions to print the results
function print_results($results) {
	foreach ($results as $id => $data) {
		printf("\t\t<h2>%s</h2>\n", $id);
		print_table($data);
	}
}

function print_table($data) {

	$info_always = array(
		'query_addr',
		'query_port',
		'connect_addr',
		'connect_port',
		'protocol',
		'short_name',
		'long_name',
		'connect_string',
		'online',
		'ping_average',
		'retry_count',
		'identifier'
	);
	
	$general_always = array(
		'hostname',
		'version',
		'max_players',
		'num_players',
		'password',
		'private_players',
		'bot_players',
		'map',
		'mode',
		'secure'
	);

	if (!$data['info']['online']) {
		echo "<p>The server did not respond within the specified time.</p>\n";
		return;
	}

	echo "\t\t<table>\n\t\t<thead>\n\t\t\t<tr><th>Group</th><th>Variable</th><th>Value</th></tr>\n\t\t</thead>\n\t\t<tbody>\n";
	
	foreach($data as $group => $datac) {
		if ($group !== 'info' && $group !== 'general' && $group !== 'settings' && $group !== 'players') {
			$cls = empty($cls) ? ' class="uneven"' : '';
			printf("\t\t\t<tr%s><td>%s</td><td>%s</td><td>%s</td></tr>\n", $cls, $group, '<i>Keys count</i>', count($datac));
			continue;
		}
		
		$grouph = $group;
		if ($group === 'info' || $group === 'general') {
			$grouph = "<span class=\"key-always\">" . $group . "</span>";
		}

		foreach ($datac as $key => $val) {
			$cls = empty($cls) ? ' class="uneven"' : '';
			
			if ($group === 'info') {
				if (in_array($key, $info_always))
					$key = "<span class=\"key-always\">" . $key . "</span>";
			} else
			if ($group === 'general') {
				if (in_array($key, $general_always))
					$key = "<span class=\"key-always\">" . $key . "</span>";	
			} else
			if ($group === 'settings') {
				$key = $val[0];
				$val = $val[1];
			} else
			if ($group === 'players') {
				$key = $val['name'];
				$val = $val['score'];
			}

			printf("\t\t\t<tr%s><td>%s</td><td>%s</td><td>%s</td></tr>\n", $cls, $grouph, $key, var_export($val, true));
		}
	}

	echo "\t\t</tbody>\n\t\t</table>\n";
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>GameQ3 - Example script</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<style type="text/css">
			body {
				font-size: 12px;
				font-family: Verdana, sans-serif;
			}
			h1 {
				font-size: 16px;
				text-align: center;
			}
			table {
				border: 1px solid #000;
				border-spacing: 1px 1px;
				background-color: #DDD;
				margin: 0px auto;
			}
			table th {
				font-weight: bold;
				background-color: #CCC;
			}
			table td {
				background-color: #F9F9F9;
			}
			table tr.uneven td {
				background-color:#FFF;
			}
			table td, table th {
				padding: 5px 8px;
			}
			h2 {
				font-size: 13px;
				text-align: center;
				margin-top: 25px;
			}
			.note {
				color: #333;
				font-style: italic;
				text-align: center;
			}
			.key-always {
				color: red;
				font-weight: bold;
			}
		</style>
	</head>
	<body>
		<h1>GameQ3 - Example script</h1>
		<div class="note">
			This is a simple output example.<br/>
			<span class="key-always">Bold, red</span> variables are always set by GameQ3.<br/>
			Click <a href="./list.php">here</a> for a list of supported games.
		</div>
<?php print_results($results); ?>
	</body>
</html>