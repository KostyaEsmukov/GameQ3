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
 
use GameQ3\Buffer;

class Squad extends \GameQ3\Protocols {

	protected $packets = array(
		'status'  => "\xff\xff\xff\xff\x54\x53\x6f\x75\x72\x63\x65\x20\x45\x6e\x67\x69\x6e\x65\x20\x51\x75\x65\x72\x79\x00",
		'rules'   => "\xff\xff\xff\xff\x54\x53\x6f\x75\x72\x63\x65\x20\x45\x6e\x67\x69\x6e\x65\x20\x51\x75\x65\x72\x79\x00",
		'players' => "\xff\xff\xff\xff\x54\x53\x6f\x75\x72\x63\x65\x20\x45\x6e\x67\x69\x6e\x65\x20\x51\x75\x65\x72\x79\x00",
	);

	protected $ports_type = self::PT_UNKNOWN;
	protected $protocol = 'squad';
	protected $name = 'squad';
	protected $name_long = "Squad";
	
	
	public function init() {
            $this->queue('status', 'udp', $this->packets['status']);
            if ($this->isRequested('settings')) $this->queue('rules', 'udp', $this->packets['rules']);
            if ($this->isRequested('players')) $this->queue('players', 'udp', $this->packets['players']);
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
		foreach($packets as $id => $packet) {
			$packets[$id] = substr($packet, 5);
		}

		return implode('', $packets);
	}

	protected function _process_status($packets) {
            $buf = new Buffer($this->_preparePackets($packets));
            $buf->jumpto(1);
            
            $this->result->addGeneral('hostname', $buf->readString());
            $mapstring = $buf->readString();
            $this->result->addGeneral('map', $mapstring);
            $this->result->addGeneral('mode', split(" ", $mapstring)[sizeof(split(" ", $mapstring))-1]);
            
            $buf->readString();$buf->readString();$buf->readInt8();$buf->readInt8();
            $this->result->addGeneral('num_players', $buf->readInt8());
            $this->result->addGeneral('max_players', $buf->readInt8());
            
	}
	
	protected function _process_players($packets) {
	}
	
	protected function _process_rules($packets) {
	}
	
}