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
 
class Bf3 extends \GameQ3\Protocols {

	protected $packets = array(
		          // GameTracker sent this:
		          // "\x00\x00\x00\x00\xa0\x02\x16\xd0\xa0\xe1\x00\x00\x02\x04\x05\xb4\x04\x02\x08\x0a\x91\x31\xaf\xd8\x00\x00\x00\x00\x01\x03\x03\x07"
		'status'  => "\x00\x00\x00\x00\x1b\x00\x00\x00\x01\x00\x00\x00\x0a\x00\x00\x00serverInfo\x00",
		'version' => "\x00\x00\x00\x00\x18\x00\x00\x00\x01\x00\x00\x00\x07\x00\x00\x00version\x00",
		'players' => "\x00\x00\x00\x00\x24\x00\x00\x00\x02\x00\x00\x00\x0b\x00\x00\x00listPlayers\x00\x03\x00\x00\x00\x61ll\x00",
	);

	/*
	Bf3 servers must be rent, so query port is always different. In case default port exists, it makes no sense to use it, because most servers use different ports.
	See this issue: https://github.com/kostya0shift/GameQ3/issues/7
	*/
	// protected $query_port = 25200;

	protected $ports_type = self::PT_SAME;
	
	protected $protocol = 'bf3';
	protected $name = 'bf3';
	protected $name_long = "Battlefield 3";
	
	
	public function init() {
		if ($this->isRequested('teams'))
			$this->result->setIgnore('teams', false);

		$this->queue('status', 'tcp', $this->packets['status']);
		$this->queue('version', 'tcp', $this->packets['version']);
		if ($this->isRequested('players')) $this->queue('players', 'tcp', $this->packets['players']);
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		} else
		if ($qid === 'version') {
			return $this->_process_version($requests['responses']);
		} else
		if ($qid === 'players') {
			return $this->_process_players($requests['responses']);
		}
	}
	
	protected function _preparePackets($packets) {
		$buf = new Buffer(implode('', $packets));
		
		$buf->skip(8); /* skip header */
		
		
		$result = array();
		$num_words = $buf->readInt32();

		for ($i = 0; $i < $num_words; $i++) {
			$len = $buf->readInt32();
			$result[] = $buf->read($len);
			$buf->read(1); /* 0x00 string ending */
		}
		
		if (!isset($result[0]) || $result[0] !== 'OK') {
			$this->debug('Packet Response was not OK! Buffer: ' . $buf->getBuffer());
			return false;
		}
		
		return $result;
	}
	
	protected function _process_version($packets) {
		$words = $this->_preparePackets($packets);
		
		if (isset($words[2]))
			$this->result->addGeneral('version', $words[2]);
	}
	
	protected function _process_players($packets) {
		$words = $this->_preparePackets($packets);
		
		// Count the number of words and figure out the highest index.
		$words_total = count($words)-1;

		// The number of player info points
		$num_tags = $words[1];

		// Pull out the tags, they start at index=3, length of num_tags
		$tags = array_slice($words, 2, $num_tags);
		
		$i_name = false;
		$i_score = false;
		$i_teamid = false;
		
		foreach($tags as $tag_i => $tag) {
			switch($tag) {
				case 'name':
					$i_name = $tag_i;
					unset($tags[$tag_i]);
					break;
				case 'teamId':
					$i_teamid = $tag_i;
					unset($tags[$tag_i]);
					break;
				case 'score':
					$i_score = $tag_i;
					unset($tags[$tag_i]);
					break;
			}
		}
		if ($i_name === false || $i_score === false || $i_teamid === false) return false;

		// Just in case this changed between calls.
		$this->result->addGeneral('num_players', $this->filterInt($words[9]));

		// Loop until we run out of positions
		for($pos=(3+$num_tags);$pos<=$words_total;$pos+=$num_tags) {
			// Pull out this player
			$player = array_slice($words, $pos, $num_tags);
			
			$m = array();
			
			foreach($tags as $tag_i => $tag) {
				$m[$tag] = $this->filterInt($player[$tag_i]);
			}
			
			$this->result->addPlayer($player[$i_name], $this->filterInt($player[$i_score]), $this->filterInt($player[$i_teamid]), $m);

		}

		// @todo: Add some team definition stuff
	}
	
	protected function _process_status($packets) {
		$words = $this->_preparePackets($packets);
		
		$this->result->addGeneral('hostname', $words[1]);
		$this->result->addGeneral('num_players', $this->filterInt($words[2]));
		$this->result->addGeneral('max_players', $this->filterInt($words[3]));
		$this->result->addGeneral('mode', $words[4]);
		$this->result->addGeneral('map', $words[5]);

		$this->result->addSetting('rounds_played', $words[6]);
		$this->result->addSetting('rounds_total', $words[7]);

		// Figure out the number of teams
		$num_teams = intval($words[8]);

		// Set the current index
		$index_current = 9;

		// Loop for the number of teams found, increment along the way
		for($id=1; $id<=$num_teams; $id++) {
			// We have tickets, but no team name. great...
			$this->result->addTeam($id, $id, array('tickets' => $this->filterInt($words[$index_current])));

			$index_current++;
		}

		// Get and set the rest of the data points.
		$this->result->addSetting('target_score', $words[$index_current]);
		// it seems $words[$index_current + 1] is always empty
		$this->result->addSetting('ranked', $words[$index_current + 2] === 'true' ? 1 : 0);
		$this->result->addGeneral('secure', $words[$index_current + 3] === 'true');
		$this->result->addGeneral('password', $words[$index_current + 4] === 'true');
		$this->result->addSetting('uptime', $words[$index_current + 5]);
		$this->result->addSetting('round_time', $words[$index_current + 6]);

		// Added in R9
		// ip_port  $words[$index_current + 7]
		$this->result->addSetting('punkbuster_version', $words[$index_current + 8]);
		$this->result->addSetting('join_queue', $words[$index_current + 9] === 'true' ? 1 : 0);
		$this->result->addSetting('region', $words[$index_current + 10]);
		$this->result->addSetting('pingsite', $words[$index_current + 11]);
		$this->result->addSetting('country', $words[$index_current + 12]);

		// Added in R29, No docs as of yet
		$this->result->addSetting('quickmatch', $words[$index_current + 13] === 'true' ? 1 : 0); // Guessed from research
	}
}