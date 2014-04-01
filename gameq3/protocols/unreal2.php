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
 
use GameQ3\Buffer;

class Unreal2 extends \GameQ3\Protocols {

	protected $packets = array(
		'status' => "\x79\x00\x00\x00\x00",
		'rules' => "\x79\x00\x00\x00\x01",
		'players' => "\x79\x00\x00\x00\x02",
	);

	protected $ports_type = self::PT_UNKNOWN;
	protected $protocol = 'unreal2';
	protected $name = 'unreal2';
	protected $name_long = "Unreal 2 Engine";
	
	
	public function init() {
		$this->queue('status', 'udp', $this->packets['status']);
		if ($this->isRequested('settings')) $this->queue('rules', 'udp', $this->packets['rules']);
		if ($this->isRequested('players')) $this->queue('players', 'udp', $this->packets['players']);
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		} else
		if ($qid === 'rules') {
			return $this->_process_rules($requests['responses']);
		} else
		if ($qid === 'players') {
			return $this->_process_players($requests['responses']);
		}
	}
	
	protected function _preparePackets($packets) {
		foreach($packets as $id => $packet) {
			$packets[$id] = substr($packet, 5);
		}

		return implode('', $packets);
	}
	

	protected function _readBadPascalString(Buffer &$buf) {
		$len = $buf->readInt8();
		
		$bufpos = $buf->getPosition();
		$buf->jumpto($bufpos + $len - 1);
		$charatlen = $buf->read(1);
		$buf->jumpto($bufpos);
		
		if ($charatlen === "\x00") {
			// Valid pascal string
			return substr($buf->read($len), 0, $len - 1); // cut off null byte
		} else {
			// Invalid pascal string, assuming end of the string is 0x00
			return $buf->readString("\x00");
		}
	}
	
	/*
	
	This works for hostname, but they count length differently! sometimes they include nullchar and sometimes they dont.
	Fuck you unreal2, gonna use dumb readString method provided above.
	
	protected function _readColoredPascalString(Buffer &$buf) {
		$len = $buf->readInt8();
		$str = "";
		
		for ($i=0; $i<$len; ) {
			$char = $buf->read(1);
			$str .= $char;
			if ($char === "\x1b") { // it is a color
				$char = $buf->read(3); // color has 4 bytes
				$str .= $char;
			} else {
				$i++;
			}
		}
		
		if ($buf->read(1) !== "\x00") {
			$this->debug(__METHOD__ . ' failed');
		}
		return $str;
	}
	*/
	
	protected function _findEncoding($str) {
		// Shit happens when clients use non-latin names in their game, game mixes ucs-2 encoding with one-byte national encoding
		
		$encs = array("windows-1251");
		foreach($encs as $enc) {
			$s = iconv("utf-8", $enc, iconv("UCS-2//IGNORE", "UTF-8", $str));
			if (@iconv("utf-8", "utf-8", $s) === $s) return $s; // when string has corrupted chars, this thing will fail
		}
		
		return iconv("UCS-2//IGNORE", "UTF-8", $str);
	}
	
	protected function _readUnrealString(Buffer &$buf) {
		// Normal pascal string
		if (ord($buf->lookAhead(1)) < 129) {
			$str = $buf->readPascalString(1);
			$cstr = iconv("ISO-8859-1//IGNORE", "utf-8", $str); // some chars like (c) should be converted to utf8
			return ($cstr === false ? $str : $cstr);
		}

		// UnrealEngine2 color-coded string
		$length = ($buf->readInt8() - 128) * 2 - 2;
		$encstr = $buf->read($length);
		$buf->skip(2);

		// Remove color-code tags
		$encstr = preg_replace('~\x5e\\0\x23\\0..~s', '', $encstr);

		$str = $this->_findEncoding($encstr);

		return $str;
	}
	
	protected function _process_status($packets) {
		$buf = new Buffer($this->_preparePackets($packets));
		
		//$this->p->strReplace("\xa0", "\x20");
		// serverid
		$buf->readInt32();
		// serverip
		$buf->readPascalString(1);
		// gameport
		$this->setConnectPort($buf->readInt32());
		// queryport
		$buf->readInt32();
		
		$this->result->addGeneral('hostname', str_replace("\xa0", "\x20", $this->_readBadPascalString($buf)));
		$this->result->addGeneral('map', str_replace("\xa0", "\x20", $buf->readPascalString(1)));
		$this->result->addGeneral('mode', str_replace("\xa0", "\x20", $buf->readPascalString(1)));
		
		$num_players = $buf->readInt32();
		$this->result->addGeneral('num_players', $num_players);
		$this->result->addGeneral('max_players', $buf->readInt32());
		
		// Ut2 sometimes doesn't send players packet when there are no players on the server
		if ($num_players == 0)
			$this->unCheck('players');
		
		/*
		// ping
		$buf->readInt32();
		
		// UT2004 only
		// Check if the buffer contains enough bytes
		if ($buf->getLength() > 6) {
			// flags
			$buf->readInt32();
			// skill
			$buf->readInt16();
		}*/
	}
	
	protected function _process_players($packets) {
		$buf = new Buffer($this->_preparePackets($packets));
		
		
		while ($buf->getLength()) {
			$id = $buf->readInt32();
			if ($id === 0) {
				break;
			}

			$name = $this->_readUnrealString($buf);
			$ping = $buf->readInt32();
			$score = $buf->readInt32();
			
			$this->result->addPlayer($name, $score, null, array('ping' => $ping));
			
			$buf->skip(4);
		}
	}
	
	protected function _process_rules($packets) {
		$buf = new Buffer($this->_preparePackets($packets));
		
		
		while ($buf->getLength()) {
			$key = $buf->readPascalString(1);
			$val = $this->filterInt($buf->readPascalString(1));
			
			switch($key) {
				case 'IsVacSecured':
					$this->result->addGeneral('secure', ($val == 'true'));
					break;
				case 'ServerVersion':
					$this->result->addGeneral('version', $val);
					break;
			}
			
			$this->result->addSetting($key, $val);
		}
	}
	
}