<?php

class CurlHandler
{
    private $handle;

    public function __construct(string $url)
    {
        $this->handle = curl_init($url);
        // Ensure that the response is returned as a string
        curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);
    }

    public function setOption($option, $value)
    {
        curl_setopt($this->handle, $option, $value);
    }

    public function execute()
    {
        $response = curl_exec($this->handle);
        if ($response === false) {
            throw new RuntimeException('Curl error: ' . curl_error($this->handle));
        }
        return $response;
    }

    public function getHandle()
    {
        return $this->handle;
    }

    public function close()
    {
        curl_close($this->handle);
    }
}
?>