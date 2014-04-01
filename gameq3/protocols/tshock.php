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

class Tshock extends \GameQ3\Protocols {
	protected $protocol = 'tshock';
	protected $name = 'terraria';
	protected $name_long = "Terraria";
	
	protected $query_port = 7878;
	protected $connect_port = 7777;
	protected $ports_type = self::PT_DIFFERENT_NONCOMPUTABLE_VARIABLE;
	
	protected $url = "/v2/server/status?players=true&rules=true";
	
	public function init() {
		if ($this->isRequested('teams'))
			$this->result->setIgnore('teams', false);

		$this->queue('status', 'http', $this->url);
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		}
	}
	
	protected function _process_status($packets) {
		$data = json_decode($packets[0], true);

		if (!isset($data['status'])) return false;
		if ($data['status'] != 200) {
			$this->debug("Tshock error (" . $data['status'] . ")" . (isset($data['error']) ? " " . $data['error'] : ""));
			return false;
		}
		unset($data['status']);
		
		if (!isset($data['port'])) return false;
		$this->setConnectPort($data['port']);
		unset($data['port']);
		
		$keys_general = array(
			"name" => "hostname",
			"playercount" => "num_players",
			"maxplayers" => "max_players",
			"world" => "map",
		);

		foreach($keys_general as $key => $normalized) {
			if (!isset($data[$key])) return false;
			$this->result->addGeneral($normalized, $data[$key]);
			unset($data[$key]);
		}
		
		if (!isset($data["players"]) || !isset($data["rules"]) || !is_array($data["players"]) || !is_array($data["rules"])) return false;
		
		/*
			As I figured out terraria's teams are partys. They haven't got any name or any other information.
		*/
		$teams = array();
		foreach($data["players"] as $player) {
			if (!isset($player["nickname"]) || !isset($player["team"])) {
				$this->debug("Bad player skipped");
				continue;
			}
			
			$name = $player["nickname"];
			$teamid = $player["team"];
			$teams[$teamid] = true;
			// if ($player["state"] == 10) // player is playing
			
			unset($player["nickname"], $player["team"]);
			
			$this->result->addPlayer($name, null, $teamid, $player);
		}
		
		foreach($teams as $teamid => $val) {
			$this->result->addTeam($teamid, '' . $teamid);
		}
		
		foreach($data["rules"] as $key => $value) {
			// Make value printable
			if (is_bool($value)) $value = ($value ? "true" : "false");
			if (is_null($value)) $value = "";
			if (!is_scalar($value)) {
				$this->debug("Setting value is not scalar. Skipped. Key: " . $key . ". Value: " . var_export($value, true));
				continue;
			}
			$this->result->addSetting($key, $value);
		}
		
		unset($data["players"], $data["rules"]);
		
		foreach($data as $key => $value) {
			// Make value printable
			if (is_bool($value)) $value = ($value ? "true" : "false");
			if (is_null($value)) $value = "";
			if (!is_scalar($value)) {
				continue;
			}
			$this->result->addSetting($key, $value);
		}
		
	}
}