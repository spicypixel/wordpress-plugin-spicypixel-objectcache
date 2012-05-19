<?php

defined('SPOC_DIR') || define('SPOC_DIR', realpath(dirname(__FILE__) . '/..'));
require_once(SPOC_DIR . '/Sockets/SocketUtility.php');

class SpicyPixel_BlobMessageSerializer
{	
	// Blob message frame
	//  dataLength: 4 bytes
	//  data: dataLength bytes
	
	public static function readBlobMessage(&$socket)
	{
		$dataLength = unpack('L', SpicyPixel_SocketUtility::readChunked($socket, 4));
		$dataLength = $dataLength[1];
		//echo "Reading data blob of length = $dataLength\n";
		$data = SpicyPixel_SocketUtility::readChunked($socket, $dataLength);
		//echo "Read data blob of length = $dataLength\n";
		return unserialize($data);
	}
	
	public static function writeBlobMessage(&$socket, &$data)
	{
		$encodedData = serialize($data);
		$length = pack('L', strlen($encodedData));
		SpicyPixel_SocketUtility::writeChunked($socket, $length, 4);
		SpicyPixel_SocketUtility::writeChunked($socket, $encodedData);
	}
}

?>