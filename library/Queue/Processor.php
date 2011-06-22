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
 * Abstract class defining a Shift8 Queue Processor. 
 * The Queue processor is responsible for inserting, retrieving and updating the Shift8 Queue. This has been implemented
 * as a seperate mechanism to allow the Queue to be implemented into various data structures such as Databases (mysql, postgresql, oracle etc),
 * flat based files, or whatever the user requires, whithout having to interfere with the internals of the Shift8 library
 * 
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1
 */
abstract class Shift8_Queue_Processor {
	/**
	 * Function responsible for inserting a new queue item to the Shift8 Queue
	 *
	 * @param Shift8_Queue_Item $item
	 *
	 * @return integer The number of the queue position for this command
	 */
	public abstract function insert( $item );

	/**
	 * Function responsible for updating an existing queued command with command results
	 * 
	 * @param Shift8_Queue_Item $item The newly updated item, retrieved from the remote source
	 *
	 * @return boolean
	 */
	public abstract function update( $item );

	/**
	 * Retrieves the unprocessed commands in the queue
	 * 
	 * @return Shift8_Queue_Item[]
	 */
	public abstract function queue();

	/**
	 * Retrieves the response of a processed command based on the queue id
	 *
	 * @param integer $queue_id The queue id to retrieve information for
	 *
	 * @return Shift8_Event|Shift8_Event[]
	 */
	public abstract function retrieveResponse( $queue_id );
}
