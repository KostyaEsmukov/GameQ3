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

	const DEFAULT_ORDER = 'asc';
 
	public static function filter(&$data, $args) {
		if (empty($data['players']))
			return;

		$sortkeys = array(
			array('key' => 'name', 'order' => 'asc')
		);
		if (isset($args['sortkeys'])) {
			$sortkeys = $args['sortkeys'];
		} else 
		if (isset($args['sortkey'])) {
			$sortkeys = array('key' => $args['sortkey'], 'order' => isset($args['order']) ? $args['order'] : self::DEFAULT_ORDER);
		}


		$s = array();
		foreach($sortkeys as $k) {
			$r = new \stdClass();

			if (!isset($k['key']))
				continue;

			$r->key = $k['key'];

			if (!isset($k['order']))
				$k['order'] = self::DEFAULT_ORDER;

			$k['order'] = ($k['order'] == 'asc') || ($k['order'] == \SORT_ASC);

			$r->order = $k['order'];

			$s []= $r;
		}
		$sortkeys = $s;
		unset($s);
		
		uasort($data['players'], function($a, $b) use($sortkeys) {

			foreach($sortkeys as $k) {
				if (isset($a[$k->key]) && isset($b[$k->key]) && !is_array($a[$k->key]) && !is_array($b[$k->key])) {
					$ca = $a[$k->key];
					$cb = $b[$k->key];
				} else
				if (isset($a['other'][$k->key]) && isset($b['other'][$k->key]) && !is_array($a['other'][$k->key]) && !is_array($b['other'][$k->key])) {
					$ca = $a['other'][$k->key];
					$cb = $b['other'][$k->key];
				} else {
					continue;
				}

				if (is_string($ca) || is_string($cb)) {
					$res = strcasecmp("" . $ca, "" . $cb);

					if ($res == 0)
						continue;

					$res = $res < 0;
				} else {
					if ($ca === $cb)
						continue;

					$res = $ca < $cb;
				}

				if (!$k->order)
					$res = !$res;

				return ($res ? -1 : 1);
			}

			return 0;
		});
	}
	
}