<?php

require_once '../library/Shift8.php';
require_once '../library/Queue/Processor/Mysql.php';

$shift8 = new Shift8('http://127.0.0.1:8088/mxml', 'manager', 'secret');

/**
 * Add the Queue processor - In our example the mysql included processor
 */
$shift8->setQueueProcessor( 
	new Shift8_Queue_Processor_Mysql("localhost", "root", "root", "devel")
);

if( !($queue_id = $shift8->addCommandToQueue('getQueues', array())) ) {
	echo "Unable to add the command to the queue\n";
	return;
}

echo "Added command to queue with id $queue_id\n";
