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

/*
Notice: sometimes players list is truncated (even with "\players\" request.
We receive something like this:
... player_31\MEXAHIK.\score_31\0\ping_31\106\team_31\2\player_32\Gre\final\\queryid\375.3"
                                                        -------------
There is nothing really bad (I think) if there will not be some players.
But the point is that in this case teams are not received.
This is very bad. I can't do anything with it.
The only solution is to remember teams' names and use them when teams are empty.

You may check result as follows:
if ($result['info']['online'] == true) {
	if ($result['info']['full'] == true) {
		// Result has full list of players and teams
	} else {
		// Result is not full
	}
}
*/

class Gamespy extends \GameQ3\Protocols {

	protected $packets = array(
		'all' => "\\status\\",
		
		/*
		'players' => "\x5C\x70\x6C\x61\x79\x65\x72\x73\x5C",
		'details' => "\x5C\x69\x6E\x66\x6F\x5C",
		'basic' => "\x5C\x62\x61\x73\x69\x63\x5C",
		'rules' => "\x5C\x72\x75\x6C\x65\x73\x5C",
		*/
	);

	protected $protocol = 'gamespy';
	protected $name = 'gamespy';
	protected $name_long = "Gamespy";
	
	protected $ports_type = self::PT_UNKNOWN;

	protected $teams;
	
