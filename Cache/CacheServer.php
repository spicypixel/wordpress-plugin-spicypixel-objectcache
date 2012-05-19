<?php

// namespace SpicyPixel;
//
// PHP < 5.3 doesn't support namespaces, so underscore to the rescue.

// TODO: Fix this
date_default_timezone_set('America/Los_Angeles');

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__) . '/..'));
require_once(SPOC_DIR . '/Cache/MemoryCache.php');
require_once(SPOC_DIR . '/Sockets/BlobMessageSerializer.php');

class SpicyPixel_CacheServer 
{
	const DEBUG = false;
	const DEFAULT_ADDRESS = '127.0.0.1'; // localhost only connections
	const DEFAULT_PORT = 10840;
	const LOG_FILE = '/tmp/CacheServer.log';

	// The cache to use
	private $cache;
	
	// Waiting readers are clients the server is waiting to read from
	private $waitingReaders = array();
	
	// Waiting writers are clients the server is waiting to write to
	private $waitingWriters = array();
	
	// Messages waiting to be delivered
	private $messages = array();
	
	// Address to listen on
	private $address;
	
	// Port to listen on
	private $port;
	
	// Server socket
	private $serverSocket;
	
	// Variable to control shutdown
	private $shutdownSignal = false;
	
	public function __construct($address = self::DEFAULT_ADDRESS, $port = self::DEFAULT_PORT) {
		$this->address = $address;
		$this->port = $port;
		$this->cache = new SpicyPixel_MemoryCache();
	}
	
	private function createSocket()
	{
		if(self::DEBUG)
			$this->writeLog("createSocket(): create\n");
		
		$this->serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($this->serverSocket === false)
			SpicyPixel_SocketUtility::throwLastSocketException();
		
		if(self::DEBUG)
			$this->writeLog("createSocket(): bind\n");
		
		if(!socket_bind($this->serverSocket, $this->address, $this->port))
			SpicyPixel_SocketUtility::throwLastSocketException();
		
		if(self::DEBUG)
			$this->writeLog("createSocket(): listen\n");
		
		if(!socket_listen($this->serverSocket))
			SpicyPixel_SocketUtility::throwLastSocketException();
		
		if(self::DEBUG)
			$this->writeLog("createSocket(): set_block\n");
		
		if(!socket_set_block($this->serverSocket))
			SpicyPixel_SocketUtility::throwLastSocketException();
		
		$this->waitingReaders[] = &$this->serverSocket;
		
		if(self::DEBUG)
			$this->writeLog("createSocket(): complete\n");
	}
	
	private function writeLog($message) {
		$msg = "[" . date("Y-m-d H:i:s") . "] " . $message;
		echo $msg;
		//error_log($msg, 3, self::LOG_FILE);
	}
	
	// The main (blocking) run loop for the server
	// $lifetime: How long to run the loop
	// $inactiveLifetime: How long to run the loop after being inactive
	public function listen($lifetime = 3600, $inactiveLifetime = 600) 
	{
		if(self::DEBUG)
			$this->writeLog("listen(): Creating server socket\n");
		
		// Setup the socket and listen
		$this->createSocket();
		
		$timeStop = time() + $lifetime; // Only run the loop until the stop time
		$lastActivity = time(); // To check if we've been inactive too long

		if(self::DEBUG)
			$this->writeLog("listen(): Starting main loop()\n");
		
		// Main loop
		while(time() < $timeStop && (time() - $lastActivity) < $inactiveLifetime)
		{
			$timeLeft = min($timeStop - time(), 10); // timeout in 10 second increments
			$timeLeft = min($timeLeft, $inactiveLifetime - (time() - $lastActivity));
			$readyReaders = $this->waitingReaders; // copy
			$readyWriters = $this->waitingWriters; // copy
			if(self::DEBUG)
				$this->writeLog("listen(): Socket select\n");
			$selectResult = socket_select($readyReaders, $readyWriters, $except = null, $timeLeft);
			if($selectResult === false)
			{
				if(self::DEBUG)
					$this->writeLog("listen(): Select failed\n");
				SpicyPixel_SocketUtility::throwLastSocketException();
			} 
			else if ($selectResult > 0 || !empty($readyReaders) || !empty($readyWriters)) 
			{
				if(self::DEBUG)
					$this->writeLog("listen(): Select returned ready sockets: $selectResult\n");
				
				// process
				if(self::DEBUG)
					$this->writeLog("listen(): Process readers\n");
					
				$this->processReaders($readyReaders);
				
				if(self::DEBUG)
					$this->writeLog("listen(): Process writers\n");
					
				$this->processWriters($readyWriters);
				
				if(self::DEBUG)
					$this->writeLog("listen(): Finished processing ready sockets\n");
			} 
			else if ($selectResult == 0) 
			{
				// timeout expired
				if(self::DEBUG)
					$this->writeLog("listen(): Select timeout expired after $timeLeft seconds\n");
				continue;
			} 
			else 
			{
				if(self::DEBUG) {
					$this->writeLog("listen(): Select returned an unexpected value: $selectResult\n");
					var_dump($selectResult);
				}
				SpicyPixel_SocketUtility::throwLastSocketException();
			}
			if($this->shutdownSignal) {
				if(self::DEBUG)
					$this->writeLog("listen(): Received shutdown signal\n");
				break;
			}
		}
		
		if(self::DEBUG)
			$this->writeLog("listen(): Finished according to set timeout\n");
		SpicyPixel_SocketUtility::friendlyCloseSocket($this->serverSocket);
	}
	
