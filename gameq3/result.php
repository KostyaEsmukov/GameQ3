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

namespace GameQ3;

class Result {
	private $result = array();
	private $ign_settings = true;
	private $ign_players = true;
	private $ign_teams = true;
	
	public function __construct($ign) {
	
		$this->result['info'] = array();
		$this->result['general'] = array();
		
		if (!isset($ign['settings'])) {
			$this->result['settings'] = array();
			$this->ign_settings = false;
		}
		if (!isset($ign['players'])) {
			$this->result['players'] = array();
			$this->ign_players = false;
		}
		if (!isset($ign['teams'])) {
			$this->result['teams'] = array();
			$this->ign_teams = false;
		}
	}
	
	public function addCustom($zone, $name, $value) {
		$this->result[$zone][$name] = $value;
		return true;
	}

	public function addInfo($name, $value) {
		$this->result['info'][$name] = $value;
		return true;
	}
	
	public function addGeneral($name, $value) {
		$this->result['general'][$name] = $value;
		return true;
	}
	
	public function issetGeneral($name) {
		return isset($this->result['general'][$name]);
	}
	
	public function getGeneral($name) {
		return isset($this->result['general'][$name]) ? $this->result['general'][$name] : null;
	}
	
	public function addSetting($name, $value) {
		if ($this->ign_settings) return false;
		$this->result['settings'] []= array($name, $value);
		return true;
	}
	
	public function addPlayer($name, $score, $teamid = null, $other = array()) {
		if ($this->ign_players) return false;
		$this->result['players'] []= array(
			'name' => $name,
			'score' => $score,
			'teamid' => $teamid,
			'other' => $other
		);
		return true;
	}
	
	public function appendPlayer($name, $key, $value) {
		if ($this->ign_teams) return false;
		$append_index = false;
		foreach($this->result['players'] as $index => &$player) {
			if ($player['name'] === $name) {
				$append_index = $index;
				break;
			}
		}
		if ($append_index === false) {
			$this->addPlayer($name, null, null, array());
		}
		
		if ($key === 'name' || $key === 'score' || $key === 'teamid')
			$this->result['players'][$append_index][$key] = $value;
		else
			$this->result['players'][$append_index]['other'][$key] = $value;
			
		return true;
	}

	public function addTeam($teamid, $name, $other = array()) {
		if ($this->ign_teams) return false;
		$this->result['teams'] [$teamid]= array(
			'name' => $name,
			'other' => $other
		);
		return true;
	}
	
	public function appendTeam($teamid, $key, $value) {
		if ($this->ign_teams) return false;
		if (!isset($this->result['teams'][$teamid])) {
			$this->addTeam($teamid, null, array());
		}
		
		if ($key === 'name')
			$this->result['teams'][$teamid][$key] = $value;
		else
			$this->result['teams'][$teamid]['other'][$key] = $value;
			
		return true;
	}

	public function fetch() {
		return $this->result;
	}
}
