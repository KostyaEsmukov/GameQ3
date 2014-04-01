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

// http://wiki.sa-mp.com/wiki/Query_Mechanism

/*
Server not responds to 'players' packet when there are too many players on the server.

You may check result as follows:
if ($result['info']['online'] == true) {
	if ($result['info']['full'] == true) {
		// Result has got players
	} else {
		// Result has not got players
	}
}
*/
 
use GameQ3\Buffer;

class Samp extends \GameQ3\Protocols {

	protected $packets = array(
		'status' => "SAMP%si",
		'players' => "SAMP%sd",
		'rules' => "SAMP%sr",
	);

	protected $query_port = 7777;
	protected $ports_type = self::PT_SAME;
	
	protected $protocol = 'samp';
	protected $name = 'samp';
	protected $name_long = "San Andreas Multiplayer";
	
	protected $players_received;
	
	protected function construct() {
		$tail = "";
		$tail .= chr(strtok($this->query_addr, '.'));
		$tail .= chr(strtok('.'));
		$tail .= chr(strtok('.'));
		$tail .= chr(strtok('.'));
		$tail .= chr($this->query_port & 0xFF);
		$tail .= chr($this->query_port >> 8 & 0xFF);

		foreach($this->packets as $packet_type => $packet) {
			$this->packets[$packet_type] = sprintf($packet, $tail);
		}
	}
	
	
	public function init() {
		$this->queue('status', 'udp', $this->packets['status']);
		if ($this->isRequested('settings')) $this->queue('rules', 'udp', $this->packets['rules']);
		if ($this->isRequested('players')) {
			$this->players_received = false;
			$this->queue('players', 'udp', $this->packets['players']);
			$this->unCheck("players");
		}
	}

	protected function preFetch() {
		$this->result->addInfo('full', (!$this->isRequested('players') || $this->players_received));
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		} else
		if ($qid === 'rules') {
			return $this->_process_rules($requests['responses']);
		} else
		if ($qid === 'players') {
			return $this->_process_players($requests['responses']);
		}
	}
	
	
	protected function _preparePackets($packets) {
		// Make buffer so we can check this out
		$buf = new Buffer(implode('', $packets));

		// Grab the header
		$header = $buf->read(11);

		// Now lets verify the header
		if(substr($header, 0, 4) != "SAMP") {
			$this->debug('Unable to match SAMP response header. Header: '. $header);
			return false;
		}

		return $buf;
	}
	
	
	protected function _process_status($packets) {
		$buf = $this->_preparePackets($packets);
		if (!$buf) return false;
		
		// Pull out the server information
		$this->result->addGeneral('password', ($buf->readInt8() == 1));
		$this->result->addGeneral('num_players', $buf->readInt16());
		$this->result->addGeneral('max_players', $buf->readInt16());

		/// TODO: check other charsets
		$this->result->addGeneral('hostname', iconv('windows-1251', 'UTF-8', $buf->read($buf->readInt32())));
		$this->result->addGeneral('mode', $buf->read($buf->readInt32()));
		$this->result->addGeneral('map', $buf->read($buf->readInt32()));
	}
	
	protected function _process_rules($packets) {
		$buf = $this->_preparePackets($packets);
		if (!$buf) return false;
		
		// Number of rules
		$buf->readInt16();

		while ($buf->getLength()) {
			$key = $buf->readPascalString();
			$val = $this->filterInt($buf->readPascalString());
			
			if ($key === "version")
				$this->result->addGeneral('version', $val);
				
			$this->result->addSetting($key, $val);
		}
	}
	
	protected function _process_players($packets) {
		$buf = $this->_preparePackets($packets);
		if (!$buf) return false;

		$this->players_received = true;
		
		$this->result->addGeneral('num_players', $buf->readInt16());
		
		while ($buf->getLength()) {
			$id = $buf->readInt8();
			$name = $buf->readPascalString();
			$score = $buf->readInt32();
			$ping = $buf->readInt32();
			
			$this->result->addPlayer($name, $score, null, array('ping' => $ping));
		}
	}
}