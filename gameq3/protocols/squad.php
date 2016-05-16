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
		'status'  => "",
		'rules'   => "",
		'players' => "",
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
            
	}
	
	protected function _process_players($packets) {
            
	}
	
	protected function _process_rules($packets) {
            
	}
	
}