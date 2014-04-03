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
/*
	This is really strange protocol.
	You shouldn't rely on it's results, because UT3 can simply send corrupted and truncated arrays of data (related to players and teams).
	Check $result['info']['full'] for results reliability (described in ./gamespy3.php)
	Also if you turn on debugging then you will see some information about corruptions in response.
	
	This is not a bug of query software, it is UT3's implementation of Gamespy3.
	Here are some screenshots from serverbrowser. They are really strange:
	http://img443.imageshack.us/img443/4982/ut320130310190116282.png
	http://img46.imageshack.us/img46/6742/ut320130310191529232.png
*/

class Ut3 extends \GameQ3\Protocols\Gamespy3 {
	protected $name = "ut3";
	protected $name_long = "Unreal Tournament 3";

	protected $query_port = 6500;
	protected $connect_port = 7777;
	protected $ports_type = self::PT_DIFFERENT_NONCOMPUTABLE_VARIABLE;
	
	protected function _parse_arrays_break(&$buf) {
		/*
			We should break in case:
			* We reach new field (next char is any except \x00-\x02)
			* We reach new section (next char is \x00, second char is \x01-\x02)
			* We reach end of packet (buf length is 1)
		*/
		
		if ($buf->getLength() <= 1) return true;
		
		$c_1 = $buf->lookAhead(2);
		$c_2 = $c_1{1};
		$c_1 = $c_1{0};
		
		// New section
		if ($c_1 === "\x00" && ($c_2 === "\x01" || $c_2 === "\x02")) return true;
		
		// New field
		if ($c_1 !== "\x00") return true;
		
		return false;
	}
	
