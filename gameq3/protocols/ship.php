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
 */


namespace GameQ3\protocols;
 
use GameQ3\Buffer;

class Ship extends \GameQ3\Protocols\Source {
	protected $name = "ship";
	protected $name_long = "The Ship";

	protected function _parseDetailsExtension(Buffer &$buf, $appid) {
		if ($appid == 2400) {
			// mode
			$m = $buf->readInt8();
			switch ($m) {
				case 0: $ms = "Hunt"; break;
				case 1: $ms = "Elimination"; break;
				case 2: $ms = "Duel"; break;
				case 3: $ms = "Deathmatch"; break;
				case 4: $ms = "VIP Team"; break;
				case 5: $ms = "Team Elimination"; break;
				default: $ms = false;
			}
			if ($ms)
				$this->result->addGeneral('mode', $ms);
				
			$this->result->addSetting('the_ship_witnesses', $buf->readInt8());
			$this->result->addSetting('the_ship_duration', $buf->readInt8());
		}
	}
}
