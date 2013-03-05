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
 
class Source extends \GameQ3\Protocols {

	protected $packets = array(
		'challenge' => "\xFF\xFF\xFF\xFF\x56\xFF\xFF\xFF\xFF",
		'details' => "\xFF\xFF\xFF\xFFTSource Engine Query\x00",
		'players' => "\xFF\xFF\xFF\xFF\x55%s",
		'rules' => "\xFF\xFF\xFF\xFF\x56%s",
	);

	protected $port = 27015; // Default port, used if not set when instanced
	protected $protocol = 'source';
	protected $name = 'source';
	protected $name_long = "Source Server";
	
	protected $source_engine = true;
	

	public function init() {
		$this->queue('details', 'udp', $this->packets['details']);
		if ($this->isRequested('settings') || $this->isRequested('players'))
			$this->queue('challenge', 'udp', $this->packets['challenge'], array('response_count' => 1));
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'challenge') {
			return $this->_process_challenge($requests['responses']);
		} else
		if ($qid === 'details') {
			return $this->_process_details($requests['responses']);
		} else
		if ($qid === 'rules') {
			return $this->_process_rules($requests['responses']);
		} else
		if ($qid === 'players') {
			return $this->_process_players($requests['responses']);
		}
	}
	
	
	
	protected function _process_challenge($packets) {
		$buf = new \GameQ3\Buffer($packets[0]);
		$head = $buf->read(4);
		
		if ($head !== "\xFF\xFF\xFF\xFF") {
			$this->debug("Wrong challenge");
			return false;
		}
		
		// 0x41 (?)
		$buf->read();
		
		$chal = $buf->read(4);
		
		if ($this->isRequested('settings')) $this->queue('rules', 'udp', sprintf($this->packets['rules'], $chal));
		if ($this->isRequested('players')) $this->queue('players', 'udp', sprintf($this->packets['players'], $chal));
	}
	
	
	
	protected function _preparePackets($packets) {
		$buffer = new \GameQ3\Buffer($packets[0]);

		// First we need to see if the packet is split
		// -2 = split packets
		// -1 = single packet
		$packet_type = $buffer->readInt32Signed();

		// This is one packet so just return the rest of the buffer
		if($packet_type == -1) {
			// We always return the packet as expected, with null included
			return $packets[0];
		}

		unset($buffer);


		$packs = array();

		// We have multiple packets so we need to get them and order them
		foreach($packets as $packet) {
			// Make a buffer so we can read this info
			$buffer = new \GameQ3\Buffer($packet);

			// Pull some info
			$packet_type = $buffer->readInt32Signed();
			$request_id = $buffer->readInt32Signed();

			// Check to see if this is compressed
			if($request_id & 0x80000000) {
				// Check to see if we have Bzip2 installed
				if(!function_exists('bzdecompress')) {
					$this->error('Bzip2 is not installed.  See http://www.php.net/manual/en/book.bzip2.php for more info.');
					return false;
				}

				// Get some info
				$num_packets = $buffer->readInt8();
				$cur_packet  = $buffer->readInt8();
				$packet_length = $buffer->readInt32();
				$packet_checksum = $buffer->readInt32();

				// Try to decompress
				$result = bzdecompress($buffer->getBuffer());

				// Now verify the length
				if(strlen($result) != $packet_length) {
					$this->debug("Checksum for compressed packet failed! Length expected {$packet_length}, length returned".strlen($result));
				}

				// Set the new packs
				$packs[$cur_packet] = $result;
			} else {
				// Gold source does things a bit different
				if(!$this->source_engine) {
					$packet_number = $buffer->readInt8();
				} else {
					$packet_number = $buffer->readInt16Signed();
					$split_length = $buffer->readInt16Signed();
				}

				// Now add the rest of the packet to the new array with the packet_number as the id so we can order it
				$packs[$packet_number] = $buffer->getBuffer();
			}

			unset($buffer);
		}

		unset($packets, $packet);

		// Sort the packets by packet number
		ksort($packs);

		// Now combine the packs into one and return
		return implode("", $packs);
	}
    
    
	
	
	protected function _process_players($packets) {
	
		$packet = $this->_preparePackets($packets);
		
		if (!$packet) return false;

		// Create a new buffer
		$buf = new \GameQ3\Buffer($packet);

		$header = $buf->read(5);
		if($header !== "\xFF\xFF\xFF\xFF\x44") {
			$this->debug("Data for ".__METHOD__." does not have the proper header (should be 0xFF0xFF0xFF0xFF0x44). Header: ".bin2hex($header));
			return false;
		}

		// Pull out the number of players
		$num_players = $buf->readInt8();

		$this->result->addGeneral('num_players', $num_players);

		// No players so no need to look any further
		if($num_players === 0) {
			return;
		}

		// Players list
		while ($buf->getLength()) {
			$id = $buf->readInt8();
			$name = $buf->readString();
			$score = $buf->readInt32Signed();
			$time = $buf->readFloat32();
			
			$this->result->addPlayer($name, $score, null, array('time' => $time, 'time_h' => gmdate("H:i:s", $time)));
		}
	}
	
	
	protected function _process_rules($packets) {
	
		$packet = $this->_preparePackets($packets);
		
		if (!$packet) return false;
		

		$buf = new \GameQ3\Buffer($packet);

		$header = $buf->read(5);
		if($header !== "\xFF\xFF\xFF\xFF\x45") {
			$this->debug("Data for ".__METHOD__." does not have the proper header (should be 0xFF0xFF0xFF0xFF0x45). Header: ".bin2hex($header));
			return false;
		}

		//  number of rules
		$buf->readInt16Signed();


		// We can tell it is dm (it's 90%), but lets try to be honest and report only trustful info.
		$m = false;
		while ($buf->getLength()) {
			$key = $buf->readString();
			$val = $buf->readString();
			
			$val = $this->filterInt($val);
			
			// I found only one game that reports its gamemode - tf2. l4d`s are stupid.
			switch($key) {
				case 'tf_gamemode_arena':	if ($val == 1) $m = 'arena'; break;
				case 'tf_gamemode_cp':		if ($val == 1) $m = 'cp'; break;
				case 'tf_gamemode_ctf':		if ($val == 1) $m = 'ctf'; break;
				case 'tf_gamemode_mvm':		if ($val == 1) $m = 'mvm'; break;
				case 'tf_gamemode_payload':	if ($val == 1) $m = 'payload'; break;
				case 'tf_gamemode_sd':		if ($val == 1) $m = 'sd'; break;
			}
			$this->result->addSetting($key, $val);
		}
		
		if ($m !== false)
			$this->result->addGeneral('mode', $m);
	}
	
	
	protected function _process_details($packets) {
		// A2S_INFO is not splitted
		
		// All info is here: https://developer.valvesoftware.com/wiki/Server_Queries
		
		// Goldsource sends two packets - oldstyle and newstyle. We don't care what type to use
		$data = $packets[0];

		$buf = new \GameQ3\Buffer($data);

		$head = $buf->read(4);
		
		if ($head !== "\xFF\xFF\xFF\xFF") {
			$this->debug("Wrong header");
			return false;
		}

		// Get the type
		$type = $buf->read(1);
		
		// Goldsource type
		if ($type === "\x6d") {
			$this->source_engine = false;
		} else
		if ($type === "\x49" || $type === "\x44") { // 0x44? wtf?
			$this->source_engine = true;
		} else {
			$this->debug("Data for ".__METHOD__." does not have the proper header type (should be 0x49|0x44|0x6d). Header type: 0x".bin2hex($type));
			return false;
		}
		

		// Check engine type
		if (!$this->source_engine) {
			// address
			$buf->readString();
		} else {
			// protocol
			$buf->readInt8();
		}

		$this->result->addGeneral('hostname', $buf->readString());
		
		$this->result->addGeneral('map', $buf->readString());
		
		// Sometimes those names are changeg. Aware them.
		$game_directory = $buf->readString();
		$game_description = $buf->readString();
		
		$this->result->addSetting('game_directory', $game_directory);
		$this->result->addSetting('game_description', $game_description);
		

		// Check engine type
		if ($this->source_engine) {
			$appid = $buf->readInt16();
			$this->result->addInfo('app_id', $appid);
			
			// L4D1
			if ($appid == 500) {
				if (strpos($game_description, '- Co-op')) {
					$this->result->addGeneral('mode', 'coop');
				} else
				if (strpos($game_description, '- Survival')) {
					$this->result->addGeneral('mode', 'survival');
				} else
				if (strpos($game_description, '- Versus')) {
					$this->result->addGeneral('mode', 'versus');
				}
			}
		}

		$this->result->addGeneral('num_players', $buf->readInt8());
		$this->result->addGeneral('max_players', $buf->readInt8());


		if (!$this->source_engine) {
			$this->result->addGeneral('version', $buf->readInt8());
		} else {
			// Gosh, who needs this info?!
			$this->result->addSetting('num_bots', $buf->readInt8());
		}

		//$this->result->addSettings('dedicated', $buf->read());
		//$this->result->addSettings('os', $buf->read());
		
		// dedicated
		$d = strtolower($buf->read());
		switch($d) {
			case 'l': $ds = "Listen"; break;
			case 'p': $ds = "HLTV"; break;
			default:  $ds = "Dedicated";
		}
		$this->result->addSetting('server_type', $ds);
		
		// os
		$d = strtolower($buf->read());
		switch($d) {
			case 'w': $ds = "Windows"; break;
			default:  $ds = "Linux";
		}
		$this->result->addSetting('os', $ds);

		$this->result->addGeneral('password', ($buf->readInt8() == 1));


		if (!$this->source_engine) {
			// is HL mod
			$is_mod = ($buf->readInt8() == 1);
			
			if ($is_mod) {
				$this->result->addSetting('hlmod_link', $buf->readString());
				$this->result->addSetting('hlmod_url', $buf->readString());
				
				// null byte
				$buf->read();
				
				$this->result->addSetting('hlmod_version', $buf->readInt32Signed());
				$this->result->addSetting('hlmod_size', $buf->readInt32Signed());
				
				$this->result->addSetting('hlmod_mp_only', $buf->readInt8());
				$this->result->addSetting('hlmod_own_dll', $buf->readInt8());
			}
		}

		$this->result->addGeneral('secure', ($buf->readInt8() == 1));

		if (!$this->source_engine) {
			$this->result->addSetting('num_bots', $buf->readInt8());
		} else {
			// The ship (The shit haha)
			if ($appid == 2400) {
				// mode
				$m = $buf->readInt8();
				switch ($m) {
					case 0: $ms = "Hunt"; break;
					case 1: $ms = "Elimination"; break;
					case 2: $ms = "Duel"; break;
					case 3: $ms = "Deathmatch"; break;
					case 4: $ms = "VIP Team"; break;
					case 5: $ms = "Team Elimination"; break;
					default: $ms = false;
				}
				if ($ms)
					$this->result->addGeneral('mode', $ms);
					
				$this->result->addSetting('the_ship_witnesses', $buf->readInt8());
				$this->result->addSetting('the_ship_duration', $buf->readInt8());
			}
			$this->result->addGeneral('version', $buf->readString());
		}

		unset($buf);

	}

}