	private function processReaders(&$readyReaders) 
	{
		if(self::DEBUG) {
			$this->writeLog("processReaders(): begin\n");
			var_dump($readyReaders);
		}
			
		// Process all waiting readers
		foreach($readyReaders as &$reader)
		{
			// Accept waiting to connect clients on the server socket
			if($reader === $this->serverSocket)
			{
				if(self::DEBUG)
					$this->writeLog("processReaders(): accepting client\n");
				$client = socket_accept($this->serverSocket);
				$this->waitingReaders[] = &$client;
				if(self::DEBUG)
					$this->writeLog("processReaders(): accept complete\n");
				continue;
			}
			
			// Read the request
			$request = null;
			try
			{
				if(self::DEBUG)
					$this->writeLog("processReaders(): reading request start\n");

				$request = $this->readRequestMessage($reader);
				
				if(self::DEBUG)
					$this->writeLog("processReaders(): reading request complete\n");
			}
			catch(Exception $ex)
			{
				$request = null;
				if(self::DEBUG)
					$this->writeLog("Error reading socket: " . $ex);
			}
			
			if(empty($request)) {
				// Something bad happened so close the channel and abandon the reader
				SpicyPixel_SocketUtility::friendlyCloseSocket($reader);
				$key = array_search(&$reader, $this->waitingReaders);
				unset($this->waitingReaders[$key]);
				continue;
			}
			
			// Prepare the response
			if(self::DEBUG)
				$this->writeLog("processReaders(): handleRequest start\n");
				
			$message = array();
			$message['request'] = $request;
			$message['response'] = $this->handleRequest($reader, $request);

			if(self::DEBUG)
				$this->writeLog("processReaders(): handleRequest complete\n");
			
			// Queue the response
			$this->messages[$reader] = $message;
			$this->waitingWriters[] = &$reader;

			// Finish with this reader
			$key = array_search(&$reader, $this->waitingReaders);
			unset($this->waitingReaders[$key]);
		}
		
		if(self::DEBUG)
			$this->writeLog("processReaders(): end\n");
	}
	
	private function processWriters(&$readyWriters) 
	{
		if(self::DEBUG) {
			$this->writeLog("processWriters(): begin\n");
			var_dump($readyWriters);
		}
		
		foreach($readyWriters as &$writer)
		{
			// Write the response
			try
			{
				$message = $this->messages[$writer];
				$this->writeResponseMessage($writer, $message['response']);
			}
			catch(Exception $ex)
			{
				if(self::DEBUG)
					$this->writeLog("Error writing socket: " . $ex);
					
				SpicyPixel_SocketUtility::friendlyCloseSocket($writer);
				$key = array_search(&$writer, $this->waitingWriters);
				unset($this->waitingWriters[$key]);
				unset($this->messages[$writer]);
				continue;
			}
						
			// Remove the response
			unset($this->messages[$writer]);

			// Finish with this writer by adding it back to the reader queue
			$this->waitingReaders[] = &$writer;
			$key = array_search(&$writer, $this->waitingWriters);
			unset($this->waitingWriters[$key]);
		}
		
		if(self::DEBUG)
			$this->writeLog("processWriters(): end\n");
	}
	
