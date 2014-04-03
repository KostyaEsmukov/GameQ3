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

class Sockets {
	/**
	 * @var Log
	 */
	private $log = null;
	
	/*
		arrays from socket_select preserve keys as of PHP 5.3.0: https://bugs.php.net/bug.php?id=44197
		arrays from stream_select preserve keys as of PHP 5.4.0: https://bugs.php.net/bug.php?id=53427
	*/
	
	// Options
	private $connect_timeout = 1; // seconds
	private $send_once_udp = 5;
	private $send_once_stream = 5;
	private $usleep_udp = 100; // ns
	private $usleep_stream = 100; // ns
	private $read_timeout = 600;
	private $read_got_timeout = 100;
	private $read_retry_timeout = 200;
	private $loop_timeout = 2; // ms
	private $socket_buffer = 8192;
	private $send_retry = 1;
	private $curl_select_timeout = 1.0; // s
	private $curl_connect_timeout = 1200; // 800
	private $curl_total_timeout = 1500; // 1500
	private $curl_options = array();
	
	// Work arrays
	private $cache_addr = array();
	
	private $sockets_udp = array();
	private $sockets_udp_data = array();
	private $sockets_udp_send = array();
	private $sockets_udp_sid = array();
	private $sockets_udp_socks = array();
	
	private $sockets_stream = array();
	private $sockets_stream_id = array();
	private $sockets_stream_data = array();
	private $sockets_stream_close = array();
	
	private $curl_mh = null;
	private $curl_running = false;
	private $curl_extra = array();
	private $curl_id = array();
	
	private $responses = array();
	private $send = array();
	private $recreated_udp = array(); // sctn => count
	private $recreated_stream = array(); // sid => count
	
	const SELECT_MAXTIMEOUT = 1; // ms
	
	const STREAM_PING_MAX_DIFF_MS = 20; // ms
	const STREAM_PING_MAX_DIFF_MULTIPLY = 2;
	
	const CURL_DEFAULT_USERAGENT = "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:19.0) Gecko/20100101 Firefox/19.0";
	const CURL_DEFAULT_MAXREDIRS = 3;
	
	public function __construct($log) {
		$this->log = $log;
	}
	
	public function getsetOption($set, $key, $value) {
		$error = false;
		
		switch($key) {
			case 'connect_timeout':
			case 'send_once_udp':
			case 'send_once_stream':
			case 'usleep_udp':
			case 'usleep_stream':
			case 'read_timeout':
			case 'read_got_timeout':
			case 'read_retry_timeout':
			case 'loop_timeout':
			case 'socket_buffer':
			case 'send_retry':
			
			case 'curl_connect_timeout':
			case 'curl_total_timeout':
				if ($set) {
					if (is_int($value))
						$this->$key = $value;
					else
						$error = 'int';
				} else {
					return $this->$key;
				}
				break;
			
			case 'curl_select_timeout':
				if ($set) {
					if (is_int($value))
						$this->$key = ($value/1000);
					else
						$error = 'int';
				} else {
					return $this->$key;
				}
				break;

			case 'curl_options':
				if ($set) {
					if (is_array($value))
						foreach($value as $opt => $val) {
							$this->$key[$opt] = $val;
						}
					else
						$error = 'array';
				} else {
					return $this->$key;
				}
				break;
			
			default:
				if ($set)
					throw new UserException("Unknown option key " . var_export($key, true));
				else
					return null;
		}
		
		if ($error !== false)
			throw new UserException("Value for setOption must be " . $error . ". Got value: " . var_export($value, true));

		return false;
	}

