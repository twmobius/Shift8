<?php
/**
 * This file is part of Shift8.
 *
 * Shift8 is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Shift8 is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Shift8.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Dummy event listener to process all the events to the Event_Listener
 *
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1
 */
class Shift8_Event_Filter_Dummy extends Shift8_Event_Filter {
	public function filter( $event ) {
		return true;
	}
}
