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
 
class Cscz extends \GameQ3\Protocols\Source {
	protected $name = "cscz";
	protected $name_long = "Counter-Strike: Condition Zero";


	protected function _process_rules($packets) {
		// CS 1.6 sends A2S_INFO in new source format, but rules in old format. Durty workaround for this.

		$os = $this->source_engine;
		$this->source_engine = false;

		$packet = $this->_preparePackets($packets);

		$this->source_engine = $os;
		

		if (!$packet) return false;
		
		$this->_parse_rules($packet);

	}
}
