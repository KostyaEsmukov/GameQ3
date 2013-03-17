<?php

/**
 * List protocols
 *
 * Ported from GameQv2
 *
 * @author Austin Bischoff <austin@codebeard.com>
 * @author Kostya Esmukov <kostya.shift@gmail.com>
 */

// Autoload classes
spl_autoload_extensions(".php");

// https://bugs.php.net/bug.php?id=51991
if (version_compare(phpversion(), '5.3.3', '<')) {
	spl_autoload_register(
		function ($class) {
			spl_autoload(str_replace('\\', DIRECTORY_SEPARATOR, ltrim($class, '\\')));
		}
	);
} else {
	spl_autoload_register();
}

chdir(dirname(__FILE__) . "/../"); // let autoload work

// Define the protocols path
$protocols_path = "./gameq3/protocols/";

// Grab the dir with all the classes available
$dir = dir($protocols_path);

$protocols = array();

// Now lets loop the directories
while (true) {
	$entry = $dir->read();
	if ($entry === false) break;
	
	if(!is_file($protocols_path.$entry)) {
		continue;
	}
	
	$className = ucfirst(pathinfo($entry, PATHINFO_FILENAME));

	// Figure out the class name
	$protocol_class = "\\GameQ3\\Protocols\\".$className;

	// Lets get some info on the class
	$reflection = new ReflectionClass($protocol_class);

	// Check to make sure we can actually load the class
	if(!$reflection->IsInstantiable()) {
		continue;
	}
	
	$dp = $reflection->getDefaultProperties();
	$dc = $reflection->getConstants();
	$pt_string = $dp['ports_type'];

	foreach($dc as $name => $val) {
		// filter out non-PT constants
		if (substr($name, 0, 3) !== "PT_") continue;

		if ($val === $dp['ports_type']) {
			$pt_string = strtolower(substr($name, 3));
			$pt_string = str_replace("_", " ", $pt_string);
			break;
		}
	}
	
	$protocols[strtolower($className)] = array(
		'protocol' => $dp['protocol'],
		'name' => $dp['name'],
		'name_long' => $dp['name_long'],
		'query_port' => (is_int($dp['query_port']) ? $dp['query_port'] : '<i>not set</i>'),
		'connect_port' => (is_int($dp['connect_port']) ? $dp['connect_port'] : '<i>not set</i>'),
		'ports_type' => $dp['ports_type'],
		'ports_type_string' => $pt_string,
		'network' => $dp['network'],
		'connect_string' => (is_string($dp['connect_string']) ? $dp['connect_string'] : '<i>not set</i>'),
	);
	
	unset($reflection);
}

unset($dir);

ksort($protocols);

$supported_games = count($protocols);

$content_table = "";

foreach ($protocols as $classname => $dp) {
	$cls = empty($cls) ? ' class="uneven"' : '';
	
	$l = "\t\t\t\t"; // idents
	$l .= "<tr" . $cls . ">";
	$l .= sprintf("<td class='left'>%s</td><td class='left'>%s</td><td>%s</td><td>%s</td>", $classname, $dp['name_long'], $dp['name'], $dp['protocol']);
	if ($dp['network']) {
		if ($dp['ports_type_string'] === "same") {
			$l .= sprintf("<td colspan='2'>%s</td>", $dp['query_port']);
		} else {
			$l .= sprintf("<td>%s</td><td>%s</td>", $dp['query_port'], $dp['connect_port']);
		}
		$l .= sprintf("<td>%s</td>", $dp['ports_type_string']);
	} else {
		$l .= "<td colspan='3'><i>Not a network protocol</i></td>";
	}
	$l .= "<td>" . $dp['connect_string'] . "</td>";
	$l .= "</tr>\n";
	
	$content_table .= $l;
	unset($protocols[$classname]);
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<title>GameQ3 - Supported Games</title>
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
				text-align: center;
			}
			.left {
				text-align: left;
			}
		</style>
	</head>
	<body>
		<h1>GameQ3 - Supported Games (<?php echo $supported_games; ?>)</h1>
		<table>
		<thead>
			<tr>
				<th>GameQ3 identifier</th>
				<th>Game name</th>
				<th>Short game name</th>
				<th>Protocol</th>
				<th>Query port</th>
				<th>Connect port</th>
				<th>Ports type</th>
				<th>Connect URL</th>
			</tr>
		</thead>
		<tbody>
<?php echo $content_table; ?>
		</tbody>
		</table>
	</body>
</html>