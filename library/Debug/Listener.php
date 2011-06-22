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
 * Provides a simple abstract function that defines how a Debug Listener must be constructed. 
 *
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1
 */
abstract class Shift8_Debug_Listener {
	/**
	 * Provides a simple mechanism for debuging the Shift8 library
	 *
	 * @param mixed $message. Usually $message is an array containg the AJAM url, the parameters in which it was invoked, as well as the actual XML response from the remove server.
	 *	
	 * @return void
	 */
	public abstract function debug( $message );
}
