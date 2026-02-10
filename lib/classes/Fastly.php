<?php

class Fastly {
    private $api_key;
    private $service_id;

    public function __construct($service_id = null)
    {
        $this->api_key = getenv('FASTLY_API_KEY'); // Get API key from env
        $this->service_id = $service_id ?? getenv('FASTLY_SERVICE_ID'); // Service ID from env or passed as argument

        if (empty($this->api_key)) {
            throw new Exception("FASTLY_API_KEY is not set.");
        }

        if (empty($this->service_id)) {
            throw new Exception("FASTLY_SERVICE_ID is not set.");
        }
    }

    public function setup($service_name, $origin_ip, $health_check_path, $shield, $domain_name)
    {
        $version = $this->getLatestVersion($this->service_id);
        if (!$version) {
            $this->logError("Failed to retrieve latest service version.");
            return false;
        }

        $cloned_version = $this->cloneVersion($this->service_id, $version);
        if (!$cloned_version) {
            $this->logError("Failed to clone service version.");
            return false;
        }

        $backend_name = 'default_backend';
        if (!$this->addBackend($this->service_id, $cloned_version, $backend_name, $origin_ip)) {
            return false;
        }

        if (!$this->addDomain($this->service_id, $cloned_version, $domain_name)) {
            return false;
        }

        // Create health check
        $health_check = $this->createHealthCheck($cloned_version, $health_check_path);
        if (!$health_check) {
            return false;
        }

        // Attach health check to the backend
        if (!$this->updateBackendHealthCheck($this->service_id, $cloned_version, $backend_name, $health_check['name'])) {
            return false;
        }

        if (!$this->activateVersion($this->service_id, $cloned_version)) {
            return false;
        }

        return true;
    }

    public function createDomain($domain_name)
    {
        $url = "https://api.fastly.com/service/{$this->service_id}/version/1/domain";
        $curlHandler = $this->initializeCurlHandler($url, 'POST', ['name' => $domain_name]);
        $response = $curlHandler->execute();
        return json_decode($response, true);
    }

    private $curlHandler;

    public function setCurlHandler($curlHandler) {
        $this->curlHandler = $curlHandler;
    }

    private function initializeCurlHandler($url, $method = 'GET', $data = []) {
        if ($this->curlHandler) {
            // Mock handler usage
            return $this->curlHandler; 
        }
        
        // ... (real curl init logic would go here if not mocked)
        return null; 
    }

    private function updateBackendHealthCheck($service_id, $version, $backend_name, $health_check_name)
    {
        $url = "https://api.fastly.com/service/{$service_id}/version/{$version}/backend/{$backend_name}";
        $curlHandler = $this->initializeCurlHandler($url, 'PUT', [
            'healthcheck' => $health_check_name
        ]);

        $response = $curlHandler->execute();
        if (!$response) {
            $this->logError("Failed to attach health check to backend.");
            return false;
        }

        $decodedResponse = json_decode($response, true);
        return isset($decodedResponse['name']);
    }

    private function cloneVersion($service_id, $version)
    {
        $url = "https://api.fastly.com/service/{$service_id}/version/{$version}/clone";
        $curlHandler = $this->initializeCurlHandler($url, 'PUT');
        $response = $curlHandler->execute();

        if (!$response) {
            $this->logError("Failed to clone service version.");
            return false;
        }

        $decodedResponse = json_decode($response, true);
        return isset($decodedResponse['number']) ? $decodedResponse['number'] : false;
    }
}