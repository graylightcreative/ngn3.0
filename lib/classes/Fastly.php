<?php

class Fastly {
    private $api_key;
    private $service_id;

    public function __construct($service_id = null)
    {
        $this->api_key = $_ENV['FASTLY_API_KEY'] ?? null; // Get API key from .env
        $this->service_id = $service_id ?? $_ENV['FASTLY_SERVICE_ID'] ?? null; // Service ID from .env or passed as argument

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

    private function createHealthCheck($version, $health_check_path)
    {
        $url = "https://api.fastly.com/service/{$this->service_id}/version/{$version}/healthcheck";
        $healthCheckData = [
            'name' => 'origin_health_check',
            'http_version' => '1.1',
            'method' => 'GET',
            'host' => 'nextgennoise.com',
            'path' => $health_check_path,
            'expected_response' => 200,
            'check_interval' => 30,
            'threshold' => 3,
            'window' => 5
        ];
        $curlHandler = $this->initializeCurlHandler($url, 'POST', $healthCheckData);

        $response = $curlHandler->execute();
        if (!$response) {
            $this->logError("Failed to create Fastly health check.");
            return false;
        }

        $decodedResponse = json_decode($response, true);
        return isset($decodedResponse['name']) ? $decodedResponse : false;
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