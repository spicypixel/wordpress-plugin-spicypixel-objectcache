<?php

// namespace SpicyPixel;
//
// PHP < 5.3 doesn't support namespaces, so underscore to the rescue.

interface SpicyPixel_OutputChannel { 
    public function send($data); 
} 

interface SpicyPixel_InputChannel { 
    public function receive($bytes = 1024); 
}

function SpicyPixel_isWindows() {
	// return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
	return !function_exists('posix_mkfifo');
}

function SpicyPixel_getNamedPipePath($name) {
	if(SpicyPixel_isWindows())
		return '\\\\127.0.0.1\\pipe\\' . $name;
		
	return $name;
}

class SpicyPixel_NamedPipeReader implements SpicyPixel_InputChannel {
	private $path;
	private $fileHandle;
	private $blocking = true;
	
	public function __construct($name) {
		$this->path = SpicyPixel_getNamedPipePath($name);
	}

	public function open() {
		if(!empty($this->fileHandle))
			throw new Exception('The pipe was already open and an attempt was made to open it a second time');
			
		if(!file_exists($this->path)) {
			return false;
		}
	
		echo 'created now openening...';
		$this->fileHandle = fopen($this->path, "rb+");
		if($this->fileHandle != null && !$this->blocking) {
			stream_set_blocking($fileHandle, false);
		}
		
		return $this->fileHandle != null;
	}
	
	public function isOpen() {
		return $this->fileHandle != null;
	}
	
	public function close() {
		if(empty($this->fileHandle))
			throw new Exception('The pipe is not open and must be to close');
			
		fclose($this->fileHandle);
		$this->fileHandle = null;
	}
	    
    public function setBlocking($enabled) {
    	if($this->blocking != $enabled && !empty($this->fileHandle))
	    	stream_set_blocking($fileHandle, $enabled);
	    
	    $this->blocking = $enabled;
    }
    
    public function receive($bytes = 1024) { 
		if(empty($this->fileHandle))
			throw new Exception('The pipe is not open and must be to receive');
		
		return fread($this->fileHandle, $bytes);
    }
}

class SpicyPixel_NamedPipeExclusiveWriter implements SpicyPixel_OutputChannel {
	private $path;
	private $fileHandle;
	private $blocking = true;

	public function __construct($name) {
		$this->path = SpicyPixel_getNamedPipePath($name);
	}
	
	public function open() {
		if(!empty($this->fileHandle))
			throw new Exception('The pipe was already open and an attempt was made to open it a second time');
		
		// Only open for exclusive writing	
		if(file_exists($this->path)) {
			echo 'file exists';
			return false;
		}
		
		// Create the PIPE on POSIX if it doesn't exist
		$use = "rb+";
		if(!SpicyPixel_isWindows()) {
			if(!posix_mkfifo($this->path, $mode = 0600))
				return false;
		}
		else {
			$use = "wb";
		}
	
		// Open for writing
		echo 'created now opening';
		$this->fileHandle = fopen($this->path, $use);
		echo 'open';
		
		// Honor blocking setting
		if($this->fileHandle != null && !$this->blocking) {
			stream_set_blocking($fileHandle, false);
		}
		
		return $this->fileHandle != null;
	}

	public function isOpen() {
		return $this->fileHandle != null;
	}
	
	public function close() {
		if(empty($this->fileHandle))
			throw new Exception('The pipe is not open and must be to close');
			
		fclose($this->fileHandle);
		$this->fileHandle = null;
		
		// Delete file
		unlink($this->path);
	}
    
    public function setBlocking($enabled) {
    	if($this->blocking != $enabled && !empty($this->fileHandle))
	    	stream_set_blocking($fileHandle, $enabled);
	    
	    $this->blocking = $enabled;
    }
    
    public function send($data) { 
    	if(empty($this->fileHandle))
			throw new Exception('The pipe is not open and must be to send');

        return fwrite($this->fileHandle, $data);
    }
}

?>