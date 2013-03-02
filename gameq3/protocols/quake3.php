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
 
class Quake3 extends \GameQ3\Protocols {

	protected $packets = array(
		'status' => "\xFF\xFF\xFF\xFF\x67\x65\x74\x73\x74\x61\x74\x75\x73\x0A",
		//self::PACKET_DETAILS => "\xFF\xFF\xFF\xFFgetinfo\x00",
	);

	protected $port = 27960; // Default port, used if not set when instanced
	protected $protocol = 'quake3';
	protected $name = 'quake3';
	protected $name_long = "Quake 3";

	public function init() {
		$this->queue('status', 'udp', $this->packets['status'], array('response_count' => 1));
	}

	public function processRequests($qid, $requests) {
		$this->addPing($requests['ping']);
		$this->addRetry($requests['retry_cnt']);
		if ($qid === 'status') {
			$this->_process_status($requests['responses']);
		}
	}

	private function _process_status($packets) {
		$buf = new \GameQ3\Buffer($packets[0]);

		// Grab the header
		$header = $buf->read(20);

		// Now lets verify the header
		if($header != "\xFF\xFF\xFF\xFFstatusResponse\x0A\x5C") {
			$this->debug('Unable to match Gamespy 3 challenge response header. Header: '. $header);
			return false;
		}

		// First section is the server info, the rest is player info
		$server_info = $buf->readString("\x0A");
		$players_info = $buf->getBuffer();

		unset($buf);

		// Make a new buffer for the server info
		$buf_server = new \GameQ3\Buffer($server_info);

		$private_players = false;
		$max_players = false;
		
		// Key / value pairs
		while ($buf_server->getLength())
		{
			$key = $buf_server->readString('\\');
			$val = $buf_server->readStringMulti(array('\\', "\x0a"), $delimfound);
			
			switch($key) {
				case 'g_gametype': $this->result->addCommon('mode', $val); break;
				case 'mapname': $this->result->addCommon('map', $val); break;
				case 'shortversion': $this->result->addCommon('version', $val); break;
				case 'sv_hostname': $this->result->addCommon('hostname', $val); break;
				case 'sv_privateClients': $private_players = intval($val); break;
				case 'ui_maxclients': $max_players = intval($val); break;
				case 'pswrd': $this->result->addCommon('password', ($val != '0')); break;
				case 'sv_punkbuster': $this->result->addCommon('secure', ($val != '0')); break;
			}
			$this->result->addSetting($key,$val);
			

			if ($delimfound === "\x0a")
				break;
		}
		
		if (!is_int($max_players)) {
			$this->debug('Max_players is not an integer in gamespy3: '. var_export($max_players, true));
			return false;
		}
		
		if (is_int($private_players)) {
			$this->result->addCommon('private_players', $private_players);
			$max_players -= $private_players;
		}
		$this->result->addCommon('max_players', $max_players);

		// Explode the arrays out
		$players = explode("\x0A", $players_info);

		// Remove the last array item as it is junk
		array_pop($players);

		// Add total number of players
		$this->result->addCommon('num_players', count($players));

		// Loop the players
		foreach($players AS $player_info) {
			$buf = new \GameQ3\Buffer($player_info);

			$score = $buf->readString("\x20");
			$ping = $buf->readString("\x20");
			
			// Skip first "
			$buf->skip(1);
			$name = trim($buf->readString('"'));
			
			// Add player info
			$this->result->addPlayer($name, $score, null, array('ping' => $ping));
		}
	}
}