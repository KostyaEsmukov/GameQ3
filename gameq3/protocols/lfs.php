<?php
/**
 * This file is part of GameQ3.
 *
 * GameQ3 is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GameQ3 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *
 */

namespace GameQ3\protocols;

// This class was created as an example of preFetch() usage, but... It works just fine.
// Don't forget to apply colorize filter to strip spans from hostname.
 
use GameQ3\UserException;

class Lfs extends \GameQ3\Protocols {
	protected $protocol = 'lfs';
	protected $name = 'lfs';
	protected $name_long = "Live for Speed";
	
	protected $network = false;
	
	protected $url = "/hoststatus/?h=%s";
	protected $query_addr = "www.lfsworld.net";
	protected $query_port = 80;
	
	protected $connect_string = 'lfs://join={HOSTNAME}';

	protected function construct() {
		if (!isset($this->server_info['hostname']))
			throw new UserException("Hostname must be set for lfs protocol");
			
		$this->url = sprintf($this->url, urlencode($this->server_info['hostname']));
	}
	
	protected function getIdentifier() {
		return $this->server_info['hostname'];
	}
	
	protected function genConnectString() {
		return str_replace('{HOSTNAME}', rawurlencode($this->server_info['hostname']), $this->connect_string);
	}
	
	public function init() {
		$this->queue('status', 'http', $this->url);
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		}
	}
	
	protected function _process_status($packets) {
		$data = $packets[0];
		unset($packets);
		
		preg_match("#<div class=\"HostName\">(.*?)</div>#i", $data, $match);
		if (!isset($match[1])) return false;
		
		$this->result->addGeneral('hostname', $match[1]);
		
		preg_match_all("#<div id=\"([^\"]*)\"[^>]*><div class=\"Field1\">([^<]*)</div><div class=\"Field2\">([^<]*)</div></div>#i", $data, $match_all, PREG_SET_ORDER);
		if (!is_array($match_all)) return;
		
		foreach($match_all as $match) {
			if (!isset($match[3])) continue;
			switch($match[1]) {
				case 'Mode':
					$this->result->addGeneral('mode', $match[3]);
					break;
				case 'Track':
					$this->result->addGeneral('map', $match[3]);
					break;
				case 'Version':
					$this->result->addGeneral('version', $match[3]);
					break;
				case 'Conns':
					$c = explode('/', $match[3]);
					if (!isset($c[1])) break;
					$n = intval(trim($c[0]));
					$m = intval(trim($c[1]));

					$this->result->addGeneral('num_players', $n);
					$this->result->addGeneral('max_players', $m);
					break;
				case 'Settings':
					break;
				default:
					$this->result->addSetting($match[2], $match[3]);
			}
		}
		
		preg_match("#<div id=\"Users\" class=\"DataCont\"><div class=\"Field3\">(.*?)</div></div>#i", $data, $match);
		if (!isset($match[1])) return false;
		
		preg_match_all("#<a class=\"User\" href=\"([^\"]*)\"[^>]*>([^>]*)</a>#i", $match[1], $match_all, PREG_SET_ORDER);
		if (!is_array($match_all)) return false;
		
		foreach($match_all as $match) {
			if (!isset($match[2])) continue;
			$name = $match[2];
			$url = $match[1];
			
			// Sometimes this happens.
			// Look at this guy: http://www.lfsworld.net/?win=stats&amp;racer=dzsed%E1j
			if (empty($name)) {
				$urld = html_entity_decode($url, ENT_HTML5, 'UTF-8');
				list(,$urld) = explode('?', $urld, 2);
				$urld = explode('&', $urld);
				foreach($urld as $pair) {
					$p = explode('=', $pair);
					if ($p[0] === "racer")
						$name = $p[1];
				}
			}
			
			$this->result->addPlayer($name, null, null, array('url' => $url));
		}

/*
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en"><head><meta http-equiv="Pragma" content="no-cache" /><meta http-equiv="Expires" content="-1" /><meta http-equiv="Cache-Control" content="no-cache" /><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" /><link rel="stylesheet" type="text/css" href="http://www.lfsworld.net/hoststatus/styles/default.css" /><title>LFS HostStatus</title></head><body>
<div class="HostName"><span style="color: #000000"><span style="color: #000000;">[AA] </span><span style="color: #FFFF00;">Demo </span><span style="color: #000000;">FBM</span></span></div>
<div id="Mode" class="DataCont"><div class="Field1">Mode</div><div class="Field2">Demo, Race 3 laps</div></div>
<div id="Track" class="DataCont"><div class="Field1">Track</div><div class="Field2">Blackwood GP</div></div>
<div id="Cars" class="DataCont"><div class="Field1">Cars</div><div class="Field2">FBM</div></div>
<div id="Settings" class="DataCont"><div class="Field1">Settings</div><div class="Field2"><strong><a class="gen" href="http://www.lfsworld.net/remote/?host=%5BAA%5D+Demo+FBM" target="_blank" title="Spectate this host with LFS Remote">R</a><span title="Racers are allowed to vote">V</span></strong> <i><span title="Joining mid-race is allowed">m</span></i></div></div>
<div id="Version" class="DataCont"><div class="Field1">Version</div><div class="Field2">0.6E</div></div>
<div id="Conns" class="DataCont"><div class="Field1">Conns</div><div class="Field2">14 / 15</div></div>
<div id="Users" class="DataCont"><div class="Field3"><a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=snowman781" target="_blank">snowman781</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=dzsed%E1j" target="_blank"></a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=JakBot" target="_blank">JakBot</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=-Jarek-" target="_blank">-Jarek-</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=eduardo579" target="_blank">eduardo579</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=Devils+lil+Helper" target="_blank">Devils lil Helper</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=jirkahrb" target="_blank">jirkahrb</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=teme10" target="_blank">teme10</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=HighDriver" target="_blank">HighDriver</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=kmalos" target="_blank">kmalos</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=ldlian987" target="_blank">ldlian987</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=CsK" target="_blank">CsK</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=Moslow" target="_blank">Moslow</a>, <a class="User" href="http://www.lfsworld.net/?win=stats&amp;racer=delix_plus710" target="_blank">delix_plus710</a></div></div>
</body></html>
*/
		
	}
	
}