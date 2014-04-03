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
 
class Bfv extends \GameQ3\Protocols\Gamespy2 {
	protected $name = "bfv";
	protected $name_long = "Battlefield Vietnam";

	protected $query_port = 23000;
	protected $connect_port = 14567;
	protected $ports_type = self::PT_DIFFERENT_NONCOMPUTABLE_VARIABLE;
	
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
			case 'reservedslots':
				$this->result->addGeneral('private_players', $val);
				$this->result->addSetting($key, $val);
				break;
			case 'password':
				$this->result->addGeneral('password', $val == 1);
				break;
			case 'sv_punkbuster':
				$this->result->addGeneral('secure', $val == 1);
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