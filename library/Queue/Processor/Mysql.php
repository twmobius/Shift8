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
 * Provides a simple queuing mechanism using Mysql to store the actual Shift8 Queue.
 *
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1
 */
class Shift8_Queue_Processor_Mysql extends Shift8_Queue_Processor {
	private $_conn;

	/**
	 * Class constructor.
	 *
	 * @param string $hostname The remote mysql hostname
	 * @param string $username The username to connect with
	 * @param string $password The password to connect with
	 * @param string $database The database to use
	 * 
	 * @throws Shift8_Execption if it was unable to connect to remote database
	 */
	public function __construct( $hostname, $username, $password, $database ) {
		if( !($this->_conn = @mysql_connect($hostname, $username, $password)) )
			throw new Shift8_Exception("Unable to connect to the remote mysql server");

		if( !@mysql_select_db($database) )
			throw new Shift8_Exception("Unable to select the mysql database");
	}

	public function insert( $item ) {
		if( !($result = @mysql_query("
			INSERT INTO Shift8_Queue (queue_id, command, arguments, date_added) VALUES (
				NULL,
				'" . addslashes($item->getCommand()) . "',
				'" . addslashes($item->getRequestArguments(true)) . "',
				'" . date("Y-d-m h:s:i") . "'
			)
		")) ) {
			return false;
		}

		return mysql_insert_id();
	}

        public function update( $item ) { 
		if( !($result = @mysql_query("
			UPDATE Shift8_Queue SET
				command = 	'" . addslashes($item->getCommand()) . "',
				arguments = 	'" . addslashes($item->getRequestArguments()) . "',
				response =	'" . addslashes($item->getResponse(true)) . "',
				date_executed =	'" . date("Y-d-m h:s:i") . "'
			WHERE queue_id = '" . addslashes($item->getQueueId()) . "'
		")) ) {
			return false;
		}

		return true;		
	}

	public function retrieveResponse( $queue_id ) {
		if( !($result = @mysql_query("SELECT * FROM Shift8_Queue WHERE queue_id = '" . addslashes($queue_id) . "' LIMIT 1")) ) {
			return false;
		}

		$item = new Shift8_Queue_Item(mysql_fetch_assoc($result));

		return $item->getResponse();
	}

        public function queue() {
		if( !($result = @mysql_query("SELECT * FROM Shift8_Queue # WHERE response IS NULL")) ) {
			return array();
		}

		$queue = array();

		while( $data = mysql_fetch_assoc($result) ) {
			array_push($queue, new Shift8_Queue_Item($data));
		}

		return $queue;
	}
}
