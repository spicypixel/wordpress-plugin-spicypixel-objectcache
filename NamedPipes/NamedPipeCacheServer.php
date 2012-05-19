<?php

// NOTE: This code is obsolete and was replaced by a sockets solution.

namespace SpicyPixel;

require('MemoryCache.php');
require('Channels.php');

const CONNECTION_CHANNEL_NAME = 'spoc.connections';

class NamedPipeCacheServer 
{
	const DEBUG = true;

	// The cache to use
	private $cache;
	
	// Waiting readers are clients the server is waiting to read from
	private $waitingReaders = array();
	
	// Waiting writers are clients the server is waiting to write to
	private $waitingWriters = array();
	
	// The total number of waiting readers and writers.
	// Used to optimize sleep schedule
	private $waitingCount = 0;
	
	// Readers currently having their requests processed
	private $processingReaders = array();
	
	// Process is re-entrant, so track the stack depth
	private $processDepth = 0;
	
	public function __construct() {
		$this->cache = new MemoryCache();
	}
	
	// The main (blocking) run loop for the server
	// $lifetime: How long to run the loop
	// $longSleep: Sleep duration (seconds) when there has been no activity
	// $shortSleep: Sleep duration (microseconds) when there has been activity
	// $shortSleepPeriod: Period (seconds) to do short sleep before going long
	public function run($lifetime = 60, $inactiveLifetime = 120, $longSleep = 1, $shortSleep = 100000, $shortSleepPeriod = 4) 
	{
		/*const*/ $timeStop = time() + $lifetime; // Only run the loop until the stop time
		$longSleepCount = 0; // In case we want to sleep longer later or terminate
		$shortSleepCount = 0;
		$connectionChannel = new NamedPipeReader(namespace\CONNECTION_CHANNEL_NAME); // Pipe to read from

		// Main loop
		while(time() < $timeStop && $longSleepCount * $longSleep < $inactiveLifetime)
		{
			// How many connects occur this iteration
			$connectsThisTime = 0;
			/*const*/ $connectsPerIteration = 10;
			
			// Accept incoming connections (up to 10 in a row before processing some)
			$client = null;
			for($connects = 0; $connects < $connectsPerIteration; $connects++)
			{
				//echo 'start accept';
				$client = $this->acceptClient($connectionChannel);
				//echo 'end accept';
				if(!empty($client)) {
					echo 'got client';
					// Add to the waiting request list
					$this->waitingReaders[$client['inputFromClient']] = $client;
					$this->waitingCount++;
					$connectsThisTime++;
				}
				else {
					break;
				}
			}
			
			// Process all ready requests and responses
			$this->process();
			
			// Sleep for a bit
			$shortSleepSeconds = ($shortSleep / 1000000) * $shortSleepCount;
			$shouldLongSleep = $shortSleepSeconds > $shortSleepPeriod;
			if($shouldLongSleep && $waitingCount == 0 && $connectsThisTime == 0) {
				$longSleepCount++;
				sleep($longSleep);
			}
			else {
				if($shouldLongSleep) // reset to short sleep if we got here because of activity
					$shortSleepCount = 0;
					
				$longSleepCount = 0;
				$shortSleepCount++;
				usleep($shortSleep);
			}
		}
	}
	
	private function process() 
	{
		if($processDepth > 5)
			return;
			
		if(waitingCount == 0 || !stream_select(array_keys($this->waitingReaders), array_keys($this->waitingWriters), $except = null, 0))
			return; // return nothing to do
		
		$processDepth++;
				
		$this->processReaders();
		$this->processWriters();
		
		$processDepth--;
	}
	
	private function processReaders() 
	{
		// Process all waiting readers
		foreach(array_keys($this->waitingReaders) as $reader)
		{
			// Don't process if already in progress
			if(array_key_exists($reader, $this->processingReaders))
				continue; // skip
			
			// Don't process if a read would block
			//if(stream_select(array($reader), $writers = null, $except = null, 0))
			if(feof($reader))
				continue; // skip

			// Mark as processing
			$this->processingReaders[$reader] = $reader;
			
			// Read the request
			$request = null;
			try
			{
				$request = $this->readRequest($reader);
			}
			catch(Exception $ex)
			{
				$request = null;
				if(self::DEBUG)
					echo $ex;
			}
			
			if(empty($request)) {
				// Something bad happened so close the channel and abandon the reader
				unset($this->processingReaders[$reader]);
				unset($this->waitingReaders[$reader]);
				continue;
			}
			
			// Prepare the response
			$client = $this->waitingReaders[$reader];
			$client['request'] = $request;
			$response = $this->handleRequest($client, $request);
			
			// Queue the response
			$client['response'] = $response;
			$this->waitingWriters[$client["outputToClient"]] = $client;

			// Finish with this reader
			unset($this->waitingReaders[$reader]);
			unset($this->processingReaders[$reader]);
		}
	}
	