	// Resolve address
	private function _resolveAddr($addr) {
		if (isset($this->cache_addr[$addr])) {
			return $this->cache_addr[$addr];
		}
		
		// Check for IPv6 format
		if ($addr{0} == '[' && $addr{strlen($addr)-1} == ']') {
			$t = substr($addr, 1, -1);
			if (!filter_var($t, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				throw new SocketsException("Wrong address (IPv6 filter failed) '" . $addr . "'");
			}
			$this->cache_addr[$addr] = array(\AF_INET6, $t);
			return $this->cache_addr[$addr];
		}
		
		if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$this->cache_addr[$addr] = array(\AF_INET, $addr);
			return $this->cache_addr[$addr];
		}
		
		// RFC1035
		//if (!preg_match('/^[a-zA-Z0-9.-]{1,255}$/', $addr)) return false;
		
		// Try faster gethostbyname
		$gh = gethostbyname($addr);
		if ($gh !== $addr) {
			// In case php guys add IPv6 support for this function in future versions.
			$r = false;
			if (filter_var($gh, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				$r = array(\AF_INET, $gh);
			} else
			if (filter_var($gh, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
				$r = array(\AF_INET6, $gh);
			}
			if ($r !== false) {
				$this->cache_addr[$addr] = $r;
				return $r;
			}
		}
		
		// We failed, trying another way
		// We need this to pass timeout value to stream_socket_client/
		$errno = 0;
		$errstr = "";
		// Create UDP (connectionless) socket to our server on some random port.
		// Socket will not send any packet to the server, it will just resolve IP address
		$sock = @stream_socket_client("udp://" . $addr . ":30000", $errno, $errstr, 1);
		// If resolve failed socket returns false.
		if (!$sock)
			throw new SocketsException("Unable to resolv hostname '" . $addr . "'");
		// Extract addr:port
		$remote_addr = stream_socket_get_name($sock, true);
		// Free socket resource
		fclose($sock);
		// Cut off port
		if (!is_string($remote_addr) || strlen($remote_addr) <= 6 || substr($remote_addr, -6) != ':30000')
			throw new SocketsException("Unable to resolv hostname '" . $addr . "'");
		$remote_addr = substr($remote_addr, 0, -6);
		
		// Find out IP version
		$r = false;
		if (filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
			$r = array(\AF_INET, $remote_addr);
		} else
		if (filter_var($remote_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
			$r = array(\AF_INET6, $remote_addr);
		}
		if ($r !== false) {
			$this->cache_addr[$addr] = $r;
			return $r;
		}
		
		throw new SocketsException("Unable to resolv hostname '" . $addr . "'");
	}
	
	
	private function _createSocketUDP($sctn, $throw = false) {
		// This should never happen
		/*if (!isset($this->sockets_udp_data[$sctn]) {
			throw new SocketsException("Cannot create UDP socket");
		}*/
		
		if (isset($this->sockets_udp[$sctn])) {
			$this->recreated_udp[$sctn] = true;
			$this->_closeSocketUDP($sctn);
		}
		
		$sock = socket_create($this->sockets_udp_data[$sctn], SOCK_DGRAM, SOL_UDP);
		if (!is_resource($sock)) {
			$errno = socket_last_error();
			$errstr = socket_strerror($errno);
			if ($throw) {
				throw new SocketsException("Cannot create socket for protocol 'udp'. Error[" . $errno. "]: " . $errstr);
			} else {
				$this->log->debug("Cannot create socket for protocol 'udp'. Error[" . $errno. "]: " . $errstr);
				return false;
			}
		}
		socket_set_nonblock($sock);
		//socket_bind($sock, ($this->sockets_udp_data[$sctn] === \AF_INET ? '0.0.0.0' : '::0'));
		$this->sockets_udp[$sctn] = $sock;
		return true;
	}

	private function _createSocketStream($sid, $throw = false) {
		// This should never happen
		if (!isset($this->sockets_stream_data[$sid])) {
			if ($throw) {
				throw new SocketsException("Cannot create stream socket");
			} else {
				$this->log->debug("Cannot create stream socket");
				return false;
			}
		}
		
		if (isset($this->sockets_stream[$sid])) {
			$this->recreated_stream[$sid] = true;
			$this->_closeSocketStream($sid);
		}
		
		$proto = $this->sockets_stream_data[$sid]['pr'];
		$data = $this->sockets_stream_data[$sid]['d'];
		$port = $this->sockets_stream_data[$sid]['p'];

		if ($proto == 'tcp') {
			$remote_addr = $proto . '://' . $data . ':' . $port;
		} else
		if ($proto == 'unix' || $proto = 'udg') {
			$remote_addr = $proto . '://' . $data;
		} else {
			$this->log->debug("Unsupported stream protocol");
			return false;
		}

		$errno = null;
		$errstr = null;
		// Create the socket
		$socket = @stream_socket_client($remote_addr, $errno, $errstr, $this->connect_timeout, STREAM_CLIENT_CONNECT);

		if (!is_resource($socket)) {
			if ($throw) {
				throw new SocketsException("Cannot create socket for address '". $remote_addr ."' Error[" . var_export($errno, true). "]: " . $errstr);
			} else {
				$this->log->debug("Cannot create socket for address '". $remote_addr ."' Error[" . var_export($errno, true). "]: " . $errstr);
				return false;
			}
		}
		
		stream_set_blocking($socket, false);
		stream_set_timeout($socket, $this->connect_timeout);
		
		$this->sockets_stream[$sid] = $socket;
		$this->sockets_stream_data[$sid]['c'] = true;
		
		return true;
	}

	private function _closeSocketUDP($sctn) {
		if (!isset($this->sockets_udp[$sctn]))
			return;

		if (is_resource($this->sockets_udp[$sctn])) {
			@socket_close($this->sockets_udp[$sctn]);
		}
		unset($this->sockets_udp[$sctn]);
	}

	private function _closeSocketStream($sid) {
		if (!isset($this->sockets_stream[$sid]))
			return;

		if (is_resource($this->sockets_stream[$sid])) {
			@fclose($this->sockets_stream[$sid]);
		}
		unset($this->sockets_stream[$sid]);
	}
	
	private function _createCurlHandle($sid, $url, $curl_opts, $return_headers) {
		if (!function_exists("curl_init") || ($ch = curl_init()) == false)
			throw new SocketsException("Cannot init curl");
			
		// http://stackoverflow.com/questions/9062798/php-curl-timeout-is-not-working
		if (!defined('CURLOPT_CONNECTTIMEOUT_MS')) define('CURLOPT_CONNECTTIMEOUT_MS', 156);

		curl_setopt_array($ch, array(
			CURLOPT_CONNECTTIMEOUT_MS => $this->curl_connect_timeout,
			CURLOPT_TIMEOUT_MS => $this->curl_total_timeout,
			CURLOPT_MAXREDIRS => self::CURL_DEFAULT_MAXREDIRS,
			CURLOPT_USERAGENT => self::CURL_DEFAULT_USERAGENT,
			// We are not going to post any sensitive information
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
		));
		curl_setopt_array($ch, $this->curl_options);
		curl_setopt_array($ch, $curl_opts);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HEADER => false,
			//CURLOPT_MUTE => true,
			CURLOPT_NOSIGNAL => true,
			CURLOPT_URL => $url,
		));
		
		if ($return_headers)
			curl_setopt($ch, CURLOPT_HEADER, true);
			
		return $ch;
	}
	
	
	private function _writeSocketUDP($sid, &$packets) {
		if (!isset($this->sockets_udp_send[$sid])) return false;
		$sctn = $this->sockets_udp_send[$sid]['s'];
	
		// socket failed.
		if (!isset($this->sockets_udp[$sctn]) || !is_resource($this->sockets_udp[$sctn])) {
			// first fail
			if (isset($this->sockets_udp[$sctn]) && !is_resource($this->sockets_udp[$sctn])) {
				// failed to recreate socket
				if(!$this->_createSocketUDP($sctn)) {
					return false;
				}
			} else {
				return false;
			}
		}
		
		foreach($packets as &$packet) {
			socket_sendto($this->sockets_udp[$sctn], $packet, strlen($packet), 0 , $this->sockets_udp_send[$sid]['a'], $this->sockets_udp_send[$sid]['p']);
			usleep($this->usleep_udp);
		}
		
		// true means that socket is alive, not that packet had been successfully sent.
		// actually we don't have to check send for success
		return true;
	}
	
	private function _writeSocketStream($sid, &$packets, $retry = false) {
		// socket failed.
		if (!isset($this->sockets_stream[$sid]) || !is_resource($this->sockets_stream[$sid])) {
			// first fail
			if (isset($this->sockets_stream[$sid]) && !is_resource($this->sockets_stream[$sid])) {
				// failed to recreate socket
				if (!$this->_createSocketStream($sid)) {
					return false;
				}
			} else {
				return false;
			}
		}
		
		$this->sockets_stream_data[$sid]['c'] = false;
		
		foreach($packets as &$packet) {
			$er = fwrite($this->sockets_stream[$sid], $packet);
			$er = ($er === false || $er <= 0 );
			if ($er) break;
			usleep($this->usleep_stream);
		}
		
		if (isset($er) && $er) {
			// Second fail. Very strange situation. Do not enter recursion, just return false for this packet.
			if ($retry) return false;
			if ($this->_createSocketStream($sid)) {
				$this->_writeSocketStream($sid, $packets, true);
			} else {
				return false;
			}
		}

		return true;
	}
	
	
	// Push request queue into class instance and prepare sockets
	public function allocateSocket($server_id, $queue_id, $queue_opts) {
		if (empty($queue_opts['transport']))
			throw new SocketsException("Missing 'transport' key in allocateSocket() function");
		if (empty($queue_opts['packets']))
			throw new SocketsException("Missing 'packets' key in allocateSocket() function");
			
		$proto = $queue_opts['transport'];
		$packs = $queue_opts['packets'];
		$domain_str = "";
		$domain = false;

		if ($proto === 'udp' || $proto === 'tcp' || $proto === 'http') {
			if (empty($queue_opts['addr']) || !is_string($queue_opts['addr']))
				throw new SocketsException("Missing valid 'addr' key in allocateSocket() function");
			if (empty($queue_opts['port']) || !is_int($queue_opts['port']))
				throw new SocketsException("Missing valid 'port' key in allocateSocket() function");
			
			if ($proto === 'http') {
				if (!is_string($packs))
					throw new SocketsException("Packets key for HTTP protocol must be a string in allocateSocket() function");
				$data = $queue_opts['addr'];
			} else {
				// some domains may have multiple ip addreses. to avoid different addreses, resolved from one domain, we going to use resolved IPs in sid
				list($domain, $data) = $this->_resolveAddr($queue_opts['addr']);
				$domain_str = ($domain == \AF_INET ? '4' : '6');
			}
			$port = $queue_opts['port'];
		} else
		if ($proto === 'unix' || $proto === 'udg') {
			if (empty($queue_opts['path']) || !is_int($queue_opts['path']))
				throw new SocketsException("Missing valid 'path' key in allocateSocket() function");
				
			$domain = false;
			$domain_str = 'u';
			$data = $queue_opts['path'];
			$port = false;
		} else {
			throw new SocketsException("Unknown protocol '" . $proto . "'");
		}
		
			
		if ($proto === 'http') {
			if (!$this->curl_running || is_null($this->curl_mh)) {
				$this->curl_mh = curl_multi_init();
				$this->curl_running = true;
			}
			
			// There is no need to identify curl handles by input data, thus we can generate random sid.
			$sid = 'Curl' . $data . $port . uniqid();
		
			$scheme = (isset($queue_opts['scheme']) ? $queue_opts['scheme'] : 'http');
			$url = $scheme . '://' . $data . ':' . $port . $packs;

			$curl_opts = (isset($queue_opts['curl_opts']) ? $queue_opts['curl_opts'] : array());
			$return_headers = (isset($queue_opts['return_headers']) ? $queue_opts['return_headers'] : false);
			
			/*
			// Multi handle
			private $curl_mh = null;
			
			// Is curl still running?
			private $curl_running = false;
			
			// Curl settings array
			private $curl_extra = array();
			sid => array('h' => return_headers)
			
			// For identification of sid by handle
			private $curl_id = array();
			resource_id => sid
			*/

			$this->curl_extra[$sid] = array();
			$ch = $this->_createCurlHandle($sid, $url, $curl_opts, $return_headers);
			$this->curl_extra[$sid]['h'] = $return_headers;
			$this->curl_id[(int)$ch] = $sid;

			curl_multi_add_handle($this->curl_mh, $ch);

			// No need in $responses for curl
			
			return $sid;
		}
			
		if (!is_array($packs))
			$packs = array($packs);
			
		$sid = $server_id.':'.$queue_id.':'.$proto.':'.$domain_str.':'.$data.':'.($port !== false ? $port : "");
		if ($proto === 'udp') {
			// domain_str added to prevent mix of ipv4 and ipv6 (the chanse of this is about zero) sids in one sidnt
			$sidnt = $domain_str.':'.$data.':'.$port;
			/*
			// to find socket resource by its number. used below and for select.
			private $sockets_udp = array();
			number_of_socket => socket_resource
			
			// for sendto
			private $sockets_udp_send = array();
			sid => array( 's' => number_of_socket, 'a' => data, 'p' => port )
			
			// for recvfrom
			private $sockets_udp_sid = array();
			number_of_socket => array( domain:data:port => sid, ... )
			
			// for finding out first available socket. used below.
			private $sockets_udp_socks = array();
			domain:data:port => array( sid => number_of_socket, ...)
			
			// for socket recreation
			private $sockets_udp_data = array();
			number_of_socket => domain
			*/
			
			// Find number of socket to use. Separate IPv4 from IPv6.
			$sctn = $domain_str . ':';
			if (!isset($this->sockets_udp_socks[$sidnt])) {
				$this->sockets_udp_socks[$sidnt] = array();
				$sctn .= '0';
			} else {
				if (isset($this->sockets_udp_socks[$sidnt][$sid])) {
					$sctn = $this->sockets_udp_socks[$sidnt][$sid];
				} else {
					$sctn .= ''.count($this->sockets_udp_socks[$sidnt]);
				}
			}
			
			if (!isset($this->sockets_udp[$sctn])) {
				// Create socket
				$this->sockets_udp_sid[$sctn] = array();
				$this->sockets_udp_data[$sctn] = $domain;
				$this->_createSocketUDP($sctn, true);
			}
			$this->sockets_udp_sid[$sctn][$sidnt] = $sid;
			$this->sockets_udp_socks[$sidnt][$sid] = $sctn;
			$this->sockets_udp_send[$sid] = array(
				's' => $sctn, // we don't store socket resource here because socket can be recreated
				'a' => $data,
				'p' => $port
			);

			// We use single send array for all sockets because we want to send packets in order they come
			$this->send[$sid] = array(
				'u' => true, // is UDP
				'r' => (!isset($queue_opts['no_retry']) || $queue_opts['no_retry'] !== true), // retry send if no reply
				'p' => $packs, // packets to send
			);
			
		} else {
			/*
			// we are going to do all interesting work with this array
			private $sockets_stream = array();
			sid => socket_resource
			
			// for socket recreation and cleaning
			private $sockets_stream_data = array();
			sid => array('pr' => proto, 'd' => data, 'p' => port, 'c' => just_created)

			// which sockets should be closed on the end of request
			private $sockets_stream_close = array();
			sid => true
			*/
			if (!isset($this->sockets_stream[$sid])) {
				if ($domain === \AF_INET6)
					$data = '['.$data.']';
					
				$this->sockets_stream_data[$sid] = array(
					'pr' => $proto,
					'd' => $data,
					'p' => $port,
					//'c' => true, // just created
				);
				$this->_createSocketStream($sid, true);
			}

			if (isset($queue_opts['close']) && $queue_opts['close']) {
				$this->sockets_stream_close[$sid] = true;
			}
			
			$this->send[$sid] = array(
				'u' => false,
				'r' => (isset($queue_opts['no_retry']) && $queue_opts['no_retry'] !== true),
				'p' => $packs,
			);
		}
	
		$this->responses[$sid] = array(
			'sr' => false,          // Socket recreated
			'p' => array(),         // Responses
			'pg' => null,           // Time between first sent packet and first got packet (ping)
			't' => 0,               // Extra tries
			'rc' => 0,              // Current responses count
			'mrc' =>                // Maximum responses count
				( (isset($queue_opts['response_count']) && is_int($queue_opts['response_count']))
				? $queue_opts['response_count'] : false),
			//'st' => 0,              // Microtime of last send
			'rt' => 0,              // Last receive time
			'i' => null,            // Information about request. Used for HTTP.
		);
		return $sid;
	}
	
	private function _procCleanSockets() {
		$sockets_stream = $this->sockets_stream;
		while (true) {
			if (empty($this->sockets_stream)) break;
			$write = null;
			$except = $sockets_stream;
			$read = $sockets_stream;
			if (!@stream_select($read, $write, $except, 0)) break;
			
			foreach($read as $sid => &$sock) {
				// Skip just created sockets as there can be hello/welcome packets
				if ($this->sockets_stream_data[$sid]['c'] == true) {
					unset($sockets_stream[$sid]); // don't select this socket
					continue;
				}
				
				$buf = stream_socket_recvfrom($sock, $this->socket_buffer);
				if ($buf === false || strlen($buf) == 0) {
					$this->log->debug("Recreating stream socket. " . $sid);
					$this->_createSocketStream($sid);
				}
			}
			foreach($except as $sid => &$sock) {
				$this->log->debug("Recreating stream socket. " . $sid);
				$this->_createSocketStream($sid);
			}
		}

		while (true) {
			if (empty($this->sockets_udp)) break;
			$write = null;
			$except = $this->sockets_udp;
			$read = $this->sockets_udp;
			if (!@socket_select($read, $write, $except, 0)) break;
			foreach($read as $sctn => &$sock) {
				$buf = "";
				$name = "";
				$port = 0;
				socket_recvfrom($sock, $buf, $this->socket_buffer, 0 , $name, $port );
				if ($buf === false || strlen($buf) == 0) {
					$this->log->debug("Recreating udp socket. " . $sctn);
					$this->_createSocketUDP($sctn);
				}
			}
			foreach($except as $sctn => &$sock) {
				$this->log->debug("Recreating udp socket. " . $sctn);
				$this->_createSocketUDP($sctn);
			}
		}
	}
	
	private function _procWrite(&$responses, &$read_udp, &$read_udp_sctn, &$read_udp_sid, &$read_stream) {
		// Send packets
		$s_udp_cnt = 0;
		$s_stream_cnt = 0;
		
		$long_to = true;
		
		foreach($this->send as $sid => $data) {
			$udp_limit = ($s_udp_cnt >= $this->send_once_udp);
			$stream_limit = ($s_stream_cnt >= $this->send_once_stream);
			
			if ($udp_limit && $stream_limit) {
				$long_to = false;
				break;
			}
			
			$is_udp = $data['u'];
			
			if (
				($is_udp && $udp_limit)
				|| (!$is_udp && $stream_limit)
			) {
				$long_to = false;
				continue;
			}

			$now = microtime(true);
			
			// packet already sent.
			if ($responses[$sid]['t'] !== 0) {
				// send just once?
				$timeout = ($responses[$sid]['t'] == 1 ? $this->read_timeout : $this->read_retry_timeout);
				
				// packet didn't timed out yet
				if (($now - $responses[$sid]['st'])*1000 < $timeout)
					continue;
					
				// packet timed out
				if ($responses[$sid]['t'] > $this->send_retry) {
					$this->log->debug("Packet timed out " . $sid);
					unset($this->send[$sid]);
					continue;
				}
			}
			
			if ($data['r'] !== true)
				unset($this->send[$sid]);
			
			if ($is_udp) {
				$r = $this->_writeSocketUDP($sid, $data['p']);
			} else {
				$r = $this->_writeSocketStream($sid, $data['p']);
			}

			if (!$r) {
				// don't read failed socket
				if ($is_udp && isset($this->sockets_udp_send[$sid])) {
					$sctn = $this->sockets_udp_send[$sid]['s'];

					if (!isset($this->sockets_udp[$sctn]) || !is_resource($this->sockets_udp[$sctn])) {
						unset($read_udp[$sctn]);
					}
				}

				continue;
			}
			
			$responses[$sid]['t']++;
			$responses[$sid]['st'] = $now;
			
			if ($is_udp) {
				$s_udp_cnt++;
				$sctn = $this->sockets_udp_send[$sid]['s'];
				// timeout identification
				// sid => socket_number
				$read_udp_sctn[$sid] = $sctn;
				// for select
				// socket_number => socket_resource
				$read_udp[$sctn] = $this->sockets_udp[$sctn];
				// for timeout identification
				// socket_number => array(sid => true, ...)
				// (are we waiting for a packet?)
				$read_udp_sid[$sctn][$sid] = true;
			} else {
				$s_stream_cnt++;
				// sid => socket_resource
				$read_stream[$sid] = $this->sockets_stream[$sid];
			}
		}
		
		return $long_to;
	}
	
	private function _procRead($start_time, $timeout, &$responses, &$read_udp, &$read_udp_sctn, &$read_udp_sid, &$read_stream) {
		foreach(array(true, false) as $is_udp) {
			$tio = (($start_time - microtime(true))*1000 + $timeout) * 1000;

			if ($is_udp && ($tio <= 0)) { // first iteration. Breaks parent loop when timed out
				return false;
			}
			
			$tio = max(0, min($tio, self::SELECT_MAXTIMEOUT*1000));
			$write = null; // we don't need to write
			if ($is_udp) {
				if (empty($read_udp)) continue;
				$except = $read_udp; // check for errors
				$read = $read_udp; // incoming packets
				$sr = @socket_select($read, $write, $except, 0, $tio);
			} else {
				if (empty($read_stream)) continue;
				$except = $read_stream; // check for errors
				$read = $read_stream; // incoming packets
				$sr = @stream_select($read, $write, $except, 0, $tio);
			}

			// as there can be much packets on a single socket, we are going to read them until we are done
			while (true) {
				if ($sr === false) {
					// nothing bad
					break;
				} else
				if ($sr == 0) {
					// got no packets in latest select => we have nothing to do here
					break;
				} else
				if ($sr > 0) {

					$recv_time = microtime(true);
					foreach($read as $k => &$sock) {
						if ($is_udp) {
							$sctn = $k;
							
							$buf = "";
							$name = "";
							$port = 0;
							$res = @socket_recvfrom($sock, $buf, $this->socket_buffer, 0, $name, $port);

							$exception = ($res === false || $res <= 0 || strlen($buf) == 0);
						} else {
							$sid = $k;
							
							$buf = @stream_socket_recvfrom($sock, $this->socket_buffer);

							// In winsock and unix sockets recv() returns empty string when
							// tcp connection is closed. I hope in this case too...
							$exception = ($buf === false || strlen($buf) == 0);
						}
						
						if ($exception) {
							// Don't read broken socket.
							if ($is_udp) {
	
								
								$this->log->debug("Socket exception. " . $sctn);

								// Socket failed -> all packets, associated with it
								// in current send loop won't come.
								

								foreach($read_udp_sid[$sctn] as $sid => $unused) {
									unset($read_udp_sctn[$sid]);
								}

								unset($read_udp_sid[$sctn]);
								unset($read_udp[$sctn]);
								
								// As it is new socket, there should't be anything to read
								$this->_createSocketUDP($sctn);
							} else {
								
								$this->log->debug("Socket exception. " . $sid);
								
								// Socket will be recreated on next write (if any)
								unset($this->sockets_stream[$sid]);
								unset($read_stream[$sid]);
							}
							continue;
						}

						if ($is_udp) {
							$sidnt = ($this->sockets_udp_data[$sctn] == \AF_INET ? '4' : '6').':'.$name.':'.$port;
							// packet from unknown sender
							if (!isset($this->sockets_udp_sid[$sctn][$sidnt])) {
								$this->log->debug("Packet from unknown sender " . $name . ":" . $port);
								continue;
							}
								
							$sid = $this->sockets_udp_sid[$sctn][$sidnt];
							
							// if sid is already timed out
							if (!isset($read_udp_sctn[$sid])) {
								$this->log->debug("Received timed out sid " . $sid);
								//continue;
							}
						}
							
						unset($this->send[$sid]);
						
						if (!isset($responses[$sid])) {
							$this->log->debug("Responses array does not exists for sid " . $sid);
							continue;
						}
						
						$responses[$sid]['rc']++;
						$responses[$sid]['p'] []= $buf;
						
						if ($responses[$sid]['pg'] === null) {
							$responses[$sid]['pg'] = ($recv_time - $responses[$sid]['st']);
						} else {
							// Check and correct ping, because hello/welcome packet might come before first write, in this case ping will be too little
							if (!$is_udp) {
								$prev_ping = ($responses[$sid]['rt'] - $responses[$sid]['st']);
								$cur_ping = ($recv_time - $responses[$sid]['st']);
								//$this->log->debug("Cur ping: " . ($cur_ping*1000));
								
								// If ping has changed too much, correct it
								if ( ($cur_ping-$prev_ping > self::STREAM_PING_MAX_DIFF_MS) || ($cur_ping/$prev_ping > self::STREAM_PING_MAX_DIFF_MULTIPLY)) {
									$responses[$sid]['pg'] = $cur_ping;
								}
							}
						}
						
						$responses[$sid]['rt'] = $recv_time;
							
						if (($responses[$sid]['mrc'] > 0) && ($responses[$sid]['rc'] >= $responses[$sid]['mrc'])) {
							if ($is_udp) {
								unset($read_udp_sid[$sctn][$sid]);
								if (empty($read_udp_sid[$sctn])) {
									unset($read_udp[$sctn]);
								}
								unset($read_udp_sctn[$sid]);
							} else {
								unset($read_stream[$sid]);
							}
						}
					}
					
					foreach($except as $k => &$sock) {
						$this->log->debug("Socket exception. " . $k);
						if ($is_udp) {
							$sctn = $k;
							if (!$this->_createSocketUDP($sctn)) {
								$this->log->debug("Recreating udp socket. " . $sctn);
								// clean sids with that socket number
								foreach($read_udp_sid[$sctn] as $sid => $unused) {
									unset($read_udp_sid[$sctn][$sid]);
									unset($read_udp_sctn[$sid]);
								}
								unset($read_udp[$sctn]);
							}
						} else {
							$sid = $k;

							if (!$this->_createSocketStream($sid)) {
								$this->log->debug("Recreating stream socket. " . $sid);
								unset($read_stream[$sid]);
							}
						}
					}
				}
				
				$write = null; // we don't need to write
				if ($is_udp) {
					$except = $read_udp; // check for errors
					$read = $read_udp; // incoming packets
					$sr = @socket_select($read, $write, $except, 0);
				} else {
					$except = $read_stream; // check for errors
					$read = $read_stream; // incoming packets
					$sr = @stream_select($read, $write, $except, 0);
				}
			}
		}
		return true;
	}
	
	private function _procMarkTimedOut(&$responses, &$read_udp, &$read_udp_sctn, &$read_udp_sid, &$read_stream) {
		$now = microtime(true);
		foreach(array(true, false) as $is_udp) {
			if ($is_udp)
				$read_en =& $read_udp_sctn;
			else
				$read_en =& $read_stream;
				
			foreach($read_en as $sid => $val) {
				if ($responses[$sid]['rc'] === 0) {
					$timeout = ($responses[$sid]['t'] == 1 ? $this->read_timeout : $this->read_retry_timeout);
					$to = (($now - $responses[$sid]['st'])*1000 >= $timeout);
				} else {
					// some data received, count timeout another way
					$to = (($now - $responses[$sid]['rt'])*1000 >= $this->read_got_timeout);
				}
					
				// packet timed out
				if ($to) {
					if ($is_udp) {
						$sctn = $val;
						unset($read_udp_sid[$sctn][$sid]);
						if (empty($read_udp_sid[$sctn])) {
							unset($read_udp[$sctn]);
						}
						unset($read_udp_sctn[$sid]);
					} else {
						unset($read_stream[$sid]);
					}
				}

			}
		}
	}
	
	
	private function _fillCurlResponse($sid, &$ch, $info) {
		if ($info['msg'] !== CURLMSG_DONE) return false;
		
		// $info['result'] and curl_errno() should be the same
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		if ($info['result'] !== CURLE_OK || $errno !== CURLE_OK || $error != "") {
			$this->log->debug("HTTP request ended with an error (" . $info['result'] . ") " . $error);
			return false;
		}
		
		$body = curl_multi_getcontent($ch);
		$curl_getinfo = curl_getinfo($ch);
		
		curl_multi_remove_handle($this->curl_mh, $ch);
		curl_close($ch);
		
		$res = true;
		
		if (is_bool($body)) {
			$res = $body;
			$body = null;
		}
		
		if (!$res) {
			$this->log->debug("Curl returned false but it is not an error"); // this should never happen
			return false;
		}
		
		$result = array();
		$result['i'] = array();
		
		if ($this->curl_extra[$sid]['h']) {
			if (is_string($body)) {
				$headers = trim(substr($body, 0, $curl_getinfo["header_size"]));
				$body = substr($body, $curl_getinfo["header_size"]);
				
				// Pop headers from the latest request
				$headers = explode("\r\n\r\n", $headers);
				$headers = end($headers);
				
				if ($this->curl_extra[$sid]['h'] === 'raw' || $this->curl_extra[$sid]['h'] === 'both') {
					$result['i']['headers_raw'] = $headers;
				}
				if ($this->curl_extra[$sid]['h'] !== 'raw') {
					$headers_array = array();
					$headers = explode("\n", $headers);
					
					foreach($headers as $header) {
						if (empty($header) || strpos($header, ":") === false) continue;
						list($key, $val) = explode(":", $header, 2);
						$headers_array[strtolower(trim($key))] = trim($val);
					}
					$result['i']['headers'] = $headers_array;
				}
			} else {
				if ($this->curl_extra[$sid]['h'] === 'raw' || $this->curl_extra[$sid]['h'] === 'both') {
					$result['i']['headers_raw'] = '';
				}
				if ($this->curl_extra[$sid]['h'] !== 'raw') {
					$result['i']['headers'] = array();
				}
			}
		}

		$result['p'] = array($body);
		
		$result['i']['curl_getinfo'] = $curl_getinfo["content_type"];
		
		$result['i']['errno'] = $errno;
		$result['i']['error'] = $error;
		
		$result['pg'] = isset($curl_getinfo["total_time"]) ? $curl_getinfo["total_time"] : null;
		
		return $result;
	}
	
	private function _procCurl(&$responses, $final) {
		if (is_null($this->curl_mh) || !$this->curl_running) return;
		
		while (true) {
			$select = curl_multi_select($this->curl_mh, ($final ? 0 : $this->curl_select_timeout));
			if ($select == -1) break;
			do {
				$mrc = curl_multi_exec($this->curl_mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
			if (!$active || $mrc !== CURLM_OK) {
				$this->curl_running = false; // Done or error occured
				if ($mrc !== CURLM_OK) {
					$this->log->debug("Curl stack error (" . $mrc . ") " . curl_multi_strerror($mrc));
				}
				break;
			}
			if (!$final) break;
		}
		
		while(true) {
			$info = curl_multi_info_read($this->curl_mh);
			if ($info == false) break; // Nothing to read for now
			$ch = $info['handle'];
			$ch_id = (int)$ch;
			if (!isset($this->curl_id[$ch_id])) {
				$this->log->debug('Curl handle not found in $this->curl_id');
				continue;
			}
			$sid = $this->curl_id[$ch_id];
			
			$res = $this->_fillCurlResponse($sid, $ch, $info);
			
			unset($this->curl_id[$ch_id]);
			unset($this->curl_extra[$sid]);
			
			if (!$res) continue;

			$responses[$sid] = array(
				'sr' => null,           // Socket recreated
				'p' => $res['p'],       // Responses
				'pg' => $res['pg'],     // Time between first sent packet and first got packet (ping)
				't' => 1,               // Extra tries
				'i' => $res['i'],       // Information about request. Used for HTTP.
			);
		}
		
		if (!$this->curl_running) {
			curl_multi_close($this->curl_mh);
			
			// Cleanup
			$this->curl_mh = null;
			$this->curl_running = false;
			$this->curl_extra = array();
			$this->curl_id = array();
		}
	}

	
	public function process() {
		$responses = &$this->responses;
		unset($this->responses);

		// Reset sockets as we could get something while we didn't waited for data. We don't need it.
		$this->_procCleanSockets();

		// Run curl requests
		if (!is_null($this->curl_mh) && $this->curl_running) {
			do {
				$mrc = curl_multi_exec($this->curl_mh, $active);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}

		// Actual list of udp sockets resourses. sctn => socket_resource.
		$read_udp = array();
		// When sid times out, we need to unset $read_udp_sid[$sctn][$sid]. sid => sctn
		$read_udp_sctn = array();
		// List of sids per socket. We need this to know when should we stop listening that socket. sctn => array( sid => true, ...)
		$read_udp_sid = array();
		// Actual list of stream sockets resourses. sid => socket_resourse
		$read_stream = array();
// \/
		while (true) {
		/*
			The logic is pretty simple: send small amount of packets and then wait for data
			a little bit of time. Such alghoritm lets us to send very much packets.
			The only limitation is memory.
			
			We can't select both streams and sockets at once, so we have to choose something
			that will be the first. UDP sockets will receive much more packets than streams,
			so they are more important.
		*/
			if (empty($this->send)) break;

			// Send packets
			if ($this->_procWrite($responses, $read_udp, $read_udp_sctn, $read_udp_sid, $read_stream)) {
				$timeout = max($this->read_timeout, $this->read_retry_timeout);
			} else {
				$timeout = $this->loop_timeout;
			}

			$start_time = microtime(true);

			while (true) {
				if (empty($read_udp) && empty($read_stream)) {
					break;
				}

				$this->_procMarkTimedOut($responses, $read_udp, $read_udp_sctn, $read_udp_sid, $read_stream);
				if (!$this->_procRead($start_time, $timeout, $responses, $read_udp, $read_udp_sctn, $read_udp_sid, $read_stream))
					break;
			}
		}
// /\ memory allocated 36943/5000=7.4 kb , 373/50=7.4 kb
// $responses size 12761/5000=2.55 kb, 129/50=2.58 kb
// 1.3 kb freed after closing sockets

		// Mark recreated sockets
		foreach($this->recreated_stream as $sid => $r) {
			$responses[$sid]['sr'] = true;
		}
		foreach($this->recreated_udp as $sctn => $r) {
			foreach($this->sockets_udp_sid[$sctn] as $sid) {
				$responses[$sid]['sr'] = true;
			}
		}

		// Close sockets
		foreach ($this->sockets_stream_close as $sid => $true) {
			$this->_closeSocketStream($sid);
		}

		// Poll curl
		$this->_procCurl($responses, false);

		// Cleanup
		$this->responses = array();
		$this->send = array();
		$this->recreated_udp = array();
		$this->recreated_stream = array();
		$this->sockets_stream_close = array();

		return $responses;
	}
	
	// when there is no more stream and socket responses left
	public function finalProcess() {
		$responses = array();
		// Poll curl
		$this->_procCurl($responses, true);
		
		return $responses;
	}
	
	
	public function cleanUp() {
		foreach($this->sockets_udp as $sctn => &$sock) {
			$this->_closeSocketUDP($sctn);
		}
		
		foreach($this->sockets_stream as $sid => &$sock) {
			$this->_closeSocketStream($sid);
		}
		
		$this->cache_addr = array();
		$this->sockets_udp = array();
		$this->sockets_udp_data = array();
		$this->sockets_udp_send = array();
		$this->sockets_udp_sid = array();
		$this->sockets_udp_socks = array();
		$this->sockets_stream = array();
		$this->sockets_stream_id = array();
		$this->sockets_stream_data = array();
		$this->responses = array();
		$this->send = array();	
		$this->recreated_udp = array();
		$this->recreated_stream = array();
		
		$this->curl_mh = null;
		$this->curl_running = false;
		$this->curl_extra = array();
		$this->curl_id = array();
	}
}

class SocketsUserException extends \Exception {} // Various configuration errors
class SocketsException extends \Exception {}