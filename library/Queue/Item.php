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
 * Simple object that describes all the elements that a queue item can have. 
 * It is up to the processor to decide which ones to actually use. One could extend this to support more items
 *
 * @author Paris Stamatopoulos
 * @version 0.1
 * @package Shift8
 */
class Shift8_Queue_Item {
	private $_queue_id;
	private $_command;
	private $_requestArguments;
	private $_response;
	private $_dateAdded;
	private $_dateExecuted;

	/**
	 * Class constructor
	 *
	 * @param array $data
	 */
	public function __construct( $data = false ) {
		if( isset($data['queue_id']) )
			$this->setQueueId($data['queue_id']);
		if( isset($data['command']) )
			$this->setCommand($data['command']);
		if( isset($data['arguments']) )
			$this->setRequestArguments($data['arguments']);
		if( isset($data['response']) )
			$this->setResponse($data['response']);
		if( isset($data['date_added']) )
			$this->setDateAdded($data['date_added']);
		if( isset($data['date_executed']) )
			$this->setDateExecuted($data['date_executed']);
	}

	/**
	 * Returns the id of the current item in the queue
	 *
	 * @return integer
	 */
	public function getQueueId() {
		return $this->_queue_id;
	}
	
	/**
	 * Sets the id of the current item in the queue
	 *
	 * @param integer $queue_id
	 *
	 * @return void
	 */
	public function setQueueId( $queue_id ) {
		$this->_queue_id = $queue_id;
	}
	
	/**
	 * Returns the command of the Queue Item
	 *
	 * @return string
	 */
	public function getCommand() {
		return $this->_command;
	}

	/**
	 * Sets the command for this queue item
	 *
	 * @param string $command
	 * 
	 * @return void
	 */
	public function setCommand( $command ) {
		$this->_command = $command;
	}

	/**
	 * Sets the request arguments for this command in this queue item
	 *
	 * @param string[] $arguments
	 * 
	 * @return void
	 */
	public function setRequestArguments( $arguments ) {
		$this->_requestArguments = serialize($arguments);
	}

	/**
	 * Gets the request arguments for this command in this queue item
	 *
	 * @param boolean $serialized Whether to return the data serialized or not
	 *
	 * @return string[]
	 */
	public function getRequestArguments( $serialized = false ) {
		if( $serialized ) 
			return $this->_requestArguments;
		else
			return unserialize($this->_requestArguments);
	}

	/**
	 * Sets the response for this queue item
	 * 
	 * @param Shift8_Event|Shift8_Event[]
	 */
	public function setResponse( $response ) {
		$this->_response = serialize($response);
	}

	/**
	 * Gets the response in Shift8_Event objects 
	 *
	 * @param boolean $serialized Whether to return the data serialized or not
	 *
	 * @return Shift8_Event[]
	 */
	public function getResponse( $serialized = false ) {
		if( $serialized )
			return $this->_response;
		else
			return unserialize($this->_response);
	}

	/**
	 * Gets the date when this command was added
	 *
	 * @return dateTime
	 */
	public function getDateAdded() {
		return $this->_dateAdded;
	}

	/**
	 * Sets the date when this command was added to the queue
	 *
	 * @param dateTime $date
	 *
	 * @return void
	 */
	public function setDateAdded( $date ) {
		$this->_dateAdded = $date;
	}

	/**
	 * Gets the date when this command was executed
	 *
	 * @return dateTime
	 */
	public function getDateExecuted() {
		return $this->_dateExecuted;
	}

	/**
	 * Sets the date when this command was executed
	 *
	 * @param dateTime $date
	 *
	 * @return void
	 */
	public function setDateExecuted( $date ) {
		$this->_dateExecuted = $date;
	}
}
