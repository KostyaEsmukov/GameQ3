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
 
class Gamespy3 extends \GameQ3\Protocols {

	protected $packets = array(
		'challenge' => "\xFE\xFD\x09\x10\x20\x30\x40",
		'all' => "\xFE\xFD\x00\x10\x20\x30\x40%s\xFF\xFF\xFF\x01",
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
		
		// Get packet index, remove header
		foreach ($packets as $index => $packet) {
			// Make new buffer
			$buf = new \GameQ3\Buffer($packet);

			// Skip the header
			$buf->skip(14);

			// Get the current packet and make a new index in the array
			$return[$buf->readInt16()] = $buf->getBuffer();
		}

		unset($buf, $packets);

		// Sort packets, reset index
		ksort($return);

		// Grab just the values
		$return = array_values($return);

		// Compare last var of current packet with first var of next packet
		// On a partial match, remove last var from current packet,
		// variable header from next packet
		for ($i = 0, $x = count($return); $i < $x - 1; $i++) {
			// First packet
			$fst = substr($return[$i], 0, -1);

			// Second packet
			$snd = $return[$i+1];

			// Get last variable from first packet
			$fstvar = substr($fst, strrpos($fst, "\x00")+1);

			// Get first variable from last packet
			$snd = substr($snd, strpos($snd, "\x00")+2);
			$sndvar = substr($snd, 0, strpos($snd, "\x00"));

			// Check if fstvar is a substring of sndvar
			// If so, remove it from the first string
			if (strpos($sndvar, $fstvar) !== false) {
				$return[$i] = preg_replace("#(\\x00[^\\x00]+\\x00)$#", "\x00", $return[$i]);
			}
		}

		// Now let's loop the return and remove any dupe prefixes
		for($x = 1; $x < count($return); $x++) {
			$buf = new \GameQ3\Buffer($return[$x]);

			$prefix = $buf->readString();

			// Check to see if the return before has the same prefix present
			if(strstr($return[($x-1)], $prefix)) {
				// Update the return by removing the prefix plus 2 chars
				$return[$x] = substr(str_replace($prefix, '', $return[$x]), 2);
			}

			unset($buf);
		}
		
		return implode("", $return);
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
			case 'gamemode':
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
		$buf = new \GameQ3\Buffer($this->_preparePackets($packets));

		while($buf->getLength()) {
			$key = $buf->readString();

			if (strlen($key) == 0)
				break;
				
			$val = $buf->readString();			
			$val = $this->filterInt($val);
				
			$this->_put_var($key, $val);
		}
		

		/*
		 * Explode the data into groups. First is player, next is team (item_t) 
		 * 
		 * Each group should be as follows:
		 * 
		 * [0] => item_
		 * [1] => information for item_
		 * ...
		 */
		$data = explode("\x00\x00", $buf->getBuffer());

		// By default item_group is blank, this will be set for each loop thru the data
		$item_group = '';

		// By default the item_type is blank, this will be set on each loop
		$item_type = '';
		
		$teams = array();
		$players = array();

		// Loop through all of the $data for information and pull it out into the result
		for($x=0; $x < count($data)-1; $x++) {
			// Pull out the item
			$item = $data[$x];
			
			// If this is an empty item, move on
			if($item === '' || $item === "\x00")
				continue;

			
			// Check to see if $item has a _ at the end, this is player info
			if(substr($item, -1) == '_') {
				$item_group = 'players';
				$item_type = substr($item, 0, -1);
				// strip non-printable chars
				$item_type = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $item_type);

				$i_pos = 1;
			} else
			// Check to see if $item has a _t at the end, this is team info
			if(substr($item, -2) == '_t') {
				$item_group = 'teams';
				$item_type = substr($item, 0, -2);
				// strip non-printable chars
				$item_type = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $item_type);

				$i_pos = 1;
			}
			// We can assume it is data belonging to a previously defined item
			else {
				$buf_temp = new \GameQ3\Buffer($item);
				
				
				// Get the values
				while ($buf_temp->getLength()) {
					$val = $buf_temp->readString();
					// No value so break the loop, end of string
					if ($val === '')
						break;
						
					$val = trim($val);
					$val = $this->filterInt($val);
						
					if ($item_group === 'players') {
						if (!isset($players[$i_pos]))
							$players[$i_pos] = array();
						$players[$i_pos][$item_type] = $val;
					} else
					if ($item_group === 'teams') {
						if (!isset($teams[$i_pos]))
							$teams[$i_pos] = array();
						$teams[$i_pos][$item_type] = $val;
					}
					
					$i_pos++;

				}
				
				// Unset out buffer
				unset($buf_temp);
			}
		}

		foreach($teams as $team_id => $team_ar) {
			if (!isset($team_ar['team'])) {
				$this->debug("Bad teams array");
				break; // teams are not so important
			}
			
			$team_name = $team_ar['team'];
			unset($team_ar['team']);
			$this->result->addTeam($team_id, $team_name, $team_ar);
		}
		
		foreach($players as $player_ar) {
			if (!isset($player_ar['player'])) {
				$this->debug("Bad players array");
				return false;
			}
			
			$name = $player_ar['player'];
			$score = isset($player_ar['score']) ? $player_ar['score'] : null;
			$teamid = isset($player_ar['team']) ? $player_ar['team'] : null;
			unset($player_ar['player'], $player_ar['score'], $player_ar['team']);
			$this->result->addPlayer($name, $score, $teamid, $player_ar);
		}
	
	}
}