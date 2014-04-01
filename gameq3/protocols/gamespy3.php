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

/*
Players list is sometimes not full due to strange ut3 query protocol implementation.
Result is assumed not full if:
1. Some values for some players are not full. Such arrays with strings are skipped, with integers are appended with zeros.
2. Players count is less than num_players

You may check result as follows:
if ($result['info']['online'] == true) {
	if ($result['info']['full'] == true) {
		// Result has full list of players
	} else {
		// Result is not full
	}
}
*/

use GameQ3\Buffer;

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

	
	protected $protocol = 'gamespy3';
	protected $name = 'gamespy3';
	protected $name_long = "Gamespy3";
	
	protected $ports_type = self::PT_UNKNOWN;
	
	protected $challenge = true;
	protected $stage = null;

	protected $settings;
	protected $full;
	
	public function init() {
		if ($this->isRequested('teams'))
			$this->result->setIgnore('teams', false);

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
		$buf = new Buffer($packets[0]);
		
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
			$buf = new Buffer($packet);

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
			case 'hostport':
				$this->setConnectPort($val);
				$this->result->addSetting($key, $val);
				break;
			default:
				$this->result->addSetting($key, $val);
		}
	}
	
	// Ut3, Bf2142
	protected function _parse_rules_break(Buffer &$buf) {
		// Sections start with \x01-\x02 
		$la = ord($buf->lookAhead(1));
		if ($la === 0) {
			$this->debug('Third \x00 in rules section. Assuming we are still in rules section. $buf->lookAhead(6): ' . bin2hex($buf->lookAhead(6)));
			return false;
		}
		if ($la <= 2) return true;
		return false;
	}
	
	protected function _parse_arrays_break(Buffer &$buf) {
		return true;
	}
	
	protected function _parse_settings() {
		$s_cnt = count($this->settings);
		if ($s_cnt % 2 != 0) {
			$this->debug('$this->settings count is not even, probably it is corrupted. Settings skipped.');
			return false;
		}
		for ($i=0; $i < $s_cnt; $i+=2) {
			$this->_put_var($this->settings[$i], $this->filterInt($this->settings[$i+1]));
		}
		unset($this->settings);
		return true;
	}
	
	protected function _process_all($packets) {
		$packets = $this->_preparePackets($packets);
		
		if ($packets === false) return false;

		$this->settings = array();
		$this->full = true;
		$fields = array();
		foreach($packets as $data) { // Loop thru packets
			$buf = new Buffer($data);
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
						if ($section_num == 0) {
							if ($this->_parse_rules_break($buf)) break;
						} else {
							break;
						}
					}

					if ($section_num == 0) { // Process scalars
						$this->settings [] = $field;
					} else { // Process arrays
					
						// Ut3:
						// 00 00 73 63 6f 72 65 5f  00                         ..score_ .
						// causes OOB exception.
						if (!$buf->getLength()) break;
						
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

							// End of array. Arma2 returns team_ array which consists of ...\x00\x00\x00\x00...
							if (
								$val === ""
								&& (count($fields[$section_num][$field]) >= count(reset($fields[$section_num])))
								&& $this->_parse_arrays_break($buf)
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

		$this->_parse_settings();
		
		$res = true;
		if (!empty($fields[1])) $res = $res && $this->_parse_arrays('players', $fields[1]);
		if (!empty($fields[2])) $res = $res && $this->_parse_arrays('teams', $fields[2]);
		
		// Check fullness of players and correct num_players.
		if ($res) {
			$pl_cnt = $this->result->count('players');
			$num_pl = $this->result->getGeneral('num_players');
			if (!is_int($num_pl)) return false;
			
			if ($num_pl < $pl_cnt) {
				$this->result->addGeneral('num_players', $pl_cnt);
			} else
			if ($num_pl > $pl_cnt) {
				$this->full = false;
			}
			$this->result->addInfo('full', $this->full);
		}
		
		return $res;
	}
	
	protected function _parse_arrays($task, &$data) {
		$cnt = false;
		$fields = array();
		$fields_truncated = array();

		$bot_players = 0;

		foreach($data as $field => &$arr) {
		/*
			It's OK for UT3 to send something like that:
			0049 5376 7961 746f 7969 4900 0073 636f  .ISvyatoyiI..sco
			7265 5f00 0034 3200 3130 3200 3735 0032  re_..42.102.75.2
			3300 3530 0035 3200 3334 0032 3900 3732  3.50.52.34.29.72
			0030 0030 0000 7069 6e67 5f00 0037 3600  .0.0..ping_..76.
			0074 6500                                .te.
			                                          --
			We should think that it is normal...
		*/
			if ($task === 'players') {
				if (substr($field, -1) !== "_") {
					$this->debug("Arrays are not consistent. Wrong field skipped: " . $field);
					continue;
				}
				$field_name = substr($field, 0, -1);
			} else
			if ($task === 'teams') {
				if (substr($field, -2) !== "_t") {
					$this->debug("Arrays are not consistent. Wrong field skipped: " . $field);
					continue;
				}
				$field_name = substr($field, 0, -2);
			} else {
				$this->debug("Bad task");
				return false;
			}

			$fields[$field_name]= $field;
			if ($cnt == false) {
				$cnt = count($arr);
				continue;
			}

			$arr_cnt = count($arr);
			if ($cnt > $arr_cnt) {
				/*
				Ut3 truncates arrays if they don't fit in current packet. Thus we get wrong data.
				Example:
				
				...
				52 00 42 61 6e 67 42 61  6c 74 68 61 7a 61 72 00    R.BangBa lthazar.
				72 75 6e 6e 79 62 61 62  69 74 00 24 63 72 65 77    runnybab it.$crew
				66 61 63 65 00 41 6c 70  68 61 72 64 38 38 38 00    face.Alp hard888.
				41 6e 56 49 52 55 53 00  53 41 58 41 4e 59 00 72    AnVIRUS. SAXANY.r
				65 72 65 72 65 33 33 33  33 33 33 00 5b 72 75 73    erere333 333.[rus
				5d 68 61 6e 74 00 6f 62  62 62 7a 7a 7a 65 6e 00    ]hant.ob bbzzzen.
				67 6f 62 79 73 00 4a 6f  6e 69 78 4c 00 00 73 63    gobys.Jo nixL..sc
				6f 72 65 5f 00 00 00 70  00                         ore_...p .     --
				                                                    --------
				Next packet (prepared) doesn't contains score too:
				01 70 69 6e 67 5f 00 00  38 34 00 33 36 00 37 32    .ping_.. 84.36.72
				00 34 38 00 34 38 00 36  34 00 31 30 38 00 32 33    .48.48.6 4.108.23
				32 00 31 36 34 00 36 38  00 39 32 00 31 35 36 00    2.164.68 .92.156.
				39 32 00 31 30 34 00 36  34 00 32 38 00 38 34 00    92.104.6 4.28.84.
				34 34 00 34 34 00 34 38  00 00 74 65 61 6d 5f 00    44.44.48 ..team_.
				00 30 00 31 00 30 00 30  00 31 00 30 00 31 00 30    .0.1.0.0 .1.0.1.0
				00 30 00 31 00 31 00 30  00 30 00 31 00 31 00 31    .0.1.1.0 .0.1.1.1
				00 31 00 30 00 30 00 30  00 00 64 65 61 74 68 73    .1.0.0.0 ..deaths


				*/
				$this->full = false;
				$first_int = is_int(reset($arr));
				$this->debug("Array is truncated" . (!$first_int ? "(skipped)" : "" ) . ", cnt is " . $cnt . ", array cnt is " . $arr_cnt . ". Field: " . $field);
				if ($first_int) {
					$fields_truncated[$field_name] = true;
				} else {
					unset($fields[$field_name]);
				}
			} else
			if ($cnt < $arr_cnt) {
				$this->debug("Arrays are not consistent, skipped last " . ($arr_cnt-$cnt) . " items of " . $field);
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
				if (isset($fields_truncated[$field_name])) {
					if (!isset($data[$field][$i]) || !is_int($data[$field][$i])) // last value can be a string - new field (truncated). 
						$data[$field][$i] = 0;
				} else
				if (!isset($data[$field][$i])) {
					$this->debug("Arrays are not consistent");
					return false;
				}
				$more[$field_name] = $data[$field][$i];
			}
			
			if ($task === 'players') {
				// Sometimes player_ keys contain space (0x20) before the nickname
				$name = trim($more['player']);
				if ($name === "") {
					$this->debug("Empty player, skipped");
					continue;
				}
				
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
			if (!$this->result->issetGeneral('bot_players'))
				$this->result->addGeneral('bot_players', $bot_players);
		}
		
		return true;
	}

}