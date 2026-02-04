<?php

class Python
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/'); // Ensure no trailing slash
    }

    /**
     * Runs the specified Python script.
     *
     * @param string $scriptPath The path of the Python script to run.
     * @param array $args Arguments to pass to the Python script.
     * @return string The output from the Python script.
     * @throws Exception If the Python script returns a non-zero exit status.
     */
    public function runScript(string $scriptPath, array $args = []): string
    {
        $url = $this->baseUrl . '/run_analysis';
        $curlHandler = new CurlHandler($url);

        // Payload containing the script path and arguments
        $payload = json_encode([
            'scriptPath' => $scriptPath,
            'args' => $args
        ]);

        // Add additional cURL options
        $curlHandler->setOption(CURLOPT_TIMEOUT, 30); // Set timeout
        $curlHandler->setOption(CURLOPT_HTTPHEADER, ['Content-Type: application/json']); // Set headers
        $curlHandler->setOption(CURLOPT_RETURNTRANSFER, true); // Return response as string
        $curlHandler->setOption(CURLOPT_POST, true); // Set method to POST
        $curlHandler->setOption(CURLOPT_POSTFIELDS, $payload); // Set the payload
        $curlHandler->setOption(CURLOPT_FOLLOWLOCATION, true); // Follow redirections

        $result = $curlHandler->execute();

        $curlError = curl_error($curlHandler->getHandle());
        $curlHandler->close();

        if ($result === false) {
            throw new Exception("Failed to execute cURL request: $curlError");
        }

        $output = json_decode($result, true);

        // Debugging: Check for JSON errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }

        // Debugging: Check and log the raw response
        if ($output === null) {
            throw new Exception("Invalid JSON response: " . $result);
        }

        if (!isset($output['status']) || $output['status'] !== 'success') {
            $message = $output['message'] ?? 'Unknown error';
            throw new Exception("Python script failed: " . $message);
        }

        return $output['message'] ?? '';
    }
}