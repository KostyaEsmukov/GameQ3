<?php
/**
 * This file is part of GameQ.
 *
 * GameQ is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * GameQ is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */


namespace GameQ3\protocols;
 
class Bf2 extends \GameQ3\Protocols\Gamespy3 {
	protected $name = "bf2";
	protected $name_long = "Battlefield 2";

	protected $port = 29900;
	
	protected $packets = array(
		'all' => "\xFE\xFD\x00\x10\x20\x30\x40\xFF\xFF\xFF\x01",
	);
	
	protected $challenge = false;
	
	
	protected function _put_var($key, $val) {
		switch($key) {
			case 'hostname':
				$this->result->addCommon('hostname', $val);
				break;
			case 'mapname':
				$this->result->addCommon('map', $val);
				break;
			case 'gamever':
				$this->result->addCommon('version', $val);
				break;
			case 'gamemode':
				$this->result->addCommon('mode', $val);
				break;
			case 'numplayers':
				$this->result->addCommon('num_players', intval($val));
				break;
			case 'maxplayers':
				$this->result->addCommon('max_players', intval($val));
				break;
			case 'bf2_reservedslots':
				$this->result->addCommon('private_players', intval($val));
				$this->result->addSetting($key, $val);
				break;
			case 'password':
				$this->result->addCommon('password', $val == '1');
				break;
			case 'bf2_anticheat':
				$this->result->addCommon('secure', $val == '1');
				$this->result->addSetting($key, $val);
				break;
			default:
				$this->result->addSetting($key, $val);
		}
	}
}
