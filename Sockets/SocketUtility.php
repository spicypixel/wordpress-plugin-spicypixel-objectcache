<?php

class SpicyPixel_SocketUtility
{
	const DEFAULT_CHUNK_SIZE = 4096;

	public static function throwLastSocketException()
	{
		throw new Exception('Socket error: ' . socket_strerror(socket_last_error()));
	}
	
	public static function friendlyCloseSocket(&$socket)
	{
		@socket_shutdown($socket, 1);
		usleep(500);
		@socket_shutdown($socket, 0);
		@socket_close($socket);
	}
			
	public static function &readChunked(&$socket, $dataLength)
	{
		$totalBytesRead = 0;
		$data = '';
		while($totalBytesRead < $dataLength) 
		{
			$nextBlockSize = min($dataLength - $totalBytesRead, self::DEFAULT_CHUNK_SIZE);
			$bytesRead = @socket_recv($socket, $block, $nextBlockSize, MSG_WAITALL);
			if($bytesRead == false) // or null (or 0 == closed)
				SpicyPixel_SocketUtility::throwLastSocketException();
			$totalBytesRead += $bytesRead;
			$data .= $block;
			unset($block);
		}
		return $data;
	}
	
	public static function writeChunked(&$socket, &$data, $length = 0)
	{
		if(!is_string($data)) {
			throw new Exception("writeChunked $data must be a string");
		}
		
		$msg = &$data; // use a reference initially for performance
		$first = true;
		
		$dataLength = strlen($msg);
		if($length == 0)
			$length = $dataLength;
		$length = min($length, $dataLength);
		
		while(true)
		{
			$bytesWritten = @socket_send($socket, $msg, $length, 0);
			if($bytesWritten === false)
				SpicyPixel_SocketUtility::throwLastSocketException();
			
			if($bytesWritten < $length) {
				if(first) {
					unset($msg);
					$msg = substr($data, $bytesWritten);
					$first = false;
				} else {
					$msg = substr($msg, $bytesWritten);
					$length -= $bytesWritten;
				}
			} else {
				return;
			}
		}
	}
}

?>