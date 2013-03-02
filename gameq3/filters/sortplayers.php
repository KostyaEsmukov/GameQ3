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
 
class Sortplayers {
 
	// add html support
	public static function filter(&$data, $args) {
		if (empty($data['players'])) return;
		if (!isset($args['sortkey'])) return;
		
		$sortkey = (isset($args['sortkey']) ? $args['sortkey'] : 'name');
		$sortdirection = (isset($args['order']) ? ($args['order'] == 'asc') || ($args['order'] == SORT_ASC) : false);
		
		
		uasort($data['players'], function($a, $b) use($sortkey, $sortdirection) {
			if (!isset($a[$sortkey]) || !isset($b[$sortkey])) {
				if (!isset($a['other'][$sortkey]) || !isset($b['other'][$sortkey])) {
					return false;
				} else {
					$t1 = $a['other'][$sortkey];
					$t2 = $b['other'][$sortkey];
				}
			} else {
				$t1 = $a[$sortkey];
				$t2 = $b[$sortkey];
			}
			if ($t1 === $t2) return 0;
			$b = is_string($t1) || is_string($t2) ? strcasecmp($t1, $t2) < 0 : ($t1 < $t2);
			$b = $sortdirection ? $b : !$b;
			return ($b ? -1 : 1);
		});
	}
	
}