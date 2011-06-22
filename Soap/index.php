<?php
/**
 * Simple page that exposed the Shift8 library over SOAP. Uses the Zend_Soap functionality to
 * AutoDiscover the WSDL as well as establishing the Server.
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
 * @package Shift8
 */

$config['library']  = '/path/to/Zend/library';				// The path for the Zend Framework
$config['asterisk'] = 'http://127.0.0.1:8088/mxml';			// The remote asterisk manager (AJAM) interface
$config['manager']  = 'manager';					// The manager username
$config['secret']   = 'secret';						// The password

set_include_path($config['library'] . PATH_SEPARATOR . get_include_path());

include_once 'Zend/Loader/Autoloader.php';

$loader = Zend_Loader_Autoloader::getInstance();
$loader->setFallbackAutoloader(true);
$loader->suppressNotFoundWarnings(false);

require_once "Zend/Soap/Wsdl.php";
require_once "Zend/Soap/Server.php";
require_once "Zend/Soap/AutoDiscover.php";

require_once '../library/Shift8.php';

/**
 * While trying to develop the Soap extension for Shift8, I needed a way to debug
 * the events occuring to the remote asterisk, thus the Debug Listener and the 
 * Syslog debug listener
 */
require_once '../library/Debug/Listener/Syslog.php';

if( isset($_GET['wsdl']) ) {
	$autodiscover = new Zend_Soap_AutoDiscover('Zend_Soap_Wsdl_Strategy_ArrayOfTypeComplex');

	$autodiscover->setOperationBodyStyle(
		array(
			'use' 		=> 'literal',
			'namespace' 	=> 'http://' . $_SERVER['HTTP_HOST']
		)
	);

	/*	
	 * Does not work with PHP Soap Client. Might be required for .NET clients
	 *
	$autodiscover->setBindingStyle(
		array(
			'style'		=> 'document',
			'transport' 	=> 'http://schemas.xmlsoap.org/soap/http'
		)
	);
	*/	

	$autodiscover->setClass('Shift8');
	$autodiscover->handle();
} else {
	session_start();
	
	$wsdl = sprintf('http://%s%s?wsdl', $_SERVER['HTTP_HOST'], $_SERVER['SCRIPT_NAME']);
	$soap = new Zend_Soap_Server($wsdl);

	$soap->setClass('Shift8', $config['asterisk'], $config['manager'], $config['secret'], false, new Shift8_Debug_Listener_Syslog());
	$soap->setPersistence(SOAP_PERSISTENCE_SESSION);
	$soap->registerFaultException('Shift8_Exception');

	$response = $soap->handle();	
}
