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
 
class Minecraft extends \GameQ3\Protocols {

	protected $packets = array(
		//'status' => "\xFE",
		'status' => "\xFE\x01",
	);

	protected $port = 25565; // Default port, used if not set when instanced
	protected $protocol = 'minecraft';
	protected $name = 'minecraft';
	protected $name_long = "Minecraft";
	
	
	public function init() {
		$this->queue('status', 'tcp', $this->packets['status'], array('response_count' => 1));
	}
	
	public function processRequests($qid, $requests) {
		$this->addPing($requests['ping']);
		$this->addRetry($requests['retry_cnt']);
		if ($qid === 'status') {
			$this->_process_status($requests['responses']);
		}
	}
	
	private function _process_status($packets) {
		// http://www.wiki.vg/Server_List_Ping
		// https://gist.github.com/barneygale/1209061
		
		$buf = new \GameQ3\Buffer($packets[0]);
		
		if ($buf->read(1) !== "\xFF") {
			$this->debug("Wrong header");
			return;
		}
		
		
		// packet length
		$buf->skip(2);
		
		$cbuf = iconv("UTF-16BE//IGNORE", "UTF-8", $buf->getBuffer());
		
		// New version
		if (substr($cbuf, 0, 2) === "\xC2\xA7") {
			$info = explode("\x00", substr($cbuf, 2));
			
			// $info[0] = 1
			$this->result->addSetting('protocol_version', $info[1]);
			
			$this->result->addCommon('version', $info[2]);
			$this->result->addCommon('hostname', $info[3]);
			
			$this->result->addCommon('num_players', min($info[4], $info[5]));
			$this->result->addCommon('max_players', $info[5]);
		
		} else {
			$info = explode("\xC2\xA7", $cbuf);
			
			// Actually it is MotD, but they usually use this as server name
			$this->result->addCommon('hostname', $info[0]);
			$this->result->addCommon('num_players', min($info[1], $info[2]));
			$this->result->addCommon('max_players', $info[2]);
		}
	}
	
}