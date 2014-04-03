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

// http://wiki.vg/Server_List_Ping#1.6

class Minecraft16 extends \GameQ3\Protocols\Minecraft {

	protected $protocol_version = "\x4a";

	protected $packets = null;
	
	protected $protocol = 'minecraft16';
	protected $name = 'minecraft';
	protected $name_long = "Minecraft 1.6";
	
	protected function _toShort($i) {
		return pack('n*', $i);
	}

	protected function _toInt($i) {
		return pack('N*', $i);
	}

	protected function _networkString($str) {
		return iconv('ISO-8859-1//IGNORE', 'UCS-2LE', $str);
	}

	protected function _buildStatus($hostname, $port) {
		$packet = "\xFE\x01\xFA";
		$packet .= $this->_toShort(11);
		$packet .= $this->_networkString("MC|PingHost");
		$packet .= $this->_toShort(7 + 2 * strlen($hostname));
		$packet .= $this->protocol_version;
		$packet .= $this->_toShort(strlen($hostname));
		$packet .= $this->_networkString($hostname);
		$packet .= $this->_toInt($port);

		return $packet;
	}

	public function init() {
		$this->queue('status', 'tcp', $this->_buildStatus($this->query_addr, $this->query_port), array('response_count' => 1, 'close' => true));
	}
}