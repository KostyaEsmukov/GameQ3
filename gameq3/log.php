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

class Log {
	const DEBUG = 1; //0b0001;
	const WARNING = 2; //0b0010;
	const ERROR = 4; //0b0100;
	
	const TRACE_LIMIT = 4;
	const TRACE_IGNORE = 2;
	const FORCE_TRACE_LIMIT = 1;
	
	private $loglevel = 4; //0b0100;
	private $trace = false;
	private $logger = null;
	
	public function __construct() {
		$this->logger = function($str) {
			error_log($str);
		};
	}

	public function setLogLevel($error, $warning, $debug, $trace) {
		$this->loglevel = 0;
		if ($error) $this->loglevel += self::ERROR;
		if ($warning) $this->loglevel += self::WARNING;
		if ($debug) $this->loglevel += self::DEBUG;
		$this->trace = ($trace == true);
	}
	
	public function setLogger($callback) {
		if (is_callable($callback))
			$this->logger = $callback;
	}
	
	private function _logger($str) {
		call_user_func($this->logger, $str);
	}
	
	private function _log($reason, $str) {
		$this->_logger('GameQ3 [' . $reason . '] ' . $str);
	}

	private function _backtrace($force, $trace_skip) {
		if (!$this->trace && !$force) return;
		
		$trace_limit = $this->trace ? self::TRACE_LIMIT : self::FORCE_TRACE_LIMIT;

		$trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, ($trace_limit + $trace_skip + self::TRACE_IGNORE));
		
		$i = 0;
		$result = "Trace:\n";
		
		foreach($trace as $v) {
			$i++;
			if ($i <= ($trace_skip + self::TRACE_IGNORE)) {
				continue;
			}
			$result .= '#' . str_pad(($i - $trace_skip - self::TRACE_IGNORE - 1), 2) . ' ' . (isset($v['class']) ? $v['class'] . '->' : '') . $v['function'] . '() called at [' . $v['file'] . ':' . $v['line'] . ']' . "\n";

			if ($i >= ($trace_limit + $trace_skip + self::TRACE_IGNORE)) break;
		}
		
		$this->_logger($result);
	}
	
	private function _eToStr(&$e) {
		if (!is_object($e)) return;
		if (!($e instanceof \Exception)) return;
		$e = "'" . get_class($e) . "' exception with message: " . $e->getMessage();
	}
	
	private function _message($type, $reason, $str, $force_trace, $trace_skip) {
		if (!($this->loglevel & $type)) return;
		$this->_eToStr($str);
		$this->_log($reason, $str);
		$this->_backtrace($force_trace, $trace_skip);
	}
	
	public function debug($str, $force_trace = false, $trace_skip = 0) {
		$this->_message(self::DEBUG, 'Debug', $str, $force_trace, $trace_skip);
	}
	public function warning($str, $force_trace = false, $trace_skip = 0) {
		$this->_message(self::WARNING, 'Warning', $str, $force_trace, $trace_skip);
	}
	public function error($str, $force_trace = false, $trace_skip = 0) {
		$this->_message(self::ERROR, 'Error', $str, $force_trace, $trace_skip);
	}
}