	private function &readRequestMessage(&$reader) 
	{
		$request = array();
		
		if(self::DEBUG)
			$this->writeLog("readRequestMessage(): begin\n");
		
		// Read command			
		$command = $request['command'] = SpicyPixel_BlobMessageSerializer::readBlobMessage($reader);
		if(self::DEBUG)
			$this->writeLog("readRequestMessage(): command = $command\n");
			
		switch($command) 
		{
			case 'g': // get(key)
			case 'd': // delete(key)
				// key
				$key = SpicyPixel_BlobMessageSerializer::readBlobMessage($reader);
				$request['key'] = &$key;
				
				if(self::DEBUG)
					$this->writeLog("readRequestMessage(): key = $key\n");
				break;
			case 's': // set(key, data, expire)
			case 'a': // add
			case 'r': // replace
				// key
				$key = SpicyPixel_BlobMessageSerializer::readBlobMessage($reader);
				$request['key'] = &$key;

				if(self::DEBUG)
					$this->writeLog("readRequestMessage(): key = $key\n");
				
				// expire
				$expire = SpicyPixel_BlobMessageSerializer::readBlobMessage($reader);
				$request['expire'] = &$expire;

				if(self::DEBUG)
					$this->writeLog("readRequestMessage(): expire = $expire\n");

				// data					
				$data = SpicyPixel_BlobMessageSerializer::readBlobMessage($reader);
				$request['data'] = &$data;

				if(self::DEBUG)
					$this->writeLog("readRequestMessage(): data read successfully\n");

				break;
			case 'f': // flush
				break;
			case '!':
				$this->shutdownSignal = true;
				$request = array(); // clearing the array here will close the client connection and shutdown
				break;
			default:
				throw new Exception('Invalid command received: ' . $command);
		}
				
		if(self::DEBUG)
			$this->writeLog("readRequestMessage(): end\n");
		
		return $request;
	}
	
	private function &handleRequest(&$client, &$request) 
	{
		$response = array();
		
		$command = $response['command'] = $request['command'];
		switch($command)
		{
			case 'g': // get
				$result = $this->cache->get($request['key']);
				if($result === false)
					$response['result'] = 'FALSE';
				else {
					$response['result'] = 'OK';
					$response['reply'] = &$result;
				}
				break;
			case 'd': // delete
				$this->cache->delete($request['key']);
				$response['result'] = 'OK';
				break;
			case 's': // set
				$this->cache->set($request['key'], $request['data'], $request['expire']);
				$response['result'] = 'OK';
				break;
			case 'a': // add
				$result = $this->cache->add($request['key'], $request['data'], $request['expire']);
				if($result === false)
					$response['result'] = 'FALSE';
				else {
					$response['result'] = 'OK';
					$response['reply'] = &$result;
				}				
				break;
			case 'r': // replace
				$result = $this->cache->replace($request['key'], $request['data'], $request['expire']);
				if($result === false)
					$response['result'] = 'FALSE';
				else {
					$response['result'] = 'OK';
					$response['reply'] = &$result;
				}
				break;
			case 'f': // flush
				$this->cache->flush();
				$response['result'] = 'OK';
				break;
			default:
				throw new Exception('Invalid request for processing: ' . $command);
		}
		
		return $response;
	}
	
	private function writeResponseMessage(&$writer, &$response)
	{
		if(self::DEBUG) {
			$this->writeLog("writeResponseMessage(): begin\n");
			$responseString = (string)$response;
			$this->writeLog("writeResponseMessage(): response = $responseString\n");
		}		
					
		$command = $response['command'];
		switch($command)
		{
			case 'g': // get(key)
				if($response['result'] == 'OK') {
					SpicyPixel_SocketUtility::writeChunked($writer, $result = '1');
					SpicyPixel_BlobMessageSerializer::writeBlobMessage($writer, $response['reply']);
				}
				else
					SpicyPixel_SocketUtility::writeChunked($writer, $result = '0');
					
				break;
			case 'd': // delete(key)
			case 's': // set(key, data, expire)
			case 'a': // add
			case 'r': // replace
			case 'f': // flush
				if($response['result'] == 'OK')
					SpicyPixel_SocketUtility::writeChunked($writer, $result = '1');
				else
					SpicyPixel_SocketUtility::writeChunked($writer, $result = '0');
				break;
			default:
				throw new Exception('Invalid operation for writing: ' . $command);
		}
		
		if(self::DEBUG)
			$this->writeLog("writeResponseMessage(): end\n");
	}
}

?>