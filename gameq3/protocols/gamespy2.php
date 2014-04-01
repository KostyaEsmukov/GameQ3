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

class Gamespy2 extends \GameQ3\Protocols {

	protected $packets = array(
		'details' => "\xFE\xFD\x00\x43\x4F\x52\x59\xFF\x00\x00",
		'players' => "\xFE\xFD\x00\x43\x4F\x52\x59\x00\xFF\xFF",
	);

	protected $protocol = 'gamespy2';
	protected $name = 'gamespy2';
	protected $name_long = "Gamespy2";
	
	protected $ports_type = self::PT_UNKNOWN;

	
	public function init() {
		if ($this->isRequested('teams'))
			$this->result->setIgnore('teams', false);

		$this->queue('details', 'udp', $this->packets['details'], array('response_count' => 1));
		if ($this->isRequested('players'))
			$this->queue('players', 'udp', $this->packets['players'], array('response_count' => 1));
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'details') {
			return $this->_process_details($requests['responses']);
		} else
		if ($qid === 'players') {
			return $this->_process_players($requests['responses']);
		}
	}
	
	protected function _put_var($key, $val) {
		switch($key) {
			case 'hostname':
				$this->result->addGeneral('hostname', iconv("ISO-8859-1//IGNORE", "utf-8", $val));
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
	
	protected function _process_details($packets) {
		$buf = new Buffer($packets[0]);
		
		// Make sure the data is formatted properly
		if($buf->lookAhead(5) != "\x00\x43\x4F\x52\x59") {
			$this->debug("Data for ".__METHOD__." does not have the proper header. Header: ".$buf->lookAhead(5));
			return false;
		}

		// Now verify the end of the data is correct
		if($buf->readLast() !== "\x00") {
			$this->debug("Data for ".__METHOD__." does not have the proper ending. Ending: ".$buf->readLast());
			return false;
		}

		// Skip the header
		$buf->skip(5);
		
		// Loop thru all of the settings and add them
		while ($buf->getLength()) {
			$key = $buf->readString();
			$val = $buf->readString();

			// Check to make sure there is a valid pair
			if(strlen($key) > 0) {
				$this->_put_var($key, $this->filterInt($val));
			}
		}

	}
	
	protected function _process_players($packets) {
		$buf = new Buffer($packets[0]);
		
		// Make sure the data is formatted properly
		if($buf->lookAhead(6) != "\x00\x43\x4F\x52\x59\x00") {
			$this->debug("Data for ".__METHOD__." does not have the proper header. Header: ".$buf->lookAhead(6));
			return false;
		}

		// Now verify the end of the data is correct
		if($buf->readLast() !== "\x00") {
			$this->debug("Data for ".__METHOD__." does not have the proper ending. Ending: ".$buf->readLast());
			return false;
		}

		// Skip the header
		$buf->skip(6);

		$res = true;
		
		// Players are first
		$res = $res && $this->_parse_playerteam('players', $buf);

		// Teams are next
		$res = $res && $this->_parse_playerteam('teams', $buf);
		
		return $res;
	}
	
	protected function _parse_playerteam($type, Buffer &$buf) {
		$count = $buf->readInt8();
		if ($type === 'players')
			$this->result->addGeneral('num_players', $count);

		// Variable names
		$varnames = array();
		$team_id = 1;

		// Loop until we run out of length
		while ($buf->getLength()) {
			$field = $buf->readString();
			if ($type === 'players') {
				if (substr($field, -1) !== "_") {
					$this->debug("Arrays are not consistent");
					return false;
				}
				$field = substr($field, 0, -1);
			} else
			if ($type === 'teams') {
				if (substr($field, -2) !== "_t") {
					$this->debug("Arrays are not consistent");
					return false;
				}
				$field = substr($field, 0, -2);
			}
			$varnames[] = $field;

			if ($buf->lookAhead() === "\x00") {
				$buf->skip();
				break;
			}
		}

		// Check if there are any value entries
		if ($buf->lookAhead() == "\x00") {
			$buf->skip();
			return;
		}
		
		$ignore = false;
		if ($buf->getLength() > 4) {
			if ($type === 'players') {
				if (!in_array('player', $varnames) || !in_array('score', $varnames)) {
					$this->debug("Bad varnames array");
					$ignore = true;
				}
			} else
			if ($type === 'teams') {
				if (!in_array('team', $varnames)) {
					$this->debug("Bad varnames array");
					$ignore = true;
				}
			}
		}

		// Get the values
		while ($buf->getLength() > 4) {
			$more = array();
			foreach ($varnames as $varname) {
				$more[$varname] = $this->filterInt($buf->readString());
			}
			
			if (!$ignore) {
				if ($type === 'players') {
					$name = trim(iconv("ISO-8859-1//IGNORE", "utf-8", $more['player'])); // some chars like (c) should be converted to utf8
					$score = $more['score'];
					
					$teamid = null;
					if (isset($more['team']) && $more['team'] !== '')
						$teamid = $more['team'];
					
					unset($more['player'], $more['score'], $more['team']);
					
					$this->result->addPlayer($name, $score, $teamid, $more);
				} else
				if ($type === 'teams') {
					$name = $more['team'];
					unset($more['team']);
					
					$this->result->addTeam($team_id, $name, $more);
					$team_id++;
				}
			}

			if ($buf->lookAhead() === "\x00") {
				$buf->skip();
				break;
			}
		}
		
		return true;
	}
}