<?php
/**
 * Shift8 provides a mechanism for PHP applications to communicate to a remote Asterisk server
 * via the AJAM XML interface of Asterisk.
 *
 * Function return values and PHPDoc have been constucted in a way that makes Shift8 library to
 * be Soap complaiant (as much as possible anyway)
 *
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
include_once 'Event/Event.php';
include_once 'Event/Filter.php';
include_once 'Event/Filter/Dummy.php';
include_once 'Event/Listener.php';
include_once 'Debug/Listener.php';
include_once 'Exception/Exception.php';
include_once 'Queue/Processor.php';
include_once 'Queue/Item.php';

/**
 * Main class responsible for the entire communications between Asterisk Manager interfaces and
 * a PHP application.
 *
 * @package Shift8
 * @author Paris Stamatopoulos
 * @version 0.1.3
 *
 * @uses Shift8_Event
 * @uses Shift8_Event_Filter
 * @uses Shift8_Event_Filter_Dummy
 * @uses Shift8_Event_Listener
 * @uses Shift8_Debug_Listener
 * @uses Shift8_Exception
 * @uses Shift8_Queue_Item
 * @uses Shift8_Queue_Processor
 *
 */
class Shift8 {
	private	$_cookie;

	private $_manager;
	private $_secret;
	private $_ajam;

	private $_eventListeners;
	private $_debugListeners;

	private $_nonQueableCommands = array( 'WaitEvent', 'Login', 'Logoff', 'Ping' );
	private $_queueProcessor;

	private $_lastError;

	const VERSION = '0.1.3';

	/**
	 * Class constructor.
	 * All variables are optional and can be passed to the Login() call
	 *
	 * @param string $ajam The remote ajam interface.
	 * @param string $manager The username of the manager to login
	 * @param string $secret The password of the manager
	 * @param Shift8_Event_Listener $eventListener Adds an event listener to this instance of the Shift8 with name 'default'
	 * @param Shift8_Debug_Listener $debugListener Adds a debug listener to this instance of the Shift8 with name 'default'
	 *
	 * @see Login
	 */
	public function __construct( $ajam = false, $manager = false, $secret = false, $eventListener = false, $debugListener = false ) {
		$this->_ajam    = $ajam;
		$this->_manager = $manager;
		$this->_secret  = $secret;

		$this->_eventListeners = array();
		$this->_debugListeners = array();

		if( $eventListener ) {
		    $this->addEventListener('default', $eventListener);
		}

		if( $debugListener ) {
		    $this->addDebugListener('default', $debugListener);
		}
	}

	/**
	 * Adds a new event listener to the library. The listener is defined by the name specified. If the name passed
	 * already exists on the system, the listener will not be added.
	 *
	 * @param string $name
	 * @param Shift8_Event_Listener $listener
	 * @param Shift8_Event_Filter $filter
	 *
	 * @throws Shift8_Exception Throws exception if the listener/filter specified is invalid
	 *
	 * @return boolean
	 */
	public function addEventListener( $name, $listener, $filter = false ) {
		if( !is_a($listener, 'Shift8_Event_Listener') )
			throw new Shift8_Exception("Listener specified must be an instance of Shift8_Event_Listener class");

		if( $filter && !is_a($filter, 'Shift8_Event_Filter') )
			throw new Shift8_Exception("Filter specified must be an instance of Shift8_Event_Filter class");

		if( !isset($this->_eventListeners[$name]) ) {
			$this->_eventListeners[$name] = new stdClass();
			$this->_eventListeners[$name]->lObject = $listener;

			if( $filter )
				$this->_eventListeners[$name]->fObject = $filter;
			else
				$this->_eventListeners[$name]->fObject = new Shift8_Event_Filter_Dummy();
		}
		else {
			return false;
		}

		return true;
	}

	/**
	 * Removes an event listener from the library.
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public function removeEventListener( $name ) {
		if( !isset($this->_eventListeners[$name]) )
			return false;

		unset($this->_eventListeners[$name]);
		return true;
	}

	/**
	 * Notify all event listeners when a event has occurred.
	 *
	 * @param Shift8_Event $event The event that has occurred
	 *
	 * @return boolean
	 */
	protected function notifyEventListeners( $event ) {
		if( !is_a($event, 'Shift8_Event') )
			throw new Shift8_Exception("Event specified must be an instance of Shift8_Event class");

		foreach( $this->_eventListeners as &$listener ) {
			$type = $event->get('event') ? $event->get('event') : 'undefined';

			if( $listener->fObject->filter($type) )
				$listener->lObject->notify($event);
		}

		return true;
	}

