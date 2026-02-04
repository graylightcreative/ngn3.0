<?php


namespace NextGenNoise\Http;
// Adjust the namespace as needed

class CurlHandler
{

	private $ch; // cURL handle

	public function __construct()
	{
		$this->ch = curl_init();
	}

	public function __destruct()
	{
		curl_close($this->ch);
	}

	public function setOption($option, $value)
	{
		curl_setopt($this->ch, $option, $value);
	}

	public function execute()
	{
		return curl_exec($this->ch);
	}

	public function getInfo($option = null)
	{
		return $option ? curl_getinfo($this->ch, $option) : curl_getinfo($this->ch);
	}

	public function getError()
	{
		return curl_error($this->ch);
	}

	public function getErrorCode()
	{
		return curl_errno($this->ch);
	}

	// Additional helper methods can be added here for common request types (GET, POST, etc.)
}