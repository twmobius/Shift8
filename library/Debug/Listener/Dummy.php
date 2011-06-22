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
 * Provides a dummy debug listener that simply print_r()s the message
 * retrieved from the Shift8 library
 *
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1
 */
class Shift8_Debug_Listener_Dummy extends Shift8_Debug_Listener {
	public function debug( $message ) {
		echo "[Debug] Request in url: " . $message['url'] . "\n"
		    ."[Debug] Request parameters: \n";

		print_r($message['parameters']);
	
		echo "[Debug] Response:\n" . $message['response'] . "\n";
		
		echo "[Debug] Cookie value:" . $message['cookie'] . "\n\n";
	}
}
