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
	const DEBUG = 0x0001;
	const WARNING = 0x0010;
	const ERROR = 0x0100;
	
	private $loglevel = 0x0100;
	private $trace = false;

	public function setLogLevel($error, $warning, $debug, $trace) {
		$this->loglevel = 0;
		if ($error) $this->loglevel += self::ERROR;
		if ($warning) $this->loglevel += self::WARNING;
		if ($debug) $this->loglevel += self::DEBUG;
		$this->trace = $trace;
	}
	
	private function _logger($reason, $str) {
		error_log('GameQ3 [' . $reason . '] ' . $str);
	}
	
	public function debug($str) {
		if ($this->loglevel & self::DEBUG) $this->_logger('Debug', $str);
		if ($this->trace) debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}
	public function warning($str) {
		if ($this->loglevel & self::WARNING) $this->_logger('Warning', $str);
		if ($this->trace) debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}
	public function error($str) {
		if ($this->loglevel & self::ERROR) $this->_logger('Error', $str);
		if ($this->trace) debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
	}
}