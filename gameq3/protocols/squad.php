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

class Squad extends \GameQ3\Protocols {

	protected $packets = array(
		'status'  => "\xff\xff\xff\xff\x54\x53\x6f\x75\x72\x63\x65\x20\x45\x6e\x67\x69\x6e\x65\x20\x51\x75\x65\x72\x79\x00",
		'challenge' => "\xff\xff\xff\xff\x56\x00\x00\x00\x00",
		'settings'   => "\xff\xff\xff\xff\x56%s",
		'players'   => "\xff\xff\xff\xff\x55%s",
	);

	protected $ports_type = self::PT_UNKNOWN;
	protected $protocol = 'squad';
	protected $name = 'squad';
	protected $name_long = "Squad";
	
	
	public function init() {
            $this->queue('status', 'udp', $this->packets['status']);
            if ($this->isRequested('settings') || $this->isRequested('players')) {
                $this->queue('challenge', 'udp', $this->packets['challenge']);
            }
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'status') {
			return $this->_process_status($requests['responses']);
		} else
		if ($qid === 'challenge') {
			return $this->_process_challenge($requests['responses']);
		} else
		if ($qid === 'settings') {
			return $this->_process_settings($requests['responses']);
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

	protected function _process_status($packets) {
            $buf = new Buffer($this->_preparePackets($packets));
            $buf->jumpto(1);
            
            $this->result->addGeneral('hostname', $buf->readString());
            $mapstring = $buf->readString();
            $this->result->addGeneral('map', $mapstring);
            
            $buf->readString();$buf->readString();$buf->readInt8();$buf->readInt8();
            $this->result->addGeneral('num_players', $buf->readInt8());
            $this->result->addGeneral('max_players', $buf->readInt8());
            $this->result->addGeneral('bot_players', $buf->readInt8());
            /*$server_type = $buf->readInt8();
            
            $environment = "";
            switch ($buf->readChar()){
                case 'l':
                    $environment = "Linux";
                    break;
                    
                case 'w':
                    $environment = "Windows";
                    break;
                
                case 'm':
                case 'o':
                    $environment = "Mac OS";
                    break;
            }*/
	}
	
	protected function _process_challenge($packets) {
            $buf = new Buffer($this->_preparePackets($packets));
            
            $challenge = $buf->getData();
            
            if ($this->isRequested('settings')) $this->queue('settings', 'udp', sprintf($this->packets['settings'], $challenge));
            if ($this->isRequested('players')) $this->queue('players', 'udp', sprintf($this->packets['players'], $challenge));
	}
	
	protected function _process_settings($packets) {
            $buf = new Buffer($this->_preparePackets($packets));
            
            $buf->jumpto(2);

            while ($buf->getLength()>0){
                $buf->lookAhead(1);
                $key = $buf->readString();
                $value = $buf->readString();
                $this->result->addSetting($key, $value);
                
                switch ($key){
                    case "GameMode_s":
                        $this->result->addGeneral('mode', $value);
                        break;
                    
                    case "GameVersion_s":
                        $this->result->addGeneral("version", $value);
                        break;
                    
                    case "NUMPRIVCONN":
                        $this->result->addGeneral("private_players", intval($value));
                        break;
                    
                    case "Password_b":
                        $this->result->addGeneral("password",  ($value=="true") ? 1 : 0);
                        break;
                }
                $buf->lookAhead(1);
            }
            
	}
        
	protected function _process_players($packets) {
            $buf = new Buffer($this->_preparePackets($packets));
            
            $count = $buf->readInt8();

            while ($buf->getLength()>0){
                $id = $buf->readInt8(); //= 0 for every player ??
                
                $name  = $buf->readString();
                
                $buf->skip(8);
                
                $this->result->addPlayer($name, 0, null, null, 0);
            }
            
	}

}