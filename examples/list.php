<?php

require __DIR__ . '/../gameq3/gameq3.php';

$gq = new \GameQ3\GameQ3();

$protocols = $gq->getAllProtocolsInfo();

$supported_games = count($protocols);

$content_table = "";

function filterNull($v) {
	if (is_null($v)) return "<i>not set</i>";
	return $v;
}

foreach ($protocols as $classname => $dp) {
	$cls = empty($cls) ? ' class="uneven"' : '';
	
	$l = "\t\t\t\t"; // idents
	$l .= "<tr" . $cls . ">";
	$l .= sprintf("<td class='left'>%s</td><td class='left'>%s</td><td>%s</td><td>%s</td>", $classname, $dp['name_long'], $dp['name'], $dp['protocol']);
	if ($dp['network']) {
		if ($dp['ports_type_info']['connect_port'] === false || $dp['ports_type_info']['query_port'] === false) {
			if ($dp['ports_type_info']['connect_port'] !== false) {
				$v = $dp['connect_port'];
			} else {
				if ($dp['ports_type_info']['query_port'] !== false) {
					$v = $dp['query_port'];
				} else {
					$v = null;
				}
			}
			
			$l .= sprintf("<td colspan='2'>%s</td>", filterNull($v));
		} else {
			$l .= sprintf("<td>%s</td><td>%s</td>", filterNull($dp['query_port']), filterNull($dp['connect_port']));
		}
		$l .= sprintf("<td><span class='pt'>%s</span></td>", $dp['ports_type_string']);
	} else {
		$l .= "<td colspan='3'><i>Not a network protocol</i></td>";
	}
	$l .= "<td>" . filterNull($dp['connect_string']) . "</td>";
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
				font-size: 11px;
			}
			table td {
				background-color: #F9F9F9;
			}
			table tr.uneven td {
				background-color:#FFF;
			}
			table td, table th {
				padding: 5px 6px;
				text-align: center;
			}
			.left {
				text-align: left;
			}
			.pt {
				font-size: 10px;
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