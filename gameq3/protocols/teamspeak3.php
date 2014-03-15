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

// TS3 lets us send 30 queries in a minute and then bans as for 600 seconds.
// Great reference: http://media.teamspeak.com/ts3_literature/TeamSpeak%203%20Server%20Query%20Manual.pdf
 
class Teamspeak3 extends \GameQ3\Protocols {

	protected $packets = array(
		'login' => "login client_login_name=%s client_login_password=%s\x0A",
		
		'usesid' => "use sid=%s\x0A",
		'useport' => "use port=%d\x0A",
		
		'serverinfo' => "serverinfo\x0A",
		'channellist' => "channellist -topic -flags -voice -limits\x0A",
		'clientlist' => "clientlist -uid -away -voice -groups\x0A",
		//'servergroup' => "servergrouplist\x0A",
		//'channelgroup' => "channelgrouplist\x0A",
		
		//'quit' => "quit\x0A",
	);

	protected $connect_port = 9987;
	protected $query_port = 10011;
	protected $ports_type = self::PT_DIFFERENT_NONCOMPUTABLE_FIXED;
	
	protected $protocol = 'teamspeak3';
	protected $name = 'teamspeak3';
	protected $name_long = "Teamspeak 3";
	
	protected $string_find = array(
		"\\\\",
		"\\/",
		"\\s",
		"\\p",
		"\\;",
		"\\a",
		"\\b",
		"\\f",
		"\\n",
		"\\r",
		"\\t",
		"\\v",
	);
	
	protected $string_replace = array(
		"\\",
		"/",
		" ",
		"|",
		";",
		"\a",
		"\b",
		"\f",
		"\n",
		"\r",
		"\t",
		"\v",
	);
	
	protected function construct() {
		// Make packet that we will send every time
		
		$formed_packet = "";
		$reply_format = array();
		
		if (isset($this->server_info['login_name']) && isset($this->server_info['login_password'])) {
			$formed_packet .= sprintf($this->packets['login'], $this->server_info['login_name'], $this->server_info['login_password']);
			$reply_format []= 'cmd';
		}
		
			
		if (isset($this->server_info['sid'])) {
			$formed_packet .= sprintf($this->packets['usesid'], $this->server_info['sid']);
		} else {
			if (!is_int($this->connect_port))
				throw new UserException("Both connect_port and sid are missed in TS3");
			$formed_packet .= sprintf($this->packets['useport'], $this->connect_port);
		}
		$reply_format []= 'cmd';
		
		$formed_packet .= $this->packets['serverinfo'];
		$reply_format []= 'serverinfo';
		if ($this->isRequested('players')) {
			$formed_packet .= $this->packets['clientlist'];
			$reply_format []= 'clientlist';
			//$formed_packet .= $this->packets['servergroup'];
			//$reply_format []= 'servergroup';
		}
		if ($this->isRequested('channels')) {
			$formed_packet .= $this->packets['channellist'];
			$reply_format []= 'channellist';
			//$formed_packet .= $this->packets['channelgroup'];
			//$reply_format []= 'channelgroup';
		}
		
		//$formed_packet .= $this->packets['quit'];
		//$reply_format []= 'cmd';

		$this->packet = $formed_packet;
		$this->reply_format = $reply_format;
	}
	
	public function init() {
		$this->queue('all', 'tcp', $this->packet, array('close' => true));
	}
	
	protected function processRequests($qid, $requests) {
		if ($qid === 'all') {
			return $this->_process_r($requests['responses']);
		}
	}
	
	protected function _process_r($packets) {
		$packet_data = implode("", $packets);
		$buf = new \GameQ3\Buffer($packet_data);
		unset($packets);
		
		$result = array();
		
		// remove header if present
		if ($buf->lookAhead(3) === 'TS3') {
			// TS3
			$buf->readString("\n");
			// Welcome to the serverquery blah-blah-blah
			$buf->readString("\n");
		}
		
		foreach($this->reply_format as $reply) {
			$data = trim($buf->readString("\n"));
			
			if ($reply !== "cmd" && substr($data, 0, 6) !== "error ") {
				$result[$reply] = array();
				
				$data = explode ('|', $data);
				
				
				foreach ($data as $part) {
					$variables = explode (' ', $part);

					$info = array();

					foreach ($variables as $variable) {
						$ar = explode('=', $variable, 2);

						$info[$ar[0]] = (isset($ar[1]) ? $this->_unescape($ar[1]) : '');
					}


					$result[$reply][] = $info;
				}
				
				$data = trim($buf->readString("\n"));
			}

			$res = $this->_verify_response($data);
			// Response is incorrect (this occures when some packets are not received due to timeout)
			if ($res !== true) {
				$this->debug("TS3 Error occured." . (is_string($res) ? $res : "\nBuffer:\n" . $packet_data ) );
				return false;
			}
		}
		
		foreach($result as $type => $reply) {
			if ($type === "serverinfo") {
				foreach($reply[0] as $key => $val) {
					$val = $this->filterInt($val);
						
					switch($key) {
						case 'virtualserver_name':
							$this->result->addGeneral('hostname', $val);
							break;
						case 'virtualserver_flag_password':
							$this->result->addGeneral('password', ($val == 1));
							break;
						case 'virtualserver_clientsonline':
							$this->result->addGeneral('num_players', $val);
							break;
						case 'virtualserver_maxclients':
							$this->result->addGeneral('max_players', $val);
							break;
						case 'virtualserver_version':
							$this->result->addGeneral('version', $val);
							break;
						case 'virtualserver_port':
							$this->setConnectPort($val);
							break;
					}
					$this->result->addSetting($key, $val);
				}
			} else
			if ($type === "clientlist") {
				foreach($reply as $player) {
					$name = $player['client_nickname'];
					unset($player['client_nickname']);
					
					foreach($player as $key => &$val) {
						$val = $this->filterInt($val);
					}
					
					// cid - channel id. But most probably we will not use that value as we don't use teams, so we don't pass to to teamid
					$this->result->addPlayer($name, 0, null, $player);
				}
			} else
			if ($type === "channellist") {
				foreach($reply as $channel) {
					foreach($channel as $key => &$val) {
						$val = $this->filterInt($val);
					}
					$cid = $channel['cid'];
					unset($channel['cid']);
					$this->result->addCustom('channels', $cid, $channel);
				}
			}
		}

	}
	

	protected function _unescape($str) {
		return str_replace($this->string_find, $this->string_replace, $str);
	}
	
	protected function _verify_response($response) {
		// Check the response
		if($response === 'error id=0 msg=ok') return true;
		
		if (substr($response, 0, 6) === "error ") {
			$errstr = "";
			
			$vars = explode(" ", substr($response, 6));
			foreach($vars as $pair) {
				$ar = explode('=', $pair, 2);

				$key = $ar[0];
				$val = (isset($ar[1]) ? $this->_unescape($ar[1]) : '');

				$errstr .= " " . ucfirst($key) .": ".$val . ".";
			}

			
			return $errstr;
		}
		
		return false;
	}
}