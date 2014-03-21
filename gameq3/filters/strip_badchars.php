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
 
namespace GameQ3\filters;
 
class Strip_badchars {

	public static function filter(&$data, $args) {

		array_walk_recursive($data, function(&$val, $key) {
			if (is_string($val)) {
				$val = trim($val);

				// http://stackoverflow.com/a/1401716
				// http://stackoverflow.com/a/13695364
				$val = htmlspecialchars_decode(htmlspecialchars($val, \ENT_SUBSTITUTE, 'UTF-8'));
			}
		});

	}
}