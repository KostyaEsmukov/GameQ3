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
spl_autoload_register();

chdir("../"); // let autoload work

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
	$protocols[strtolower($className)] = array(
		'protocol' => $dp['protocol'],
		'port' => $dp['port'],
		'name' => $dp['name'],
		'name_long' => $dp['name_long'],
		'network' => $dp['network'],
	);
	
	// Unset the class
	unset($reflection);
}

unset($dir);

ksort($protocols);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
        <title>GameQ - Supported Games</title>
        <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
        <style type="text/css">
            * {
                font-size: 9pt;
                font-family: Verdana, sans-serif;
            }
            h1 {
                font-size: 12pt;
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
            green {
            	font-color: #000000;
            }
        </style>
    </head>
    <body>
    <h1>GameQ3 - Supported Games (<?php echo count($protocols); ?>)</h1>
    <table>
    <thead>
        <tr>
            <td>Game Name</td>
            <td>Short Game Name</td>
            <td>GameQ Identifier</td>
            <td>Protocol</td>
            <td>Default port</td>
        </tr>
    </thead>
	<tbody>
<?php

foreach ($protocols as $className => $info) {
	$cls = empty($cls) ? ' class="uneven"' : '';
	
	printf("<tr%s><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n", $cls,
		$info['name_long'],
		$info['name'],
		$className,
		$info['protocol'],
		$info['network'] ? (is_int($info['port']) ? $info['port'] : 'not set') : 'not a network'
	);
}
?>
    </tbody>
    </table>
</body>
</html>