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
 
/**
 * GameQ3
 *
 * This is a library to query gameservers and return their answer in universal well-formatted array.
 *
 * This library influenced by GameQv1 (author Tom Buskens <t.buskens@deviation.nl>) 
 * and GameQv2 (url https://github.com/Austinb/GameQ, author Austin Bischoff <austin@codebeard.com>)
 *
 * @author Kostya Esmukov <kostya.shift@gmail.com>
 */

namespace GameQ3;

// Autoload classes
spl_autoload_extensions(".php");
spl_autoload_register();

set_include_path(get_include_path() . PATH_SEPARATOR . realpath(dirname(__FILE__). '/../'));


class GameQ3 {

	// Config
	private $servers_count = 2500;

	// Working vars
	private $sock = null;
	private $log = null;
	private $filters = array();
	private $servers_filters = array();
	private $servers = array();
	
	private $started = false;
	private $request_servers = array();

	public function __construct() {
		$this->log = new Log();
		$this->sock = new Sockets($this->log);
	}
	
	/**
	 * Set logging rules.
	 * Logger uses error_log() by default. This can be changed using GameQ3::setLogger()
	 * @param bool $error Log errors or not
	 * @param bool $warning Log warnings or not
	 * @param bool $debug Log debug messages or not
	 * @param bool $trace Log backtrace or not
	 */
	public function setLogLevel($error, $warning = true, $debug = false, $trace = false) {
		$this->log->setLogLevel($error, $warning, $debug, $trace);
	}

	/**
	 * Set logger function.
	 * @param callable $callback function($msg)
	 * @throws UserException
	 */
	public function setLogger($callback) {
		if (is_callable($callback))
			$this->log->setLogger($callback);
		else
			throw new UserException("Argument for setLogger must be callable");
	}
	
	private function _getsetOption($set, $key, $value = null) {
		$error = false;
		
		switch($key) {
			case 'servers_count':
				if ($set) {
					if (is_int($value))
						$this->$key = $value;
					else
						$error = 'int';
				} else {
					return $this->$key;
				}
				break;
			
			default:
				return $this->sock->getsetOption($set, $key, $value);
		}
		
		if ($error !== false)
			throw new UserException("Value for setOption must be " . $error . ". Got value: " . var_export($value, true));
			
		return true;
	}

	/**
	 * Set option. See readme for a list of options.
	 * @param string $key
	 * @param mixed $value
	 * @throws UserException
	 */
	public function setOption($key, $value) {
		if ($this->started)
			throw new UserException("You cannot set options while in request");
		return $this->_getsetOption(true, $key, $value);
	}
	
	public function __set($key, $value) {
		return $this->setOption($key, $value);
	}
	
	public function __get($key) {
		return $this->_getsetOption(false, $key);
	}
	
	/**
	 * Set filter. See readme for a list of filters
	 * @param string $name Filter name
	 * @param array $args Filter options
	 * @throws UserException
	 */
	public function setFilter($name, $args = array()) {
		if ($this->started)
			throw new UserException("You cannot set filter while in request");

		if (!is_array($args))
			throw new UserException("Args must be an array in setFilter (name '" . $name . "')");

		$this->filters[$name] = $args;
	}

	/**
	 * Unset filter.
	 * @param string $name Filter name
	 * @throws UserException
	 */
	public function unsetFilter($name) {
		if ($this->started)
			throw new UserException("You cannot unset filter while in request");

		unset($this->filters[$name]);
	}

