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
 
class Bf2 extends \GameQ3\Protocols\Gamespy3 {
	protected $name = "bf2";
	protected $name_long = "Battlefield 2";

	protected $query_port = 29900;
	protected $connect_port = 16567;
	protected $ports_type = self::PT_DIFFERENT_NONCOMPUTABLE_VARIABLE;
	
	protected $packets = array(
		'all' => "\xFE\xFD\x00\x10\x20\x30\x40\xFF\xFF\xFF\x01",
	);
	
	protected $challenge = false;
	
	
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
			case 'bf2_reservedslots':
				$this->result->addGeneral('private_players', $val);
				$this->result->addSetting($key, $val);
				break;
			case 'password':
				$this->result->addGeneral('password', $val == 1);
				break;
			case 'bf2_anticheat':
				$this->result->addGeneral('secure', $val == 1);
				$this->result->addSetting($key, $val);
				break;
			case 'hostport':
				$this->setConnectPort($val);
				$this->result->addSetting($key, $val);
				break;
			default:
				$this->result->addSetting($key, $val);
		}
	}
}