	/**
	 * Adds a debug listener.
	 *
	 * @param string $name
	 * @param Shift8_Debug_Listener $listener
	 *
	 * @throws Shift8_Exception if the listener specified is not of type Shift8_Debug_Listener
	 * @return boolean
	 */
	public function addDebugListener( $name, $listener ) {
		if( !is_a($listener, 'Shift8_Debug_Listener') )
			throw new Shift8_Exception("Debug Listener specified must be an instance of Shift8_Debug_Listener class");

		if( !isset($this->_debugListeners[$name]) ) {
			$this->_debugListeners[$name] = $listener;
			return true;
		}

		return false;
	}

	/**
	 * Removes a debug listener
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public function removeDebugListener( $name ) {
		if( !isset($this->_debugListeners[$name]) )
			return false;

		unset($this->_debugListeners[$name]);
		return true;
	}

	/**
	 * Send debug messages to all Debug Listeners
	 *
	 * @param mixed $message
	 *
	 * @return boolean
	 */
	protected function notifyDebugListeners( $message ) {
		foreach( $this->_debugListeners as &$listener ) {
			$listener->debug($message);
		}
	}

	/**
	 * Adds a new command to the queue of commands to be executed on the remote asterisk server.
	 *
	 * @param string $command
	 * @param array $arguments
	 *
	 * @throws Shift8_Exception if the command specified cannot be queued.
	 * @return integer Returns the id in the queue
	 */
	public function addCommandToQueue( $command, $arguments ) {
		if( !isset($this->_queueProcessor) )
			throw new Shift8_Exception("You have not specified a queue processor before adding a command to queue");

		if( in_array($command, $this->_nonQueableCommands) )
			throw new Shift8_Exception("Command $command is not allowed to be queued");

		return $this->_queueProcessor->insert(
				new Shift8_Queue_Item(
					array(
						'command'	=> $command,
						'arguments'	=> $arguments
					)
				)
		);
	}

	/**
	 * Processes the commands actively in the Shift8 queue
	 *
	 * @return boolean
	 */
	public function processCommandQueue() {
		if( !isset($this->_queueProcessor) )
			throw new Shift8_Exception("You have not specified a queue processor before processing the queue");

		$queue = $this->_queueProcessor->queue();

		for( $i = 0; $i < count($queue); $i++ ) {
			$item = $queue[$i];

			$response = call_user_func_array( array($this, $item->getCommand()), $item->getRequestArguments() );

			if( $response !== FALSE ) {
				$item->setResponse($response);
				$this->_queueProcessor->update($item);
			}
		}
	}

	/**
	 * Retrieve the result from a queued command
	 *
	 * @param integer $queue_id
	 *
	 * @return Shift8_Event[] or null in case nothing was found
	 */
	public function getQueuedCommandResponse( $queue_id ) {
		if( !isset($this->_queueProcessor) )
			throw new Shift8_Exception("You have not specified a queue processor before accessing the queue");

		return $this->_queueProcessor->retrieveResponse($queue_id);
	}

	/**
	 * Set the active queue processor.
	 * The processor is responsible for inserting, retrieving and updating the Shift8 Command Queue.
	 *
	 * @param Shift8_Queue_Processor $processor
	 *
	 * @throws Shift8_Exception if the processor specified is not of type Shift8_Queue_Processor
	 * @return void
	 */
	public function setQueueProcessor( $processor ) {
		if( !is_a($processor, 'Shift8_Queue_Processor') )
			throw new Shift8_Exception("You need to specify a subclass of Shift8_Queue_Processor");

		$this->_queueProcessor = $processor;
	}

	/**
	 * Returns the active queue processor.
	 *
	 * @return Shift8_Queue_Processor
	 */
	public function getQueueProcessor() {
		return $this->_queueProcessor;
	}

	/**
	 * Disallow a command from being queued
	 *
	 * @param string $command The command to disallow
	 *
	 * @return boolean
	 */
	public function disallowCommandToBeQueued( $command ) {
		if( in_array($this->_nonQueableCommands, $command) )
			return true;

		return array_push($this->_nonQueableCommands, $command);
	}

	/**
	 * Allow command to be queued.
	 *
	 * @param string $command The command to be allowed
	 *
	 * @return boolean
	 */
	public function allowCommandToBeQueued( $command ) {
		if( !($pos = in_array($this->_nonQueableCommands, $command)) )
			return false;

		unset($this->_nonQueableCommands[$pos]);
		return true;
	}

