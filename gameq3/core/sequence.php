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


class Sequence {

	private $alphabet = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

	private $alphabet_size;
	private $reverse = array();

	private $cur;
	private $cur_len;


	public function __construct() {
		$this->alphabet_size = strlen($this->alphabet);

		$this->cur = $this->alphabet{0};
		$this->cur_len = 1;

		for ($i=0; $i < $this->alphabet_size; $i++) {
			$this->reverse[$this->alphabet{$i}] = $i;
		}
	}

	private function _inc() {
		$pos = $this->cur_len - 1;

		while($pos >= 0) {
			$i = $this->reverse[ $this->cur{$pos} ];
			if ($i == $this->alphabet_size-1) {
				$this->cur{$pos} = $this->alphabet{0};
			} else {
				$this->cur{$pos} = $this->alphabet{$i + 1};
				return;
			}

			--$pos;
		}

		$this->cur = $this->alphabet{0} . $this->cur;
		++$this->cur_len;
	}

	public function next() {
		$this->_inc();
		return $this->cur;
	}
} 