	/**
	 * Returns information about protocol.
	 * @param string $protocol
	 * @throws UserException
	 * @return array
	 */
	public function getProtocolInfo($protocol) {
		if (!is_string($protocol))
			throw new UserException("Protocol must be a string");
			
		$className = "\\GameQ3\\Protocols\\". ucfirst(strtolower($protocol));
		
		$reflection = new \ReflectionClass($className);
		
		if(!$reflection->IsInstantiable()) {
			return false;
		}
		
		$dp = $reflection->getDefaultProperties();
		$dc = $reflection->getConstants();
		$pt_string = 'UNKNOWN';

		foreach($dc as $name => $val) {
			// filter out non-PT constants
			if (substr($name, 0, 3) !== "PT_") continue;

			if ($val === $dp['ports_type']) {
				$pt_string = substr($name, 3);
				break;
			}
		}
		
		$res = array(
			'protocol' => $dp['protocol'],
			'name' => $dp['name'],
			'name_long' => $dp['name_long'],
			'query_port' => (is_int($dp['query_port']) ? $dp['query_port'] : null),
			'connect_port' => (is_int($dp['connect_port']) ? $dp['connect_port'] : ($pt_string === 'SAME' ? $dp['query_port'] : null)), // connect_port shouldn't be set in PT_SAME
			'ports_type' => $dp['ports_type'],
			'ports_type_string' => $pt_string,
			'ports_type_info' => array(
				'connect_port' => true,
				'query_port' => ($pt_string !== 'SAME'), // Only PT_SAME ignores query port
			),
			'network' => $dp['network'],
			'connect_string' => (is_string($dp['connect_string']) ? $dp['connect_string'] : null),
		);
		
		unset($reflection);
		
		return $res;
	}
	
	/**
	 * Returns array of info for each protocol
	 * @see GameQ3::getProtocolInfo()
	 * @return array
	 */
	public function getAllProtocolsInfo() {
		$protocols_path = dirname(__FILE__) . "/protocols/";

		$dir = dir($protocols_path);
		$protocols = array();

		while (true) {
			$entry = $dir->read();
			if ($entry === false) break;
			
			if(!is_file($protocols_path.$entry)) {
				continue;
			}
			
			$protocol = pathinfo($entry, PATHINFO_FILENAME);

			$res = $this->getProtocolInfo($protocol);

			if (!empty($res))
				$protocols[strtolower($protocol)] = $res;
		}
		
		unset($dir);

		ksort($protocols);
		
		return $protocols;
	}

	/**
	 * Add a server to be queried
	 * @param array $server_info
	 * @throws UserException
	 */
	public function addServer($server_info) {
		if ($this->started)
			throw new UserException("You cannot add servers while in request");

		if (!is_array($server_info))
			throw new UserException("Server_info must be an array");
			
		if (!isset($server_info['type']) || !is_string($server_info['type'])) {
			throw new UserException("Missing server info key 'type'");
		}

		if (!isset($server_info['id']) || (!is_string($server_info['id']) && !is_numeric($server_info['id']))) {
			throw new UserException("Missing server info key 'id'");
		}
		
		// already added
		if (isset($this->servers[ $server_info['id'] ]))
			return;

		if (!empty($server_info['filters'])) {
			if (!is_array($server_info['filters']))
				throw new UserException("Server info key 'filters' must be an array");
				
			$this->servers_filters[ $server_info['id'] ] = array();
			// check filters array
			foreach($server_info['filters'] as $filter => &$args) {
				if ($args !== false && !is_array($args))
					throw new UserException("Filter arguments must be an array or boolean false");
				$this->servers_filters[ $server_info['id'] ][ $filter ] = $args;
			}
			
			unset($server_info['filters']);
		}

		$protocol_class = "\\GameQ3\\Protocols\\".ucfirst($server_info['type']);

		try {
			if (!class_exists($protocol_class, true)) // PHP 5.3
				throw new UserException("Class " . $protocol_class . " could not be loaded");
			$this->servers[ $server_info['id'] ] = new $protocol_class($server_info, $this->log);
		}
		catch(\LogicException $e) { // Class not found PHP 5.4
			throw new UserException($e->getMessage());
		}
	}
	
	/**
	 * Unset server from list of serers to query
	 * @param string $id Server id
	 * @throws UserException
	 */
	public function unsetServer($id) {
		if ($this->started)
			throw new UserException("You cannot unset servers while in request");

		unset($this->servers[$id]);
	}

	// addServers removed because you have to decide what to do when exception occurs. This function does not handle them. 
	
	private function _clear() {
		//$this->filters = array();
		//$this->servers_filters = array();
		//$this->servers = array();
		$this->started = false;
		$this->request_servers = array();
	}
	
	/**
	 * Request added servers and return all responses
	 * @return array
	 */
	public function requestAllData() {
		$result = array();
		while (true) {
			$res = $this->_request();
			if ($res === false || !is_array($res))
				break;
			
			// I hate array_merge. It's like a blackbox.
			foreach($res as $key => $val) {
				$result[$key] = $val;
				unset($res[$key]);
			}
		} 
		return $result;
	}
	
