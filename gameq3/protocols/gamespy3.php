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

// References:
// http://bf2browser.googlecode.com/svn/trunk/ss.py
// http://wiki.unrealadmin.org/UT3_query_protocol

class Gamespy3 extends \GameQ3\Protocols {

	protected $packets = array(
		'challenge' => "\xFE\xFD\x09\x10\x20\x30\x40",
		'all' => "\xFE\xFD\x00\x10\x20\x30\x40%s\xFF\xFF\xFF\x01",
		                                      // (1) (2) (3) (4)
		/*
			(1) - request host information (rules) (\x00 or \xFF)
			(2) - request players information (\x00 or \xFF)
			(3) - request teams information (\x00 or \xFF)
			(4) - response format (\x00 or \x01)
		*/
	);

	protected $port = false; // Default port, used if not set when instanced
	protected $protocol = 'gamespy3';
	protected $name = 'gamespy3';
	protected $name_long = "Gamespy3";
	
	protected $challenge = true;
	protected $stage = null;
	
	public function init() {
		if ($this->challenge) {
			$this->queue('all', 'udp', $this->packets['challenge'], array('response_count' => 1));
			$this->stage = 'challenge';
		} else {
			$this->queue('all', 'udp', $this->packets['all']);
			$this->stage = 'all';
		}
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'all') {
			if ($this->stage === 'challenge') {
				return $this->_process_challenge($requests['responses']);
			} else
			if ($this->stage === 'all') {
				return $this->_process_all($requests['responses']);
			}
		}
	}
	
	protected function _process_challenge($packets) {
		$buf = new \GameQ3\Buffer($packets[0]);
		
		$buf->skip(5);
		$challenge = intval($buf->readString());


		$challenge_result = sprintf(
			"%c%c%c%c",
			( $challenge >> 24 ),
			( $challenge >> 16 ),
			( $challenge >> 8 ),
			( $challenge >> 0 )
			);

		$this->queue('all', 'udp', sprintf($this->packets['all'], $challenge_result));
		$this->stage = 'all';
	}
	
	protected function _preparePackets($packets) {
		$return = array();
		
		$last_packet = false;
		// Get packet index, remove header
		foreach ($packets as $index => $packet) {
			// Make new buffer
			$buf = new \GameQ3\Buffer($packet);

			// Skip the header
			if ($buf->read(1) !== "\x00") {
				$this->debug("Wrong packet header");
				continue;
			}
			
			$buf->skip(4); // identifier
			
			if ($buf->read(9) !== "splitnum\x00") {
				$this->debug("Wrong packet header");
				continue;
			}
			
			$packet_number = $buf->readInt8();
			
			// last packet
			if ($packet_number >= 0x80) {
				$last_packet = true;
				$packet_number -= 0x80;
			}

			$return[$packet_number] = $buf->getBuffer();
		}

		unset($buf, $packets);
		
		if (!$last_packet) {
			$this->debug("No last packet received");
			return false;
		}
		
		for($i = 0; $i < count($return); $i++) {
			if (!isset($return[$i])) {
				$this->debug("Packet " . $i . " wasn't received");
				return false;
			}
		}
		// prepare for foreach loop
		ksort($return, SORT_NUMERIC);

		return $return;
	}
	
	protected function _put_var($key, $val) {
		switch($key) {
			case 'hostname':
				$this->result->addGeneral('hostname', $val);
				break;
			case 'mapname':
				$this->result->addGeneral('map', $val);
				break;
			case 'gamever':
				$this->result->addGeneral('version', $val);
				break;
			case 'gametype':
				$this->result->addGeneral('mode', $val);
				break;
			case 'numplayers':
				$this->result->addGeneral('num_players', $val);
				break;
			case 'maxplayers':
				$this->result->addGeneral('max_players', $val);
				break;
			case 'password':
				$this->result->addGeneral('password', $val == 1);
				break;
			default:
				$this->result->addSetting($key, $val);
		}
	}
	
	protected function _process_all($packets) {
		$packets = $this->_preparePackets($packets);
		
		if ($packets === false) return false;

		$fields = array();
		foreach($packets as $data) { // Loop thru packets
			$buf = new \GameQ3\Buffer($data);
			while ($buf->getLength()) { // Loop thru sections
				$section_num = $buf->readInt8();
				
				// Prepare field for array sections
				if ($section_num != 0) {
					if (!isset($fields[$section_num]))
						$fields[$section_num] = array();
				}
				
				while($buf->getLength()) { // Loop thru fields
					$field = $buf->readString();

					// End of section
					if ($field === "") {
						break;
					}

					if ($section_num == 0) { // Process scalars
						$val = $this->filterInt($buf->readString());
						$this->_put_var($field, $val);
					} else { // Process arrays
						$element_num = $buf->readInt8();

						if (!isset($fields[$section_num][$field]))
							$fields[$section_num][$field] = array();

						// Value index
						$val_index = count($fields[$section_num][$field]);
						
						// We have extra items, remove them
						if ($val_index > $element_num) {
							foreach($fields[$section_num][$field] as $id => $val) {
								if ($id >= $element_num) {
									unset($fields[$section_num][$field][$id]);
									$val_index--;
								}
							}
						}

						while($buf->getLength()) { // Loop thru values of an array
							$val = $buf->readString();

							// End of array. Arma2 returns team_ array that consists of ...\x00\x00\x00\x00...
							if (
								$val === ""
								&& (count($fields[$section_num][$field]) >= count(reset($fields[$section_num])))
							) {
								break;
							}
							
							$fields[$section_num][$field][$val_index]= $this->filterInt($val);
							$val_index++;
						}
					}
				}
			}
		}
		
		$res = true;
		if (!empty($fields[1])) $res = $res && $this->_parse_arrays('players', $fields[1]);
		if (!empty($fields[2])) $res = $res && $this->_parse_arrays('teams', $fields[2]);
		
		return $res;
	}
	
	protected function _parse_arrays($task, &$data) {
		$cnt = false;
		$fields = array();
		if ($task === 'players') {
			$bot_players = 0;
		}
		foreach($data as $field => &$arr) {
			if ($task === 'players') {
				if (substr($field, -1) !== "_") {
					$this->debug("Arrays are not consistent");
					return false;
				}
				$field_name = substr($field, 0, -1);
			} else
			if ($task === 'teams') {
				if (substr($field, -2) !== "_t") {
					$this->debug("Arrays are not consistent");
					return false;
				}
				$field_name = substr($field, 0, -2);
			}
			$fields[$field_name]= $field;
			if ($cnt == false) {
				$cnt = count($arr);
				continue;
			}
			if ($cnt !== count($arr)) {
				$this->debug("Arrays are not consistent");
				return false;
			}
		}

		if ($task === 'players') {
			if (!isset($fields['player'])) {
				$this->debug("Arrays are not consistent");
				return false;
			}
		} else
		if ($task === 'teams') {
			if (!isset($fields['team'])) {
				$this->debug("Arrays are not consistent");
				return false;
			}
		}
		
		for($i=0; $i < $cnt; $i++) {
			$more = array();
			foreach($fields as $field_name => $field) {
				if (!isset($data[$field][$i])) {
					$this->debug("Arrays are not consistent");
					return false;
				}
				$more[$field_name] = $data[$field][$i];
			}
			
			if ($task === 'players') {
				// Sometimes player_ keys contain space (0x20) before the nickname
				$name = trim($more['player']);
				$score = isset($more['score']) ? $more['score'] : null;
				$teamid = isset($more['team']) ? $more['team'] : null;
				// Arma2 empty teams
				if ($teamid === "") $teamid = null;
				unset($more['player'], $more['score'], $more['team']);
				if (isset($more['AIBot']) && $more['AIBot'] == 1)
					$bot_players++;
					
				$this->result->addPlayer($name, $score, $teamid, $more);
			} else
			if ($task === 'teams') {
				$team = $more['team'];
				unset($more['team']);
				$this->result->addTeam(($i+1), $team, $more);
			}
		}
		
		if ($task === 'players') {
			$this->result->addGeneral('bot_players', $bot_players);
		}
		
		return true;
	}

}