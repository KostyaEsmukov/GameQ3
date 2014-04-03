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
 
abstract class Protocols {
	protected $protocol = 'undefined';
	protected $name = 'undefined';
	protected $name_long = "Unknown";
	
	const PT_SAME = 1; // Query and Connect ports are the same
	const PT_DIFFERENT_COMPUTABLE = 2; // It is possible to compute second port if we have first
	const PT_DIFFERENT_NONCOMPUTABLE_FIXED = 3; // Query port is the same for few connect_ports
	const PT_DIFFERENT_NONCOMPUTABLE_VARIABLE = 4; // Both ports are set by server admin in config files, so it is impossible to compute them. But servers return connect_port.
	const PT_CUSTOM = 5; // It is possible to compute ports using custom function fixPorts, which must be overloaded in protocols using this PT.
	const PT_UNKNOWN = 6; // PT is unknown. Both ports must be set.
	
	protected $query_addr = false;
	protected $query_port = false;
	protected $connect_addr = false;
	protected $connect_port = false;
	protected $ports_type = self::PT_UNKNOWN;
	protected $network = true;
	protected $connect_string = false;
	
	protected $server_info;
	private $unst;
	private $is_not_requested;

	/**
	 * @var Log
	 */
	private $log = null;
	private $debug = false;
	private $ping_sum = 0;
	private $ping_cnt = 0;
	private $retry_cnt = 0;

	/**
	 * @var Result
	 */
	protected $result;
	private $queue = array();
	private $queue_check = array();
	private $force_offline;

	public function __construct($server_info, $log) {
		$this->log = $log;
		
		if ($this->network) {
			$this->connect_addr = false;
			$connect_port = false;
			$this->query_addr = false;
			$query_port = false;

			if (isset($server_info['connect_addr'])) {
				$this->connect_addr = $server_info['connect_addr'];
			} else
			if (isset($server_info['connect_host'])) {
				list($this->connect_addr, $connect_port) = $this->parseHost($server_info['connect_host']);
			}
			if (isset($server_info['connect_port'])) $connect_port = $this->filterPort($server_info['connect_port']);
			
			if (isset($server_info['addr'])) {
				$this->query_addr = $server_info['addr'];
			} else
			if (isset($server_info['host'])) {
				list($this->query_addr, $query_port) = $this->parseHost($server_info['host']);
			}
			if (isset($server_info['port'])) $query_port = $this->filterPort($server_info['port']);
			
			if (isset($server_info['query_addr'])) {
				$this->query_addr = $server_info['query_addr'];
			} else
			if (isset($server_info['query_host'])) {
				list($this->query_addr, $query_port) = $this->parseHost($server_info['query_host']);
			}
			if (isset($server_info['query_port'])) $query_port = $this->filterPort($server_info['query_port']);
			
			if (!$this->connect_addr && !$this->query_addr) {
				throw new UserException("Missing server address info");
			}
			if ($this->connect_addr && !$this->query_addr) {
				$this->query_addr = $this->connect_addr;
			} else
			if (!$this->connect_addr && $this->query_addr) {
				$this->connect_addr = $this->query_addr;
			}
		
			$this->validateAddrAsString($this->query_addr);

			$this->fixPorts($query_port, $connect_port);
			
			if (!is_int($this->query_port))
				throw new UserException("Query port missing");

			unset($server_info['query_host'], $server_info['query_addr'], $server_info['query_port']);
			unset($server_info['connect_host'], $server_info['connect_addr'], $server_info['connect_port']);
		}

		$this->debug = (isset($server_info['debug']) && $server_info['debug']);
		unset($server_info['debug']);
		
		
		$this->unst = array();
		if (isset($server_info['unset'])) {
			if (!is_array($server_info['unset']))
				$server_info['unset'] = array($server_info['unset']);
				
			foreach($server_info['unset'] as $unst)
				$this->unst[$unst] = true;
		}
		unset($server_info['unset']);
		
		$this->is_not_requested = $this->unst;

		if (!isset($this->unst['teams']))
			$this->unst['teams'] = true; // Unset teams by default
		
		$this->server_info = $server_info;
		
		$this->construct();
	}
	
	private function parseHost($host) {
		$colonpos = strrpos($host, ':');
		if ($colonpos === false) {
			$addr = $host;
			$port = false;
		} else {
			$port = substr($host, $colonpos+1);
			if (!is_numeric($port)) {
				$addr = $host;
				$port = false;
			} else {
				$addr = substr($host, 0, $colonpos);
				$port = $this->filterPort($port);
			}
		}
		
		return array($addr, $port);
	}
	
