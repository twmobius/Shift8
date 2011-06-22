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
 * Generic Shift8 Event. Class is being instanciated and fed all the informations retrieved from a 
 * Asterisk Manager event
 *
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1
 */
class Shift8_Event {
	/**
	 * Normally this should have been specified as private/protected, however it has been declared public to be
	 * viewed via Soap requests 
	 *
	 * @var array $variables
	 */
	public $variables;

	/**
	 * Generic setter function to retrieve all the variables from the
	 * asterisk event. In case of duplicate the later will be ignored
	 * 
	 * @param string $variable The variable name retrieved for the event
	 * @param string $value The value that this variable has
	 *
	 * @return boolean
	 */
	public function set( $variable, $value ) {
		if( isset($this->variables[$variable]) ) 
			return false;

		$this->variables[$variable] = $value;
		return true;
	}

	/**
	 * Generic getter function to retrieve a specific variable. If the variable
	 * is not set, boolean false will be returned
	 *
	 * @param string $variable The variable name retrieved for the event
	 *
	 * @return string or null if nothing was found
	 */
	public function get( $variable ) {
		if( !isset($this->variables[$variable]) )
			return null;

		return $this->variables[$variable];
	}

	/**
	 * Class overloading to retrieve the event parameters directly
	 *
	 * @param string $variable
	 *	
	 * @return string
	 */
	public function __get( $variable ) {
		return $this->get($variable);
	}

	/**
	 * Class overloading to set a variable directly into the object
	 *
	 * @param string $variable
	 * @param string $value
	 *
	 * @return boolean
	 */	 
	public function __set( $variable, $value ) {
		return $this->set($variable, $value);
	}

	/**
	 * Returns all the variables stored in the Event as an associative array
	 *
	 * @return array
	 */
	public function getVariables() {
		return $this->variables;
	}

	/**
	 * Sets the internal variables array.
	 *
	 * @param array $variables The variables to set to this object
	 *
	 * @return void
	 */
	public function setVariables( $variables ) {
		$this->variables = $variables;
	}

	/*
	public function __sleep() {
		return array_keys( get_object_vars( $this ) );
	}
	*/

	public function asXml() {
		$xml = '<shift8_event>';
		
		while (list($key, $value) = each($this->variables))
			$xml .= "<$key>$value</$key>";
	
		$xml .= '</shift8_event>';

		return $xml;
	}
}
