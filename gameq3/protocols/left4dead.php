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
 
class Left4dead extends \GameQ3\Protocols\Source {
	protected $name = "left4dead";
	protected $name_long = "Left 4 Dead";

	protected function _detectMode($game_description, $appid) {
		if ($appid == 500) {
			if (strpos($game_description, '- Co-op')) {
				$this->result->addGeneral('mode', 'coop');
			} else
			if (strpos($game_description, '- Survival')) {
				$this->result->addGeneral('mode', 'survival');
			} else
			if (strpos($game_description, '- Versus')) {
				$this->result->addGeneral('mode', 'versus');
			}
		}
	}
}