	private function validateAddrAsString($addr) {
		if (!is_string($addr) || $addr === "")
			throw new UserException("Wrong address (empty)");
			
		if ($addr{0} == '[' && substr($addr, -1) == ']') {
			if (!filter_var(substr($addr, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
				throw new UserException("Wrong address (IPv6 filter failed): " . $addr);
		} else {
			if (!filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
				if (!preg_match('/^[a-zA-Z0-9._-]{1,255}$/', $addr))
					throw new UserException("Wrong address (IPv4 and hostname filters failed): " . $addr);
		}
		
		return true;
	}
	
	private function filterPort($var) {
		if (is_int($var)) return $var;
		if (is_string($var) && ctype_digit($var)) return intval($var);
		return false;
	}
	
	protected function fixPorts($query_port, $connect_port) {
		if ($this->ports_type === self::PT_CUSTOM)  {
			$this->error("Function fixPorts must be overloaded for PT_CUSTOM ports type");
			throw new UserException("Protocol error: function fixPorts must be overloaded for PT_CUSTOM ports type");
		}
		
		$q_set = is_int($query_port);
		$c_set = is_int($connect_port);
		$any_set = ($q_set || $c_set);
		
		if (!is_int($this->query_port)) $this->query_port = false;
		if (!is_int($this->connect_port)) $this->connect_port = false;
		
		$q_default = $this->query_port; // Main value
		$c_default = $this->connect_port;
		
		if ($q_set && $c_set) {
			$this->query_port = $query_port;
			$this->connect_port = $connect_port;
			return;
		}
		if ($this->ports_type === self::PT_UNKNOWN)  {
			throw new UserException("Both query and connect ports must be defined for this protocol");
		}
		if (!$any_set) {
			if ($this->ports_type === self::PT_SAME)  {
				// $c_default is totally ignored here
				$this->connect_port = $q_default;
			}
			return;
		}
		
		// Just one port is not set there
		
		if ($this->ports_type === self::PT_SAME) {
			if ($q_set) {
				$this->query_port = $query_port;
				$this->connect_port = $query_port;
			} else {
				$this->query_port = $connect_port;
				$this->connect_port = $connect_port;
			}
		} else
		if ($this->ports_type === self::PT_DIFFERENT_COMPUTABLE) {
			$diff = $q_default - $c_default;
			
			if ($q_set) {
				$this->query_port = $query_port;
				$this->connect_port = $query_port - $diff;
			} else {
				$this->query_port = $connect_port + $diff;
				$this->connect_port = $connect_port;
			}
		} else
		if ($this->ports_type === self::PT_DIFFERENT_NONCOMPUTABLE_FIXED) {
			if ($q_set) {
				if ($q_default !== $query_port) {
					$this->query_port = $query_port;
					$this->connect_port = false; // This value should be filled by protocols when possible
				}
			} else {
				$this->connect_port = $connect_port;
			}
		} else
		if ($this->ports_type === self::PT_DIFFERENT_NONCOMPUTABLE_VARIABLE) {
			if ($q_set) {
				if ($q_default !== $query_port) {
					$this->query_port = $query_port;
					$this->connect_port = false; // This value should be filled by protocols when possible
				}
			} else {
				if ($c_default !== $connect_port) {
					throw new UserException("Query port must be defined for this protocol");
				} else {
					$this->connect_port = $connect_port;
				}
			}
		} else {
			throw new UserException("Unknown ports_type for game " . $this->name);
		}
	}
	
	
	final protected function debug($str) {
		$str = '{' . $this->protocol . '} ' . $str;
		// Rise priority when we need
		if ($this->debug)
			$this->log->warning($str, true, 1);
		else
			$this->log->debug($str, true, 1);
	}
	
	final protected function error($str) {
		$this->log->error($str, true, 1);
	}
	
	
	final protected function setConnectPort($port) {
		$port = $this->filterPort($port);
		if (!is_int($port)) return;
		if (is_int($this->connect_port) && $this->connect_port !== $port) {
			$this->debug("Defined connect port '" . $this->connect_port ."' is not equal to received from the server one '" . $port . "'. Using port provided by server");
		}
		$this->connect_port = $port;
	}
	
	final protected function getConnectString() {
		$res = $this->genConnectString();
		
		if (!is_string($res))
			return null;
			
		return $res;
	}
	
	protected function genConnectString() {
		if (!is_string($this->connect_string) || !is_int($this->connect_port))
			return false;

		return str_replace(
			array(
				'{CONNECT_PORT}',
				'{CONNECT_ADDR}',
				'{IDENTIFIER}'
			),
			array(
				$this->connect_port,
				$this->connect_addr,
				$this->getIdentifier()
			),
			$this->connect_string
		);
	}
	
	// Human-readable string that identifies servers.
	protected function getIdentifier() {
		if (!$this->network) {
			$this->error("function getIdentifier must always be overloaded in non-network protocols");
			return false;
		}
		return $this->connect_addr . ':' . $this->connect_port;
	}

	
	protected function filterInt($var) {
		if (is_string($var)) {
			if (ctype_digit($var)) {
				$i = intval($var);
				return ($i == $var ? $i : $var); // overflow check
			}
			//if (preg_match('/^[-]?[0-9.]+$/', $var))
			//	return floatval($var);
		}
		return $var;
	}

	final protected function forceRequested($s, $v) {
		$this->is_not_requested[$s] = !$v;
	}


	final protected function isRequested($s) {
		return (!isset($this->is_not_requested[$s]) || !$this->is_not_requested[$s]);
	}

	final protected function addPing($p) {
		if ($p !== null) {
			$this->ping_sum += $p;
			$this->ping_cnt++;
		}
	}
	
	final protected function addRetry($r) {
		if ($r !== null)
			$this->retry_cnt += $r;
	}
	
	final protected function unCheck($name) {
		unset($this->queue_check[$name]);
	}
	
	final public function popRequests() {
		$q = &$this->queue;
		unset($this->queue);
		$this->queue = array();
		return $q;
	}
	
	final protected function queue($name, $transport, $packets, $more = array() ) {
		$this->queue_check[$name] = true;
		
		$this->queue[$name] = array(
			'addr' => $this->query_addr,
			'port' => $this->query_port,
			'transport' => $transport,
			'packets' => $packets,
		);
		
		foreach($more as $key => $val) {
			if ($key == 'nocheck')
				$this->unCheck($name);
			else
				$this->queue[$name][$key] = $val;
		}
	}
	

	final public function protocolInit() {
		$this->ping_sum = 0;
		$this->ping_cnt = 0;
		$this->retry_cnt = 0;
		$this->force_offline = false;
		
		$this->result = new Result($this->unst);
		
		$this->result->addInfo('query_addr', $this->query_addr);
		$this->result->addInfo('query_port', $this->query_port);
		
		$this->result->addInfo('connect_addr', $this->connect_addr);
		$this->result->addInfo('connect_port', $this->connect_port);
		
		$this->result->addInfo('protocol', $this->protocol);
		$this->result->addInfo('short_name', $this->name);
		$this->result->addInfo('long_name', $this->name_long);
		
		try {
			if ($this->init() === false)
				$this->force_offline = true;
		}
		catch(\Exception $e) {
			$this->debug("Init '" . get_class($e) . "' exception with message: " . $e->getMessage());
		}
	}
	
	protected function construct() {
		// Overload
		return;
	}

	protected function init() {
		// Overload
		return;
	}
	
	protected function preFetch() {
		// Overload
		return;
	}

	protected function processRequests($qid, $requests) {
		// Overload
		$this->error("function processRequests must always be overloaded in protocols");
		return false;
	}
	
	final public function startRequestProcessing($qid, $requests) {
		$this->addPing($requests['ping']);
		$this->addRetry($requests['retry_cnt']);
		$this->unCheck($qid);
		
		try {
			if ($this->processRequests($qid, $requests) === false)
				$this->force_offline = true;
		}
		catch(\Exception $e) {
			$this->debug("ProcessRequests '" . get_class($e) . "' exception with message: " . $e->getMessage());
		}
	}
	
	final public function startPreFetch() {
		try {
			if ($this->preFetch() === false)
				$this->force_offline = true;
		}
		catch(\Exception $e) {
			$this->debug("PreFetch '" . get_class($e) . "' exception with message: " . $e->getMessage());
		}
		return;
	}
	

	final public function resultFetch() {
		$important_keys = array('num_players', 'max_players', 'hostname');
		$additional_keys = array('bot_players' => null, 'private_players' => null, 'password' => null, 'version' => null, 'map' => null, 'mode' => null, 'secure' => null);
		
		$identifier = $this->getIdentifier();
		
		$online = true;
		
		if ($this->force_offline || $identifier === false) {
			$online = false;
		}
		
		// Some request haven't been received, assume we failed
		if (!empty($this->queue_check)) {
			$online = false;
		}
		
		if ($online && $this->network && !is_int($this->connect_port)) {
			$this->debug("Connect_port is not set");
			$online = false;
		}
			
		foreach($important_keys as $key)
			if (!$this->result->issetGeneral($key)) {
				$online = false;
				break;
			}
		
		if ($online) {
			foreach($additional_keys as $key => $default)
				if (!$this->result->issetGeneral($key)) {
					$this->result->addGeneral($key, $default);
				}
		}
		
		$this->result->addInfo('connect_string', $this->getConnectString()); // We could do this in protocolInit() function, but connect_port can be changed in some protocols after query.
		
		$ping = ($this->ping_cnt != 0 ? ($this->ping_sum / $this->ping_cnt)*1000 : null);
		$this->result->addInfo('online', $online);
		$this->result->addInfo('ping_average', $ping);
		$this->result->addInfo('retry_count', $this->retry_cnt);
		$this->result->addInfo('identifier', $identifier);
		
		$res = $this->result->fetch();
		$this->result = null;

		return $res;
	}
}
