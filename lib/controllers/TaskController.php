<?php

use Google\Cloud\Language\V1\LanguageServiceClient;
use Google\Cloud\Language\V1\Document;

function assignTask($data, $botName)
{
    // Set the Google Application Credentials environment variable
    putenv('GOOGLE_APPLICATION_CREDENTIALS=/home/brockstarr2/nextgennoise/lib/definitions/ngn2024-2bd251265e1a.json');

    // Retrieve task from provided data
    $task = $data['task'] ?? '';

    // Check for an empty task and return an error response if none provided
    if (empty($task)) {
        return ['error' => 'No task provided'];
    }

    try {
        // Initialize Google Language Service Client
        $languageClient = new LanguageServiceClient();

        // Prepare the document with task content for sentiment analysis
        $document = new Document();
        $document->setContent($task);
        $document->setType(Document\Type::PLAIN_TEXT);

        // Analyze the document's sentiment
        $response = $languageClient->analyzeSentiment($document);
        $sentiment = $response->getDocumentSentiment();

        // Close the client to free up resources
        $languageClient->close();

        // Return the task with sentiment analysis scores
        return [
            'task' => $task,
            'sentiment_score' => $sentiment->getScore(),
            'sentiment_magnitude' => $sentiment->getMagnitude()
        ];
    } catch (\Exception $e) {
        // Log the exception for debugging
        error_log("Error analyzing sentiment: " . $e->getMessage());

        // Return an error response with the exception message
        return ['error' => 'Failed to analyze task sentiment', 'message' => $e->getMessage()];
    }
}