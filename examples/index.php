<?php

chdir(dirname(__FILE__) . "/../"); // let autoload work
require './gameq3/gameq3.php';

// Define your servers,
// see list.php for all supported games and identifiers.
$servers = array(
	array(
		'id' => 'TS3',
		'type' => 'teamspeak3',
		'host' => 'simhost.org',
	),
	array(
		'id' => 'CS 1.6 server',
		'type' => 'cs',
		'host' => 'simhost.org:27015',
	)
);


$gq = new GameQ3\GameQ3();

//$gq->setLogLevel(true, true, true, true);
$gq->setFilter('colorize', array(
	'format' => 'strip'
));

$gq->setFilter('sortplayers', array(
	'sortkey' => 'score',
	'order' => SORT_DESC,
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
		printf("<h2>%s</h2>\n", $id);
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
		'online',
		'ping_average',
		'retry_count'
	);
	
	$general_always = array(
		'hostname',
		'version',
		'max_players',
		'num_players',
		'password',
		'private_players',
		'map',
		'mode',
		'secure'
	);

	if (!$data['info']['online']) {
		printf("<p>The server did not respond within the specified time.</p>\n");
		return;
	}

	print("<table><thead><tr><td>Group</td><td>Variable</td><td>Value</td></tr></thead><tbody>\n");
	
	foreach($data as $group => $datac) {
		if ($group !== 'info' && $group !== 'general' && $group !== 'settings' && $group !== 'players') {
			$cls = empty($cls) ? ' class="uneven"' : '';
			printf("<tr%s><td>%s</td><td>%s</td><td>%s</td></tr>\n", $cls, $group, 'Keys count', count($datac));
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

			printf("<tr%s><td>%s</td><td>%s</td><td>%s</td></tr>\n", $cls, $grouph, $key, var_export($val, true));
		}
	}

	print("</tbody></table>\n");
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>GameQ - Example script</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <style type="text/css">
            * {
                font-size: 9pt;
                font-family: Verdana, sans-serif;
            }
            h1 {
                font-size: 12pt;
            }
            h2 {
                margin-top:2em;
                font-size: 10pt;
            }
            table {
                border: 1px solid #000;
                background-color: #DDD;
                border-spacing:1px 1px;
            }
            table thead {
                font-weight: bold;
                background-color: #CCC;
            }
            table tr.uneven td {
                background-color:#FFF;
            }
            table td {
                padding: 5px 8px;
            }
            table tbody {
                background-color: #F9F9F9;
            }
            .note {
                color: #333;
                font-style:italic;
            }
            .key-always {
                color:red;
                font-weight:bold;
            }
            .key-normalise {
                color:red;
            }
        </style>
    </head>
    <body>
    <h1>GameQ - Example script</h1>
    <div class="note">
    This is a simple output example. <br/>
    <span class="key-always">Bold, red</span> variables are always set by gameq.
    <br/>
    Click <a href="./list.php">here</a> for a list of supported games.
    </div>
<?php
	print_results($results);
?>
    </body>
</html>