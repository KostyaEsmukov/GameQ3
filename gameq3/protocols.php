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
	protected $protocol = 'core';
	protected $name = 'core';
	protected $name_long = "Core protocol";
	protected $port;

	protected $addr;
	protected $connect_addr;
	protected $connect_port;
	protected $server_info;
	private $unst;
	private $log = null;
	private $debug = false;
	private $ping_sum = 0;
	private $ping_cnt = 0;
	private $retry_cnt = 0;
	protected $result;
	private $queue = array();
	private $queue_check = array();
	protected $network = true;
	private $force_offline;

	public function __construct($server_info, $log) {
		$this->log = $log;
		
		$default_port = $this->port;
		if ($this->network) {
			list($this->addr, $this->port, $this->connect_addr, $this->connect_port) = Sockets::fillQueryConnectHosts($server_info);


			if (!is_int($this->port))
				$this->port = $default_port;
				
			if (!is_int($this->connect_port))
				$this->connect_port = $this->port;
				
			unset($server_info['host'], $server_info['addr'], $server_info['port']);
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
		
		$this->server_info = $server_info;
		
		$this->construct();
	}
	
	final protected function debug($str) {
		// Rise priority when we need
		if ($this->debug)
			$this->log->warning($str);
		else
			$this->log->debug($str);
	}
	
	final protected function error($str) {
		$this->log->error($str);
	}
	
	final protected function isRequested($s) {
		return (!isset($this->unst[$s]));
	}

	final protected function addPing($p) {
		$this->ping_sum += $p;
		$this->ping_cnt++;
	}
	
	final protected function addRetry($r) {
		$this->retry_cnt += $r;
	}
	
	final protected function unCheck($name) {
		unset($this->queue_check[$name]);
	}
	
	protected function filterInt($var) {
		if (is_string($var)) {
			if (ctype_digit($var))
				return intval($var);
			if (is_numeric($var))
				return floatval($var);
		}
		return $var;
	}

	final public function protocolInit() {
		$this->ping_sum = 0;
		$this->ping_cnt = 0;
		$this->retry_cnt = 0;
		$this->force_offline = false;
		
		$this->result = new Result($this->unst);
		
		$this->result->addInfo('query_addr', $this->addr);
		$this->result->addInfo('query_port', $this->port);
		
		$this->result->addInfo('connect_addr', $this->connect_addr);
		$this->result->addInfo('connect_port', $this->connect_port);
		
		$this->result->addInfo('protocol', $this->protocol);
		$this->result->addInfo('short_name', $this->name);
		$this->result->addInfo('long_name', $this->name_long);
		
		if ($this->init() === false)
			$this->force_offline = true;
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
		return;
	}
	
	final public function startRequestProcessing($qid, $requests) {
		$this->addPing($requests['ping']);
		$this->addRetry($requests['retry_cnt']);
		$this->unCheck($qid);
		
		if ($this->processRequests($qid, $requests) === false)
			$this->force_offline = true;
	}
	
	final public function startPreFetch() {
		if ($this->preFetch() === false)
			$this->force_offline = true;
		return;
	}
	
	final public function popRequests() {
		$q = &$this->queue;
		unset($this->queue);
		$this->queue = array();
		return $q;
	}
	
	protected function queue($name, $transport, $packets, $more = array() ) {
		$this->queue_check[$name] = true;
		
		$this->queue[$name] = array(
			'addr' => $this->addr,
			'port' => $this->port,
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

	final public function resultFetch() {
		$important_keys = array('num_players', 'max_players', 'hostname');
		$additional_keys = array('private_players' => null, 'password' => false, 'version' => null, 'map' => null, 'mode' => null, 'secure' => false);
		
		$online = true;
		
		if ($this->force_offline)
			$online = false;
		
		// Some request haven't been received, assume we failed
		if (!empty($this->queue_check))
			$online = false;
			
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
		
		$ping = ($this->ping_cnt != 0 ? ($this->ping_sum / $this->ping_cnt)*1000 : null);
		$this->result->addInfo('online', $online);
		$this->result->addInfo('ping_average', $ping);
		$this->result->addInfo('retry_count', $this->retry_cnt);
		
		$res = $this->result->fetch();
		$this->result = null;

		return $res;
	}
}