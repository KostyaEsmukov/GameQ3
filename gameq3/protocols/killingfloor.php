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
 */


namespace GameQ3\protocols;
 
class Killingfloor extends \GameQ3\Protocols\Unreal2 {
	protected $name = "killingfloor";
	protected $name_long = "Killing Floor";

	protected $query_port = 7708;
	protected $connect_port = 7707;
	protected $ports_type = self::PT_DIFFERENT_COMPUTABLE;

	protected $connect_string = 'steam://connect/{IDENTIFIER}'; // same as source
}