	/**
	 * Adds a new interface in the Queue
	 *
	 * @param string $queue The queue to add the interface to
	 * @param string $interface The interface to add to the queue
	 * @param string $member The member name for this interface
	 * @param int $penalty The penalty for this agent
	 * @param boolean $paused Whether the agent will be paused on login
	 *
	 * @return boolean
	 */
	public function queueAddInterface( $queue, $interface, $member = false, $penalty = 0, $paused = false ) {
		$parameters = array(
			'Action'	=>	'QueueAdd',
			'Queue'		=>	$queue,
			'Interface'	=>	$interface
		);

		if( $member )
			$parameters['MemberName'] = $member;

		if( $penalty )
			$parameters['Penalty'] = $penalty;

		if( $paused )
			$parameters['Paused'] = 1;


		$response = $this->proxy( $this->_ajam, $parameters );

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) )
			return true;

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Remove an interface from the Queue
	 *
	 * @param string $queue The queue to remove the interface from
	 * @param string $interface The interface to remove from the queue
	 *
	 * @return boolean
	 */
	public function queueRemoveInterface( $queue, $interface ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'QueueRemove',
					'Queue'		=>	$queue,
					'Interface'	=>	$interface
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) )
			return true;

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Changes the paused status of an interface.
	 *
	 * @param string $inteface The interface to change the status
	 * @param integer $paused The paused value. 1 for Paused, 0 for Unpaused
	 *
	 * @return boolean
	 */
	protected function changeQueuePaused( $interface, $paused ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'QueuePause',
					'Interface'	=>	$interface,
					'Paused'	=>	$paused
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) )
			return true;

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Performs an agent pause on the interface
	 *
	 * @param string $interface The interface to pause
	 *
	 * @return boolean
	 */
	public function pauseQueueInterface( $interface ) {
		return $this->changeQueuePaused( $interface, 1 );
	}

	/**
	 * Performs an agent un-pause on the interface
	 *
	 * @param string $interface The interface to unpause
	 *
	 * @return boolean
	 */
	public function unpauseQueueInterface( $interface ) {
		return $this->changeQueuePaused( $interface, 0 );
	}

	/**
	 * Retrieves the status from the Queues mechanism. It can retrieve either the status for all the Queues
	 * or the status for a specific queue/queue member
	 *
	 * @param string $queue The queue to retrieve status for. (Optional)
	 * @param string $member The member to retrieve status for. (Optional)
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getQueueStatus( $queue = false, $member = false ) {
		$parameters = array(
			'Action' => 'QueueStatus'
		);

		if( $queue )
			$parameters['Queue'] = $queue;

		if( $member )
			$parameters['Member'] = $member;

		$response = $this->proxy( $this->_ajam, $parameters );

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Retrieves the Queue summary for a specific queue if one has been defined, or for the entire system
	 *
	 * @param string $queue The queue to get the summary for. If not specified the summary for all the queues is returned
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getQueueSummary( $queue = false ) {
		$parameters = array(
			'Action' => 'QueueSummary'
		);

		if( $queue )
			$parameters['Queue'] = $queue;

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Lists agents and their status
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getAgents() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Agents'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Get a queue rule
	 *
	 * @param string $rule The queue rule to get
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getQueueRule( $rule ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'QueueRule',
					'Rule'		=>	$rule
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Sets the Queue Penalty for a member
	 *
	 * @param string $member The queue member to set the penalty
	 * @param string $queue The queue this member
	 * @param integer $penalty The penalty to set
	 *
	 * @return boolean
	 */
	public function setQueueMemberPenalty( $member, $queue, $penalty ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'QueuePenalty',
					'Interface'	=>	$member,
					'Queue'		=>	$queue,
					'Penalty'	=>	$penalty
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}


	/**
	 * Get the queues from the remote asterisk server
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getQueues() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Queues'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Allows you to write your own events into the queue log
	 *
	 * @param string $queue The queue to write the event for
	 * @param integer $unique_id The unique id for the queue log
	 * @param string $interface The interface for the log
	 * @param string $event The actual event that needs to be recorded
	 * @param string $message The message to log in the queue log
	 *
	 * @return boolean
	 */
	public function addQueueLog( $queue, $unique_id, $interface, $event, $message ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'QueueLog',
					'Queue'		=>	$queue,
					'UniqueID'	=>	$unique_id,
					'Interface'	=>	$interface,
					'Event'		=>	$event,
					'Message'	=>	$message
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}


	/**
	 * Get a SIP Peer from the remote asterisk as specified by $peer
	 *
	 * @param string $peer The peer to get information for
	 *
	 * @return Shift8_Event or null if it was unable to retrieve any information
	 */
	public function getSipPeer( $peer ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'sipshowpeer',
					'Peer'		=>	$peer
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) ) {
			$events = $this->processEvents($res);

			if( isset($events[0]) )
				return $events[0];

			return null;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Retrieve the SIP Peers from the remote asterisk server
	 *
	 * @return Shift8_Event[]
	 */
	public function getSipPeers() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'SipPeers'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * @todo Fix SipQualifyPeer
	public function getSipQualifyPeer( $peer ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'SipQualifypeer',
					'Peer'		=>	$peer
				)
		);
	}
	*/

	/**
	 * Plays a dtmf digit on the specified channel
	 *
	 * @param string $dtmf The dtmf digit to play
	 * @param string $channel Channel name to send digit to
	 *
	 * @return boolean
	 */
	public function playDTMF( $dtmf, $channel ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'PlayDTMF',
					'Channel'	=>	$channel,
					'Digit'		=>	$dtmf
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Sends a SIP Notify message to a peer
	 *
	 * @param string $channel The channel to sent the notify
	 *
	 * @return boolean
	 */
	public function sentSIPNotify( $channel ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'SIPnotify',
					'Channel'	=>	$channel
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Retrieves the SIP Registry from the remote Asterisk server
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getSipRegistry() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'SIPshowregistry'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * List All Voicemail User Information
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getVoicemailUsers() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'VoicemailUsersList'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Retrieves the IAX Peers from the remote asterisk server
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getIAXPeers() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'IAXpeers'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Retrieves the IAX Peers from the remote asterisk server
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getIAXPeerList() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'IAXpeerlist'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Retrieve the IAX Net stats
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getIAXNetStats() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'IAXnetstats'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Unpauses monitoring of a channel on which monitoring had previously been paused with PauseMonitor.
	 *
	 * @param string $channel The channel to unpause monitor
	 *
	 * @return boolean
	 */
	public function unpauseMonitor( $channel ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'UnpauseMonitor',
					'Channel'	=>	$channel
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * The 'PauseMonitor' action may be used to temporarily stop the recording of a channel
	 *
	 * @param string $channel The channel to pause monitor
	 *
	 * @return boolean
	 */
	public function pauseMonitor( $channel ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'PauseMonitor',
					'Channel'	=>	$channel
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Change monitoring filename of a channel. Has no effect if the channel is not monitored
	 *
	 * @param string $channel Used to specify the channel to record
	 * @param string $file Is the new name of the file created in the monitor spool directory
	 *
	 * @return boolean
	 */
	public function changeMonitor( $channel, $file ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ChangeMonitor',
					'Channel'	=>	$channel,
					'File'		=>	$file
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Stops monitoring a channel. Has no effect if the channel is not monitored
	 *
	 * @param string $channel The channel to stop monitoring
	 *
	 * @return boolean
	 */
	public function stopMonitor( $channel ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'StopMonitor',
					'Channel'	=>	$channel
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * The 'Monitor' action may be used to record the audio on a specified channel.
	 *
	 * @param string $channel Used to specify the channel to record
	 * @param string $file  Is the name of the file created in the monitor spool directory.  Defaults to the same name as the channel (with slashes replaced with dashes)
	 * @param string $format Is the audio recording format.  Defaults to wav
	 * @param boolean $mix Boolean parameter as to whether to mix the input and output channels together after the recording is finished
	 *
	 * @return boolean
	 */
	public function monitor( $channel, $file = false, $format = false, $mix = false ) {
		$parameters = array(
			'Action'	=>	'Monitor',
			'Channel'	=>	$channel
		);

		if( $file )
			$parameters['File'] = $file;

		if( $format )
			$parameters['Format'] = $format;

		if( $mix )
			$parameters['Mix'] = 1;

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Send a message to a Jabber Channel
	 *
	 * @param string $jabber Client or transport Asterisk uses to connect to JABBER
	 * @param string $screenName User Name to message.
	 * @param string $message Message to be sent to the buddy
	 *
	 * @return boolean
	 */
	public function sendMessageToJabberChannel( $jabber, $screenName, $message ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'JabberSend',
					'Jabber'	=>	$jabber,
					'ScreenName'	=>	$screenName,
					'Message'	=>	$message
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Add a new command to execute by the Async AGI application
	 *
	 * @param string $channel The channel to execute the command at
	 * @param string $command The command to execute
	 * @param string $command_id The command id
	 *
	 * @return boolean
	 */
	public function AGI( $channel, $command, $command_id ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'AGI',
					'Channel'	=>	$channel,
					'Command'	=>	$command,
					'CommandID'	=>	$command_id
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Removes database keytree/values
	 *
	 * @param string $family
	 * @param string|boolean $key
	 *
	 * @return boolean
	 */
	public function DBDelTree( $family, $key = false ) {
		$parameters = array(
			'Action'	=>	'DBDelTree',
			'Family'	=>	$family
		);

		if( $key )
			$parameters['Key'] = $key;

		$response = $this->proxy( $this->_ajam, $parameters );

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Removes database key/value
	 *
	 * @param string $family
	 * @param string $key
	 *
	 * @return boolean
	 */
	public function DBDel( $family, $key ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'DBDel',
					'Family'	=>	$family,
					'Key'		=>	$key
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Gets a database value
	 *
	 * @param string $family
	 * @param string $key
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function DBGet( $family, $key ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'DBGet',
					'Family'	=>	$family,
					'Key'		=>	$key
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Adds / updates a database value
	 *
	 * @param string $family
	 * @param string $key
	 * @param string|boolean $value
	 *
	 * @return boolean
	 */
	public function DBPut( $family, $key, $value = false ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'DBPut',
					'Family'	=>	$family,
					'Key'		=>	$key,
					'Val'		=>	($value) ? $value : ''
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Bridge channels together
	 *
	 * @param string $channelA The first channel to bridge
	 * @param string $channelB The second channel to bridge
	 * @param string|boolean $tone Play a tone to the bridged channels. Not required
	 *
	 * @return boolean
	 */
	public function bridge( $channelA, $channelB, $tone = false ) {
		$parameters = array(
			'Action'	=>	'Bridge',
			'Channel1'	=>	$channelA,
			'Channel2'	=>	$channelB
		);

		if( $tone )
			$parameters['Tone'] = $tone;

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Park a channel
	 *
	 * @param string $channelA
	 * @param string $channelB
	 * @param integer|boolean $timeout
	 *
	 * @return boolean
	 */
	public function park( $channelA, $channelB, $timeout = false ) {
		$parameters = array(
			'Action'	=>	'Bridge',
			'Channel'	=>	$channelA,
			'Channel2'	=>	$channelB
		);

		if( $timeout )
			$parameters['Timeout'] = $timeout;

		$response = $this->proxy( $this->_ajam, $parameters );

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * List parked calls
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getParkedCalls() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ParkedCalls'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Show dialplan extensions
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getDialplan() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ShowDialPlan'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Checks if Asterisk module is loaded
	 *
	 * @param string $module Asterisk module name (not including extension)
	 *
	 * @return boolean
	 */
	public function isModuleLoaded( $module ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ModuleCheck',
					'Module'	=>	$module
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Loads, unloads or reloads an Asterisk module in a running system.
	 * If no module is specified for a reload loadtype, all modules are reloaded
	 *
	 * @param boolean|string $module Asterisk module name (not including extension) or subsystem identifier: cdr, enum, dnsmgr, extconfig, manager, rtp, http
	 * @param string $loadType load | unload | reload The operation to be done on module
	 *
	 * @return boolean
	 */
	public function loadModule( $module = false, $loadType = 'reload' ) {
		$parameters = array(
			'Action'	=>	'ModuleLoad',
			'LoadType'	=>	$loadType
		);

		if( $loadType != 'reload' && !$module )
			return false;

		if( $module )
			$parameters['Module'] = $module;

		$response = $this->proxy( $this->_ajam, $parameters );

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * List currently defined channels and some information about them.
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getActiveChannels() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'CoreShowChannels'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Send a reload event. Works the same as sending a ModuleLoad event (reload) without specifying
	 * any modules
	 *
	 * @return boolean
	 */
	public function reload() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Reload'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Show PBX core status information
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getCoreStatusVariables() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'CoreStatus'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Show PBX core settings information
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getCoreSettings() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'CoreSettings'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Send an event to manager sessions
	 *
	 * @param string $userEvent Event string to send
	 *
	 * @todo This might need something more. Header1-N handling
	 *
	 * @return boolean
	 */
	public function sendUserEvent( $userEvent ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'UserEvent',
					'UserEvent'	=>	$userEvent
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * An UpdateConfig action will modify, create, or delete configuration elements in Asterisk configuration files.
	 *
	 * @todo Not yet implemented
	 * @return boolean
	public function updateConfig() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'UpdateConfig'
				)
		);

		print_r($response->asXML());
	}
	*/

	/**
	 * Sends A Text Message while in a call
	 *
	 * @param string $channel Channel to send message to
	 * @param string $message Message to send
	 *
	 * @return boolean
	 */
	public function sendText( $channel, $message ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'SendText',
					'Channel'	=>	$channel,
					'Message'	=>	$message
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Returns the action name and synopsis for every action that is available to the use
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function listCommands() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ListCommands'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Checks a voicemail account for new messages.
	 *
	 * @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
	 *
	 * @todo - Fix this to return a number
	 *
	 * @return integer
	 */
	public function getMailboxCount( $mailbox ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'MailboxCount',
					'Mailbox'	=>	$mailbox
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return 0;
	}

	/**
	 * Checks a voicemail account for status
	 *
	 * @param string $mailbox Full mailbox ID <mailbox>@<vm-context>
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getMailboxStatus( $mailbox ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'MailboxStatus',
					'Mailbox'	=>	$mailbox
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Hangup a channel after a certain time.
	 *
	 * @param string $channel Channel name to hangup
	 * @param integer $timeout Maximum duration of the call (sec)
	 *
	 * @return boolean
	 */
	public function setAbsoluteTimeout( $channel, $timeout ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'AbsoluteTimeout'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Report the extension state for given extension. If the extension has a hint, will use devicestate to check
	 * the status of the device connected to the extension.
	 *
	 * @param string $exten Extension to check state on
	 * @param string $context Context for extension
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getExtensionState( $exten, $context ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ExtensionState',
					'Exten'		=>	$exten,
					'Context'	=>	$context
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Run a CLI command
	 *
	 * @param string $command Asterisk CLI command to run
	 *
	 * @return Shift8_Event[]
	 */
	public function executeCommand( $command ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Command',
					'Command'	=>	$command
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Generates an outgoing call to a Extension/Context/Priority or Application/Data
	 *
	 * @param string $channel Channel name to call
	 * @param string $context Context to use (requires 'Exten' and 'Priority')
	 * @param string $exten Extension to use (requires 'Context' and 'Priority')
	 * @param string $priority Priority to use (requires 'Exten' and 'Context')
	 * @param string $application Application to use
	 * @param string $data Data to use (requires 'Application')
	 * @param string $timeout How long to wait for call to be answered (in ms. Default: 30000)
	 * @param string $callerID Caller ID to be set on the outgoing channel
	 * @param string $variable Channel variable to set, multiple Variable: headers are allowed
	 * @param string $account Account code
	 * @param string $async Set to 'true' for fast origination
	 * @param string $codecs The codecs to use
	 *
	 * @return boolean
	 */
	public function originate( $channel, $context = false, $exten = false, $priority = false, $application = false, $data = false, $timeout = 30000, $callerID = false, $variable = false, $account = false, $async = true, $codecs = false ) { 
		if( $exten && (!$context || !$priority) )
			return false;

		if( $context && (!$exten || !$priority) )
			return false;

		if( $priority && (!$exten || !$context) )
			return false;

		if( $data && !$application )
			return false;

		$parameters = array(
			'Action'	=>	'Originate',
			'Channel'	=>	$channel
		);

		if( $exten )
			$parameters['Exten'] = $exten;

		if( $context )
			$parameters['Context'] = $context;

		if( $priority )
			$parameters['Priority'] = $priority;

		if( $application )
			$parameters['Application'] = $application;

		if( $data )
			$parameters['Data'] = $data;

		if( $timeout )
			$parameters['Timeout'] = $timeout;

		if( $callerID )
			$parameters['CallerID'] = $callerID;

		if( $variable )
			$parameters['Variable'] = $variable;

		if( $account )
			$parameters['Account'] = $account;

		if( $async )
			$parameters['Async'] = 'true';
		else
			$parameters['Async'] = 'false';

		if( $codecs )
			$parameters['Codecs'] = $codecs;

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Provides a wrapper around Originate to provide a simple call generation between two numbers
	 *
	 * @todo Not yet implemented
	 * @return boolean
	public function clickToCall( $numberA, $numberB ) {
		//return $this->originate();
	}
         */

	/**
	 * Attended transfer
	 *
	 * @param string $channel
	 * @param string $exten
	 * @param string $context
	 * @param integer $priority
	 *
	 * @return boolean
	 */
	public function attendedTransfer( $channel, $exten, $context, $priority ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Atxfer',
					'Channel'	=>	$channel,
					'Exten'		=>	$exten,
					'Context'	=>	$context,
					'Priority'	=>	$priority
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Synonymous for redirect().
	 *
	 * @see redirect
	 */
	public function transfer( $channel, $extraChannel = false, $exten, $context, $priority ) {
		return $this->redirect($channel, $extraChannel, $exten, $context, $priority);
	}

	/**
	 * Redirect (transfer) a call
	 *
	 * @param string $channel Channel to redirect
	 * @param string $extraChannel Second call leg to transfer (optional)
	 * @param string $exten Extension to transfer to
	 * @param string $context Context to transfer to
	 * @param integer $priority Priority to transfer to
	 *
	 * @return boolean
	 */
	public function redirect( $channel, $extraChannel = false, $exten, $context, $priority ) {
		$parameters = array(
			'Action'	=>	'Redirect',
			'Channel'	=>	$channel,
			'Exten'		=>	$exten,
			'Context'	=>	$context,
			'Priority'	=>	$priority
		);

		if( $extraChannel )
			$parameters['ExtraChannel'] = $extraChannel;

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * A 'ListCategories' action will dump the categories in a given file.
	 *
	 * @param string $filename The filename to dump the categories from
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function listCategories( $filename ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ListCategories',
					'Filename'	=>	$filename
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * A 'CreateConfig' action will create an empty file in the configuration directory.
	 * This action is intended to be used before an UpdateConfig action.
	 *
	 * @param string $filename The filename to create
	 *
	 * @return boolean
	 */
	public function createConfigurationFile( $filename ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'CreateConfig',
					'Filename'	=>	$filename
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Lists channel status along with requested channel vars
	 *
	 * @param string $channel Name of the channel to query for status
	 * @param string $variables Comma ',' separated list of variables to include
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getStatus( $channel = false, $variables = false ) {
		$parameters = array(
			'Action'	=>	'Status'
		);

		if( $channel )
			$parameters['Channel'] = $channel;

		if( $variables )
			$parameters['Variables'] = $variables;

		$response = $this->proxy( $this->_ajam, $parameters );

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * A 'GetConfigJSON' action will dump the contents of a configuration file by category and contents in JSON format.
	 * This only makes sense to be used using rawman over the HTTP interface.
	 *
	 * @param string $filename Configuration filename (e.g. foo.conf)
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getConfigJson( $filename ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'GetConfigJSON',
					'Filename'	=>	$filename
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * A 'GetConfig' action will dump the contents of a configuration file by category and contents or optionally by specified category only
	 *
	 * @param string $filename Configuration filename (e.g. foo.conf)
	 * @param string $category Category in configuration file
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getConfig( $filename, $category = false ) {
		$parameters = array(
			'Action'	=>	'GetConfig',
			'Filename'	=>	$filename
		);

		if( $category )
			$parameters['Category'] = $category;

		$response = $this->proxy($this->_ajam,$parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Get the value of a global or local channel variable
	 *
	 * @param string $variable Variable name
	 * @param string $channel Channel to read variable from
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function getChannelVariable( $variable, $channel = false ) {
		$parameters = array(
			'Action'	=>	'GetVar',
			'Variable'	=>	$variable
		);

		if( $channel )
			$parameters['Channel'] = $channel;

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Get the value of a global or local channel variable
	 *
	 * @param string $variable Variable name
	 * @param string $value Value
	 * @param string $channel Channel to read variable from
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function setChannelVariable( $variable, $value, $channel = false ) {
		$parameters = array(
			'Action'	=>	'Setvar',
			'Variable'	=>	$variable,
			'Value'		=>	$value
		);

		$response = $this->proxy($this->_ajam, $parameters);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Hangup a channel
	 *
	 * @param string $channel The channel name to be hungup
	 *
	 * @return boolean
	 */
	public function hangup( $channel ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Hangup',
					'Channel'	=>	$channel
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Generate Challenge for MD5 Auth
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function challenge() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Challenge',
					'AuthType'	=>	'MD5'
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Enable/Disable sending of events to this manager client.
	 *
	 * @param string $eventMask The event mask to apply to this manager client
	 *
	 * @return boolean
	 */
	public function events( $eventMask ) {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'Events',
					'EventMask'	=>	$eventMask
				)
		);

		if( $response->xpath("/ajax-response/response/generic[@response='Success']") ) {
			return true;
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Logins to the remote asterisk server
	 * All arguments are optional and can be defined per instance on the class instanciation
	 *
	 * @param string $ajam The remote ajam interface.
	 * @param string $manager The username of the manager to login
	 * @param string $secret The password of the manager
	 *
	 * @throws Shift8_Exception is thrown when no credentials have not been specified on the class instanciation and no credentials where specified here.
	 * @return boolean
	 */
	public function login( $ajam = false, $manager = false, $secret = false ) {
		if( (!$this->_ajam && !$ajam) || (!$this->_manager && !$manager) || (!$this->_secret && !$secret) )
			throw new Shift8_Exception("No connection credentials have been found. Please read the Shift8 documentation");

		if( $ajam )
			$this->_ajam = $ajam;

		if( $manager )
			$this->_manager = $manager;

		if( $secret )
			$this->_secret = $secret;

		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'login',
					'Username'	=>	$this->_manager,
					'Secret'	=>	$this->_secret
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) )
			return true;

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Logs off from the remote asterisk server
	 *
	 * @return boolean
	 */
	public function logoff() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'logoff'
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Goodbye']")) )
			return true;

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Pings the remote asterisk server. Keeps the remote connection alive
	 *
	 * @return boolean
	 */
	public function ping() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'ping'
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) ) 
			return true;

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return false;
	}

	/**
	 * Send a WaitEvent command to remote asterisk and wait for any incoming events
	 *
	 * @return Shift8_Event[] or null if it was unable to retrieve any information
	 */
	public function waitEvent() {
		$response = $this->proxy(
				$this->_ajam,
				array(
					'Action'	=>	'WaitEvent'
				)
		);

		if( ($res = $response->xpath("/ajax-response/response/generic[@response='Success']")) ) {
			return $this->processEvents($response->xpath("/ajax-response/response/generic[@event]"));
		}

		$this->setLastError($response->xpath("/ajax-response/response/generic[@response='Error']"));
		return null;
	}

	/**
	 * Handles the processing of the return events by the asterisk server creating PHP objects for each one of them
	 *
	 * @param SimpleXMLElement $events
	 *
	 * @return Shift8_Event[]
	 */
	public function processEvents( $events ) {
		$_events = array();

		for( $c = 0; $c < count($events); $c++ ) {
			$_event = new Shift8_Event();

			foreach($events[$c]->attributes() as $key => $value) {
				$_event->set($key, (string) $value);
			}

			$this->notifyEventListeners($_event);
			$_events[] = $_event;
		}

		return $_events;
	}

	/**
	 * Handles all the proxying of requests to the remote asterisk server.
	 *
	 * @param string $url
	 * @param array $parameters
	 *
	 * @return SimpleXMLElement
	 */
        protected function proxy( $url, $parameters ) {
		if( is_array($parameters) && !empty($parameters) ) {
			$getArguments = "?";

			while (list($key, $value) = each($parameters)) {
				$getArguments .= $key . "=" . urlencode($value) . "&";
			}

			$getArguments = substr($getArguments, 0, strlen($getArguments)-1);
		}
		else {
			$getArguments = "";
		}
		$url = $url . $getArguments;

		$_curl = curl_init();

		if( $this->_cookie )
			curl_setopt($_curl, CURLOPT_COOKIE, $this->_cookie);

		curl_setopt($_curl, CURLOPT_URL, $url);
		curl_setopt($_curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($_curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($_curl, CURLOPT_HEADER, 1);

		$output = curl_exec($_curl);
		$header_size = curl_getinfo($_curl, CURLINFO_HEADER_SIZE);

		curl_close($_curl);

		if( !$this->_cookie ) {
			preg_match('/^Set-Cookie: (.*?);/m', $output, $cookies);

			if( isset($cookies[1]) ) {
				$this->_cookie = $cookies[1];
			}
		}

		$response = substr($output, $header_size);

		$this->notifyDebugListeners(
			array(
				'url'		=> $url,
				'parameters'	=> $parameters,
				'response'	=> $response,
				'cookie'	=> @$cookies[1]
			)
		);

		if( !($xml = @simplexml_load_string($response)) ) {
			return new SimpleXMLElement("<ajax-response><response type='object' id='unknown'><generic response='Error' message='Unable to connect to remote asterisk server' /></response></ajax-response>");
		}

		return($xml);
	}

	/**
	 * Retrieves the asterisk cookie.
	 * This can be used to set the cookie value to a PHP session so as to establish a permanent connection between a web application and the
	 * Asterisk AJAM interface. Don't forget to close the session since php sessions are locking
	 *
	 * @return string
	 */
	public function getCookie() {
		return $this->_cookie;
	}

	/**
	 * Sets the cookie to be used for the connection with the remote asterisk server.
	 *
	 * @param string $cookie The cookie from an already established connection to a remote asterisk server
	 *
	 * @return void
	 */
	public function setCookie( $cookie ) {
		$this->_cookie = $cookie;
	}

	/**
	 * Returns the version of the Shift8 library
	 *
	 * @return string
	 */
	public static function getVersion() {
		return self::VERSION;
	}

	/**
	 * Compare the specified Shift8 version string $version
	 * with the current version of Shift8. Function taken from Zend Framework
	 *
	 * @param  string  $version  A version string (e.g. "0.7.1").
	 *
	 * @return boolean           -1 if the $version is older, 0 if they are the same and +1 if $version is newer.
	 */
	public static function compareVersion($version) {
		$version = strtolower($version);
		$version = preg_replace('/(\d)pr(\d?)/', '$1a$2', $version);
		return version_compare($version, strtolower(self::VERSION));
	}

	/**
	 * Sets the last error message
	 *
	 * @param SimpleXMLElement $message The last message occurred
	 */
	protected function setLastError( $message ) {
		$this->_lastError = @$message[0]->attributes()->message;
	}

	/**
	 * Returns the last error that has occurred in the communication with the remote asterisk
	 *
	 * @return string
	 */
	public function getLastError() {
		return $this->_lastError;
	}
}

