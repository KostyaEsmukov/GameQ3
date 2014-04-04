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

namespace GameQ3\Core;

use GameQ3\UserException;

class Options {

	const TYPE_INT = 1;
	const TYPE_FLOAT = 2;
	const TYPE_ARRAY = 3;

	private $storage = array();

	public function register($name, $default_value, $type) {
		if ($type == 'array') {
			$type = self::TYPE_ARRAY;
		} else
		if ($type == 'int') {
			$type = self::TYPE_INT;
		} else
		if ($type == 'float') {
			$type = self::TYPE_FLOAT;
		} else {
			throw new UserException("Bad option type");
		}

		$this->storage[$name] = array(
			'value' => $default_value,
			'type' => $type
		);
	}

	public function set($name, $value) {
		if (!isset($this->storage[$name]))
			throw new UserException("Unknown option " . $name);

		$t = $this->storage[$name]['type'];

		if ($t == self::TYPE_ARRAY && !is_array($value)) {
			throw new UserException("Bad value type for " . $name . ". It must be array");
		}
		if ($t == self::TYPE_FLOAT && !is_float($value)) {
			throw new UserException("Bad value type for " . $name . ". It must be float");
		}
		if ($t == self::TYPE_INT && !(is_int($value) || is_float($value))) {
			throw new UserException("Bad value type for " . $name . ". It must be int");
		}

		$this->storage[$name]['value'] = $value;
	}

	public function get($name) {
		if (!isset($this->storage[$name])) {
			throw new \Exception("Unknown option " . $name);
		}

		return $this->storage[$name]['value'];
	}

	public function __get($k) {
		return $this->get($k);
	}
} 