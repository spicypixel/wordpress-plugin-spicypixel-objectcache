<?php

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__) . '/..'));
require_once(SPOC_DIR . '/Cache/Cache.php');
require_once(SPOC_DIR . '/Sockets/BlobMessageSerializer.php');

class SpicyPixel_RemoteCache implements SpicyPixel_Cache
{
	const DEFAULT_ADDRESS = '127.0.0.1';
	const DEFAULT_PORT = 10840;
		
	private $socket;
	
	public function __construct()
	{
		if(!$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP))
			SpicyPixel_SocketUtility::throwLastSocketException();
	}
	
	public function connect(
		$address = self::DEFAULT_ADDRESS,
		$port = self::DEFAULT_PORT)
	{
		if(@socket_connect($this->socket, $address, $port) == false)
			SpicyPixel_SocketUtility::throwLastSocketException();
			
		return true;
	}
	
	public function close()
	{
		SpicyPixel_SocketUtility::friendlyCloseSocket($this->socket);
	}
	
	private function checkResult()
	{
		$result = SpicyPixel_SocketUtility::readChunked($this->socket, 1);
		if($result == '1')
			return true;
		
		return false;
	}
	
	/*
		This function adds data to the cache if the cache key doesn't 
		already exist. If it does exist, the data is not added and the 
		function returns false.
	*/
	public function add($key, &$data, $expire)
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = 'a');
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $key);
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $expire);
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $data);
		
		// response
		return $this->checkResult();
	}

	/*
	Adds data to the cache. If the cache key already exists, then 
	it will be overwritten; if not then it will be created.
	*/
	public function set($key, &$data, $expire)
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = 's');
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $key);
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $expire);
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $data);
		
		// response
		return $this->checkResult();
	}

	/*
	Returns the value of the cached object. Returns false if the 
	cache key doesn't exist.
	*/
	public function get($key)
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = 'g');
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $key);

		// response
		if($this->checkResult()) {
			$value = SpicyPixel_BlobMessageSerializer::readBlobMessage($this->socket);
			return $value;
		}
		
		return false;
	}

	/*
	Clears data from the cache for the given key.	*/
	public function delete($key)
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = 'd');
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $key);
		
		// response
		return $this->checkResult();		
	}

	/*
	Replaces the given cache if it exists, returns false otherwise. 
	This is similar to wp_cache_set() except the cache object is not 
	added if it doesn't already exist.
	*/
	public function replace($key, &$data, $expire)
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = 'r');
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $key);
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $expire);
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $data);

		// response
		return $this->checkResult();		
	}

	/*
	Clears all cached data.
	*/
	public function flush()
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = 'f');
		
		// response
		return $this->checkResult();	
	}
	
	public function shutdown()
	{
		// request
		SpicyPixel_BlobMessageSerializer::writeBlobMessage($this->socket, $command = '!');
		
		// response
		return $this->checkResult();	
	}
}

?>