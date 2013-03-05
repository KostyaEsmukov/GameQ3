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
 
class Mohwf extends \GameQ3\Protocols\Bf3 {
	protected $name = 'mohwf';
	protected $name_long = "Medal of Honor Warfighter";

	
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

		// The next 3 are empty in MOHWF, kept incase they start to work some day
		// ip_port  $words[$index_current + 7]
		$this->result->addSetting('punkbuster_version', $words[$index_current + 8]);
		$this->result->addSetting('join_queue', $words[$index_current + 9] === 'true' ? 1 : 0);
		
		$this->result->addSetting('region', $words[$index_current + 10]);
		$this->result->addSetting('pingsite', $words[$index_current + 11]);
		$this->result->addSetting('country', $words[$index_current + 12]);


	}
}