	private function processWriters() 
	{
		foreach(array_keys($this->waitingWriters) as $writer)
		{
			// Write when ready
			if(stream_select($readers = null, array($writer), $except = null, 0))
			{
				$this->writeResponse($writer);
			}
		}
	}
	
	private function readRequest($reader) 
	{
		$request = array();
		
		try 
		{
			$command = $reader->receive(1);
			$request['command'] = $command;
			switch($command) 
			{
				case 'g': // get(key)
				case 'd': // delete(key)
					// key
					$keyLength = unpack('L', $reader->receive(4));
					$keyLength = $keyLength[1];
					$request['key'] = $reader->receive($keyLength);
					break;
				case 's': // set(key, data, expire)
				case 'a': // add
				case 'r': // replace
					// key
					$keyLength = unpack('L', $reader->receive(4));
					$keyLength = $keyLength[1];
					$request["key"] = $reader->receive($keyLength);
					
					// expire
					$expireLength = unpack('L', $reader->receive(4));
					$expireLength = $expireLength[1];
					$request['expire'] = $reader->receive($expireLength);
					
					// data
					$dataLength = unpack('L', $reader->receive(4));
					$dataLength = $dataLength[1];
									
					$data = '';
					$bytesRead = 0;
					$endTime = time() + 20; // don't allow writing or blocking for more than 20 seconds
					while($bytesRead < $dataLength && time() < $endTime) 
					{
						if(!feof($reader))
						{
							$nextBlockSize = min($dataLength - $bytesRead, 4096);
							$block = $reader->receive($nextBlockSize);
							$bytesRead += mb_strlen($block, '8bit');
							$data .= $block;
						}
						else 
						{
							usleep(100);	
						}
					}
					$request['data'] = $data;
					break;
				case 'f': // flush
					break;
				default:
					throw new \Exception('Invalid operation received: ' . $command);
			}
		}
		catch(Exception $ex) 
		{
			return null;
		}
	}
	
	private function handleRequest($client, $request) 
	{
		$response = array();
		
		$response['command'] = $command = $request['command'];
		switch($command)
		{
			case 'g': // get
				$response['result'] = $this->cache->get($request['key']);
				break;
			case 'd': // delete
				$this->cache->delete($request['key']);
				break;
			case 's': // set
				$this->cache->set($request['key'], $request['data'], $request['expire']);
				break;
			case 'a': // add
				$response['result'] = $this->cache->add($request['key'], $request['data'], $request['expire']);
				break;
			case 'r': // replace
				$response['result'] = $this->cache->replace($request['key'], $request['data'], $request['expire']);
				break;
			case 'f': // flush
				$this->cache->flush();
				break;
		}
	}
	
	public function acceptClient($connectionChannel) {
		// Wait for a writer (client)
		if(!$connectionChannel->open())
			return false;
			
		try
		{
			// A writer has appeared, get its channel name		
			$clientChannelNameLength = unpack('L', $reader->receive(4));
			$clientChannelNameLength = $clientChannelNameLength[1];
			$clientChannelName = $connectionChannel->receive($clientChannelNameLength);
			
			// Bind to the client for duplex communication		
			$client = $this->bindClient($clientChannelName);
		}
		catch(Exception $ex)
		{
			echo $ex;
		}
		
		// Close connection channel and wait for another client
		$connectionChannel->close();
		
		// Return the bound client
		return $client;
	}
	
	private function bindClient($clientChannelName) {
		$timeStart = time();
		
		// Creating a write channel won't block
		$outputToClient = new NamedPipeExclusiveWriter($clientChannelName . '.in'); // Write to the client's in 
		if(!$outputToClient->open())
			throw new \Exception('Unable to create a new channel for writing to the client');

		// Create a read channel and wait for the client to open its writing end.
		// Use a short timeout and tight loop because no new connections with other 
		// clients can be made during this bind.
		$inputFromClient = new NamedPipeReader($clientChannelName . '.out');
		while(!$inputFromClient->open() && time() < $timeStart+2) {
			process();
			usleep(100000);
		}
			
		if(!$inputFromClient->isOpen()) {
			$outputToClient->close();
			throw new \Exception('Unable to create a new channel for reading from the client');
		}
		
		return array(
			'name' => $clientChannelName,
			'outputToClient' => $outputToClient,
			'inputFromClient' => $inputFromClient
		);
	}
}

$server = new ObjectCacheServer();
$server->run();

?>