	protected function _parse_settings() {
		$s_cnt = count($this->settings);
		$i = 0;
		$ut3_section = false;
		while ($i < $s_cnt) {
			$key = $this->settings[$i];
			if (!$ut3_section) {
				// Those keys seem to be always set in the beggining of ut3 variables section
				if ($key === 's32779' || $key === 's0')
					$ut3_section = true;
			}
			
			$val = isset($this->settings[$i+1]) ? $this->settings[$i+1] : "";
			
			// Check if next var is ut3 key. Sometimes it's value is skipped, we should process that.
			/* Example (look at key p268435968):
				1c 00 70 32 36 38 34 33  35 37 30 36 00 31 32 00    ..p26843 5706.12.
				70 32 36 38 34 33 35 39  36 38 00 70 32 36 38 34    p2684359 68.p2684
				33 35 39 36 39 00 30 00  00 01 70 6c 61 79 65 72    35969.0. ..player

			*/
			if ($ut3_section && preg_match('/^(s[0-9]+|p[0-9]+)$/', $val)) {
				$i += 1;
				$val = "";
			} else {
				$i += 2;
			}
			$this->_put_var($key, $this->filterInt($val));
			
		}
		unset($this->settings);
		return true;
	}
	
	
	protected function _put_var($key, $val) {
		$normalize = array(
			's6' => 'pure_server',
			's10' => 'force_respawn',
			'p268435704' => 'frag_limit',
			'p268435705' => 'time_limit',
		);
		
		switch($key) {
			// General
			
			// Hostname is really buggy. The most trustful value is p1073741827, if it is not empty.
			case 'hostname':
				$this->result->addGeneral('hostname', $val);
				break;
			// OwningPlayerName is usually set to hostname, but some references say that it is really OwningPlayerName.
			case 'OwningPlayerName': 
				if ($this->result->getGeneral('hostname') !== $val)
					$this->result->addSetting('OwningPlayerName', $val);
				break;
			case 'p1073741827':
				if ($val !== "")
					$this->result->addGeneral('hostname', $val);
				break;
				
			case 'hostport':
				$this->setConnectPort($val);
				$this->result->addSetting($key, $val);
				break;
			case 'p1073741825':
				$this->result->addGeneral('map', $val);
				break;
			case 'EngineVersion':
				$this->result->addGeneral('version', $val);
				$this->result->addSetting($key, $val);
				break;
			case 's32779':
				switch($val) {
					case 1: $m = "dm"; break;
					case 2: $m = "war"; break;
					case 3: $m = "vctf"; break;
					case 4: $m = "tdm"; break;
					case 5: $m = "duel"; break;
					default: $m = false;  break; // don't override p1073741826
				}
				if (!$m)
					$this->result->addSetting($key, $val);
				else
					$this->result->addGeneral('mode', $m);
				break;
			case 'p1073741826':
				switch($val) {
					case 'UTGameContent.UTVehicleCTFGame_Content': $m = "vctf"; break;
					case 'UTGameContent.UTCTFGame_Content': $m = "ctf"; break;
					case 'UTGame.UTTeamGame': $m = "tdm"; break;
					case 'UTGameContent.UTOnslaughtGame_Content': $m = "war"; break;
					default: $m = false;  break; // don't override s32779
				}
				if (!$m)
					$this->result->addSetting($key, $val);
				else
					$this->result->addGeneral('mode', $m);
				break;
			case 'numplayers':
				$this->result->addGeneral('num_players', $val);
				break;
			case 'maxplayers':
				$this->result->addGeneral('max_players', $val);
				break;
			case 'p268435703':
				$this->result->addGeneral('bot_players', $val);
				break;
			case 's7':
				$this->result->addGeneral('password', $val == 1);
				break;
				
			// Settings that we need to parse
			case 'p268435717':
				$m = array();
				if ($val & 1)     $m []= "BigHead";
				if ($val & 2)     $m []= "FriendlyFire";
				if ($val & 4)     $m []= "Handicap";
				if ($val & 8)     $m []= "Instagib";
				if ($val & 16)    $m []= "LowGrav";
				if ($val & 64)    $m []= "NoPowerups";
				if ($val & 128)   $m []= "NoTranslocator";
				if ($val & 256)   $m []= "Slomo";
				if ($val & 1024)  $m []= "SpeedFreak";
				if ($val & 2048)  $m []= "SuperBerserk";
				if ($val & 8192)  $m []= "WeaponReplacement";
				if ($val & 16384) $m []= "WeaponsRespawn";
				
				foreach($m as $mut)
					$this->result->addSetting('stock_mutator', $mut);
					
				//$this->result->addSetting($key, $mut);
				break;
			case 'p1073741828':
				$m = explode("\x1C", $val);
				foreach($m as $mut)
					if ($mut !== "") $this->result->addSetting('custom_mutator', $mut);
				break;
			case 'p1073741829':
				// Same as p1073741828 list of mutators, but these values are mutator names I think,
				// in p1073741828 we have mutator descriptions.
				break;
			case 's0':
				switch($val) {
					case 1: $m = "Novice"; break;
					case 2: $m = "Average"; break;
					case 3: $m = "Experienced"; break;
					case 4: $m = "Skilled"; break;
					case 5: $m = "Adept"; break;
					case 6: $m = "Masterful"; break;
					case 7: $m = "Inhuman"; break;
					case 8: $m = "Godlike"; break;
					default: $m = false;  break;
				}
				if (!$m)
					$this->result->addSetting($key, $val);
				else
					$this->result->addSetting('bot_skill', $m);
				break;
			case 's8':
				switch($val) {
					case 0: $m = "false"; break;
					case 1: $m = "true"; break;
					case 2: $m = "1:1"; break;
					case 3: $m = "3:2"; break;
					case 4: $m = "2:1"; break;
					default: $m = false;  break;
				}
				if (!$m)
					$this->result->addSetting($key, $val);
				else
					$this->result->addSetting('vs_bots', $m);
				break;
			case 'mapname':
				// skip comma-separated list of the same variables
				break;
				
			// All other settings
			default:
				$this->result->addSetting((isset($normalize[$key]) ? $normalize[$key] : $key), $val);
		}
	}
}