	public function init() {
		if ($this->isRequested('teams'))
			$this->result->setIgnore('teams', false);

		$this->queue('all', 'udp', $this->packets['all']);
		
		/*
		$this->queue('players', 'udp', $this->packets['players']);
		$this->queue('details', 'udp', $this->packets['details']);
		$this->queue('basic', 'udp', $this->packets['basic']);
		$this->queue('rules', 'udp', $this->packets['rules']);
		*/
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'all') {
			return $this->_process_all($requests['responses']);
		}
	}
	
	protected function _put_var($key, $val) {
		switch($key) {
			case 'hostname':
				$this->result->addGeneral('hostname', iconv("ISO-8859-1//IGNORE", "utf-8", $val));
				break;
			// case 'maptitle':
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
	
	protected function _preparePackets($packets) {
		// Holds the new list of packets, which will be stripped of queryid and ordered properly.
		$packets_ordered = array();
		$final = false;
		
		// Single packets may not contain queryid
		$single = (count($packets) == 1);

		// Loop thru the packets
		foreach ($packets as $packet) {
			if(preg_match("/^(.*)\\\\queryid\\\\([^\\\\]+)(.*)$/", $packet, $matches) === FALSE) {
				if (!$single) {
					$this->debug('An error occured while parsing the status packets');
					return false;
				} else {
					$packets_ordered[0] = $packet;
					continue;
				}
			}

			// Lets make the key proper incase of decimal points
			if(strstr($matches[2], '.')) {
				list($req_id, $req_num) = explode('.', $matches[2]);

				$key = $req_num;
			} else {
				$key = $matches[2];
			}
			
			if (isset($matches[3]) && $matches[3] == "\\final\\")
				$final = true;

			// Add this stripped queryid to the new array with the id as the key
			$packets_ordered[$key] = $matches[1];
		}

		// Sort the new array to make sure the keys (query ids) are in the proper order
		ksort($packets_ordered, SORT_NUMERIC);
		$result = implode('', $packets_ordered);
		
		if ($result{0} !== "\\") {
			$this->debug("Wrong response format");
			return false;
		}
		
		// IL-2 has final before queryid
		if (!$final) {
			if (substr($result, -7) !== "\\final\\") {
				$this->debug("Wrong response format");
				return false;
			} else {
				$result = substr($result, 1, -7);
			}
		} else {
			$result = substr($result, 1);
		}
		
		return $result;
	}
	
	
	protected function _process_all($packets) {
		$packet = $this->_preparePackets($packets);
		if (!$packet) return false;
		
		$data = explode("\\", $packet);
		
		unset($packets, $packet);
		
		$full = true;
		
		// BF1942: Teams' tickets defined in rules. We should save these values.
		$this->teams = array();
		
		$data_cnt = count($data);
		if ($data_cnt % 2 !== 0) {
			$this->debug("Not even count of rules");
			$full = false;
			$data_cnt--;
			unset($data[$data_cnt]);
		}
		
		$players = array();
		$teams = array();
		
		for($i=0; $i < $data_cnt; $i+=2) {
			$key = $data[$i];
			$val = $this->filterInt($data[$i+1]);
			
			$dt_pos = strpos($key, "_t");
			$dp_pos = strpos($key, "_");
			if ($dt_pos !== false && is_numeric(substr($key, $dt_pos + 2))) {
				$index = $this->filterInt(substr($key, $dt_pos + 2));
				$item = substr($key, 0, $dt_pos);
				if (!isset($teams[$index])) $teams[$index] = array();
				$teams[$index][$item] = $val;
			} else
			if ($dp_pos !== false && is_numeric(substr($key, $dp_pos + 1))) {
				$index = $this->filterInt(substr($key, $dp_pos + 1));
				$item = substr($key, 0, $dp_pos);
				
				// BF1942
				if ($item === "teamname") {
					if (!isset($teams[$index+1])) $teams[$index+1] = array();
					$teams[$index+1]['team'] = $val;
				} else {
					if (!isset($players[$index])) $players[$index] = array();
					$players[$index][$item] = $val;
				}
			} else {
				$this->_put_var($key, $val);
			}
		}
		
		if (!empty($players)) {
			// Remember fields of the first player
			$fields = array_keys(reset($players));
			$fields_cnt = count($fields);
			
			$player_key = false;
			
			foreach(array('player', 'playername') as $key) {
				if (in_array($key, $fields)) {
					$player_key = $key;
					break;
				}
			}
			
			if ($player_key === false) {
				$this->debug("Arrays are not consistent");
				return false;
			}
			
			foreach($players as $more) {
				if (count($more) !== $fields_cnt) {
					$this->debug("Invalid player, skipped");
					$full = false;
					continue;
				}
				$cntn = false;
				foreach($fields as $field) {
					if (!isset($more[$field])) {
						$this->debug("Invalid player, skipped");
						$cntn = true;
						$full = false;
						break;
					}
				}
				if ($cntn) continue;

				$name = iconv("ISO-8859-1//IGNORE", "utf-8", $more[$player_key]); // some chars like (c) should be converted to utf8
				$score = isset($more['score']) ? $more['score'] : (isset($more['frags']) ? $more['frags'] : null);
				$teamid = isset($more['team']) ? $more['team'] : null;

				unset($more[$player_key], $more['score'], $more['frags'], $more['team']);
				
				$this->result->addPlayer($name, $score, $teamid, $more);
			}
		}
		
		if ($full) {
			$players_cnt = count($players);
			
			// BF1942
			if ($players_cnt > $this->result->getGeneral('num_players')) {
				$this->result->addGeneral('num_players', $players_cnt);
			} else
			// Il-2
			if ($players_cnt < $this->result->getGeneral('num_players'))
				$full = false;
		}
		
		// We know that teams can be empty. Assume we don't fail when teams are wrong.
		if (!empty($teams)) {
			// Remember fields of the first player
			$fields = array_keys(reset($teams));
			$fields_cnt = count($fields);
			
			if (!in_array('team', $fields)) {
				$this->debug("Arrays are not consistent");
				return;
			}
			
			$teams_r = array();
			// Ensure we have full teams array
			foreach($teams as $index => $more) {
				if (count($more) !== $fields_cnt) {
					$this->debug("Invalid team, broken");
					$teams_r = array();
					$full = false;
					break;
				}
				$brk = false;
				foreach($fields as $field) {
					if (!isset($more[$field])) {
						$this->debug("Invalid team, broken");
						$teams_r = array();
						$brk = true;
						$full = false;
						break;
					}
				}
				if ($brk) {
					break;
				}

				$team = $more['team'];
				unset($more['team']);
				$teams_r[$index] = array('team' => $team, 'more' => $more);
			}
			
			foreach($teams_r as $index => $val) {
			
				if (!empty($this->teams[$index])) {
					foreach($this->teams[$index] as $k => $v) {
						$val['more'][$k] = $v;
					}
				}
				
				$this->result->addTeam($index, $val['team'], $val['more']);
			}
		}
		
		$this->result->addInfo('full', $full);

		return true;
	}
	
}