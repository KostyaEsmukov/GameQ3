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

/**
 * Query kerbal multi player mod of the Kerbal Space Program game using HTTP
 */
 
 // https://github.com/TehGimp/KerbalMultiPlayer/blob/c4890648b9919938bb171ede6e83562c4aa47537/KLFServer/Server.cs#L1019

class kerbalmultiplayer extends \GameQ3\Protocols {
	protected $protocol = 'kerbalmultiplayer';
	protected $name = 'kerbalmultiplayer';
	protected $name_long = "Kerbal Space Program - Multiplayer";
	
	protected $url = "/";

	protected $query_port = 8080;
	protected $connect_port = 2076;
	protected $ports_type = self::PT_DIFFERENT_NONCOMPUTABLE_VARIABLE;

	protected function _put_var($key, $val) {
		switch($key) {
			case 'Version':
				$this->result->addGeneral('version', $val);
				break;

			case 'Port':
				$this->result->addSetting($key, $val);
				$this->setConnectPort($val);
				break;

			case 'Num Players':
				$pa = explode('/', $val, 2);
				if (!isset($pa[1])) {
					$this->debug("Bad Num Players row value: " . $val);
					break;
				}

				$n = intval(trim($pa[0]));
				$m = intval(trim($pa[1]));

				$this->result->addGeneral('num_players', $n);
				$this->result->addGeneral('max_players', $m);
				break;

			case 'Players':
				$pa = explode(',', $val);

				foreach ($pa as $pname) {
					$pname = trim($pname);

					$this->result->addPlayer($pname);
				}
				break;

			case 'Information':
				$this->result->addGeneral('hostname', $val);
				break;

			// the rest is just settings
			default:
				$this->result->addSetting($key, $val);
		}
	}
	
	public function init() {
		$this->queue('status', 'http', $this->url);
	}

	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		}
	}
	
	protected function _process_status($packets) {
		$data = $packets[0];
		unset($packets);

		$data = trim($data);

		$data_a = explode("\n", $data);

		foreach($data_a as $row) {
			$kv = explode(':', $row, 2);

			if (!isset($kv[1])) {
				$this->debug("Skipped row without colon: " . $row);
				continue;
			}

			$k = trim($kv[0]);

			if (strpos($k, ">") !== false || strpos($k, "<") !== false) {
				$this->debug("Key seems to contain HTML tag - skipped");
				continue;
			}
			$this->_put_var($k, trim($kv[1]));
		}
	}
	
}