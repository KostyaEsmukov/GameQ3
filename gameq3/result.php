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
	private $ignore = array();

	
	public function __construct($ign) {
	
		$this->result['info'] = array();
		$this->result['general'] = array();
		
		foreach(array('settings', 'players', 'teams') as $t) {
			$this->setIgnore($t, isset($ign[$t]) ? $ign[$t] : false);
		}
	}
	
	public function setIgnore($t, $value) {
		if ($value) {
			unset($this->result[$t]);
			$this->ignore[$t] = true;
		} else {
			$this->result[$t] = array();
			$this->ignore[$t] = false;
		}
	}
	
	private function isIgnored($t) {
		return (isset($this->ignore[$t]) && $this->ignore[$t]);
	}
	
	public function count($key) {
		return count($this->result[$key]);
	}
	
	public function addCustom($zone, $name, $value) {
		if ($this->isIgnored($zone)) return false;
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
		if ($this->isIgnored('settings')) return false;
		$this->result['settings'] []= array($name, $value);
		return true;
	}
	
	public function addPlayer($name, $score=null, $teamid=null, $other=array(), $is_bot=null) {
		if ($this->isIgnored('players')) return false;
		$this->result['players'] []= array(
			'name' => $name,
			'score' => $score,
			'teamid' => $teamid,
			'is_bot' => $is_bot,
			'other' => $other
		);
		return true;
	}

	public function addTeam($teamid, $name, $other = array()) {
		if ($this->isIgnored('teams')) return false;
		$this->result['teams'][$teamid]= array(
			'name' => $name,
			'other' => $other
		);
		return true;
	}

	public function fetch() {
		return $this->result;
	}
}
