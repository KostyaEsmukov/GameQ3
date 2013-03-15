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
	const FORCE_TRACE_LIMIT = 1;
	
	private $loglevel = 4; //0b0100;
	private $trace = false;

	public function setLogLevel($error, $warning, $debug, $trace) {
		$this->loglevel = 0;
		if ($error) $this->loglevel += self::ERROR;
		if ($warning) $this->loglevel += self::WARNING;
		if ($debug) $this->loglevel += self::DEBUG;
		$this->trace = ($trace == true);
	}
	
	private function _logger($str) {
		error_log($str);
	}
	
	private function _log($reason, $str) {
		$this->_logger('GameQ3 [' . $reason . '] ' . $str);
	}
	
	private function _backtrace($force, $trace_skip) {
		if (!$this->trace && !$force) return;
		
		$trace_limit = $this->trace ? self::TRACE_LIMIT : self::FORCE_TRACE_LIMIT;
		
		//  Save some memory in modern versions
		// http://php.net/manual/ru/function.debug-backtrace.php
		$php_version = phpversion();
		if (version_compare(($php_version), '5.4.0', '>=')) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, ($trace_limit+$trace_skip+1));
		} else
		if (version_compare(($php_version), '5.3.6', '>=')) {
			$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		} else {
			$trace = debug_backtrace(false);
		}
		
		unset($trace[0]);
		
		$i = 0;
		$result = "Trace:\n";
		
		foreach($trace as $v) {
			$i++;
			if ($i <= $trace_skip) {
				continue;
			}
			$result .= '#' . str_pad($i, 2) . ' ' . (isset($v['class']) ? $v['class'] . '->' : '') . $v['function'] . '() called at [' . $v['file'] . ':' . $v['line'] . ']' . "\n";

			if ($i >= ($trace_limit+$trace_skip)) break;
		}
		
		$this->_logger($result);
	}
	
	public function debug($str, $force_trace = false, $trace_skip = 0) {
		if (!($this->loglevel & self::DEBUG)) return;
		$this->_log('Debug', $str);
		$this->_backtrace($force_trace, $trace_skip);
	}
	public function warning($str, $force_trace = false, $trace_skip = 0) {
		if (!($this->loglevel & self::WARNING)) return;
		$this->_log('Warning', $str);
		$this->_backtrace($force_trace, $trace_skip);
	}
	public function error($str, $force_trace = false, $trace_skip = 0) {
		if (!($this->loglevel & self::ERROR)) return;
		$this->_log('Error', $str);
		$this->_backtrace($force_trace, $trace_skip);
	}
}