	// returns array until we have servers to reqest. otherwise returns false

	/**
	 * Request added servers and return responses by parts. Returns false when there are no responses left.
	 * @return mixed array or false
	 */
	public function requestPartData() {
		return $this->_request();
	}

	private function _applyFilters($key, &$result) {
		$sf = (isset($this->servers_filters[$key]) ? $this->servers_filters[$key] : array());
		foreach($this->filters as $name => $args) {
			if (isset($sf[$name])) {
				$args = $sf[$name];
				unset($sf[$name]);
				if ($args === false) continue;
			}
			$filt = "\\GameQ3\\Filters\\".ucfirst($name);
			
			try {
				class_exists($filt, true); // try to load class
				call_user_func_array($filt . "::filter", array( &$result, $args ));
			}
			catch(\Exception $e) {
				$this->log->warning($e);
			}
		}
		
		foreach($sf as $name => $args) {
			if ($args === false) continue;
			
			$filt = "\\GameQ3\\Filters\\".ucfirst($name);
			
			try {
				class_exists($filt, true); // try to load class
				call_user_func_array($filt . "::filter", array( &$result, $args ));
			}
			catch(\Exception $e) {
				$this->log->warning($e);
			}
		}
	}

	private function _request() {
		if (!$this->started) {
			$this->started = true;
// \/
			foreach($this->servers as &$instance) {
				try {
					$instance->protocolInit();
				}
				catch (\Exception $e) {
					$this->log->warning($e);
				}
			}
// /\ memory allocated 14649/5000=3 kb, 152/50=3 kb

			$this->request_servers = $this->servers;
		}

		if (empty($this->request_servers)) {
			$this->started = false;
			$this->_clear();
			return false;
		}

		$servers_left = array();
		$servers_queried = array();

		$s_cnt = 1;
		foreach($this->request_servers as $server_id => &$instance) {
			$servers_left[$server_id] = $instance;
			$servers_queried[$server_id] = $instance;
			unset($this->request_servers[$server_id]);
			
			$s_cnt++;
			if ($s_cnt > $this->servers_count)
				break;
		}
		
		$process = array();

		while (true) {
			if (empty($servers_left)) break;
			
			$final_process = true;

			foreach($servers_left as $server_id => &$instance) {
				try {
					$instance_queue = $instance->popRequests();

					if (empty($instance_queue)) {
						unset($servers_left[$server_id]);
						continue;
					}
					
					$final_process = false;

					foreach($instance_queue as $queue_id => &$queue_qopts) {
						$sid = $this->sock->allocateSocket($server_id, $queue_id, $queue_qopts);
						$process[$sid] = array(
							'id' => $queue_id,
							'i' => $instance
						);
					}
				}
				catch (SocketsException $e) { // not resolvable hostname, etc
					$this->log->debug($e);
				}
				catch (\Exception $e) { // wrong input data
					$this->log->warning($e);
				}
			}
			
			if ($final_process) {
				$response = $this->sock->finalProcess();
				if (empty($response)) break;
			} else {
				$response = $this->sock->process();
			}

			foreach($response as $sid => $ra) {
				if (empty($ra['p']) || !isset($process[$sid])) continue;

				try { // Protocols should handle exceptions by themselves
					$process[$sid]['i']->startRequestProcessing(
						$process[$sid]['id'],
						array(
							'ping' => $ra['pg'],
							'retry_cnt' => ($ra['t']-1),
							'responses' => $ra['p'],
							'socket_recreated' => $ra['sr'],
							'info' => $ra['i'],
						)
					);
				}
				catch(\Exception $e) {
					$this->log->debug($e);
				}
				unset($response[$sid]);
				unset($process[$sid]);
			}
		}

		$this->sock->cleanUp();
		
		$result = array();
		foreach($servers_queried as $key => &$instance) {
			try {
				$instance->startPreFetch();
			}
			catch(\Exception $e) {
				$this->log->debug($e);
			}
			$result[$key] = $instance->resultFetch();
			$this->_applyFilters($key, $result[$key]);
		}

		return $result;
	}
}

class UserException extends \Exception {}