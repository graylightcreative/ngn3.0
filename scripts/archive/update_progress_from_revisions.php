<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

class RevisionProgressUpdater
{
    private string $revisionFilePath;
    private string $progressFilePath;

    public function __construct(string $revisionFilePath, string $progressFilePath)
    {
        $this->revisionFilePath = $revisionFilePath;
        $this->progressFilePath = $progressFilePath;
    }

    public function update(): void
    {
        $revisionTasks = $this->readRevisionTasks();
        $progressData = $this->readProgressJson();

        foreach ($revisionTasks as $task) {
            if ($task['status'] === 'COMPLETED') {
                $progressData[$task['id']] = [
                    'description' => $task['description'],
                    'status' => 'completed',
                    'completion_date' => date('Y-m-d H:i:s'), // Add a timestamp for completion
                ];
            }
        }

        $this->writeProgressJson($progressData);
        echo "progress.json updated successfully.\n";
    }

    private function readRevisionTasks(): array
    {
        if (!file_exists($this->revisionFilePath)) {
            return [];
        }

        $content = file_get_contents($this->revisionFilePath);
        $lines = explode("\n", $content);
        $tasks = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^- ([A-Z0-9-]+): (.+) - (PENDING|COMPLETED)$/', $line, $matches)) {
                $tasks[] = [
                    'id' => $matches[1],
                    'description' => trim($matches[2]),
                    'status' => $matches[3],
                ];
            }
        }
        return $tasks;
    }

    private function readProgressJson(): array
    {
        if (!file_exists($this->progressFilePath) || filesize($this->progressFilePath) === 0) {
            return [];
        }

        $content = file_get_contents($this->progressFilePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // Return empty array on JSON error
        }
        return $data;
    }

    private function writeProgressJson(array $data): void
    {
        file_put_contents($this->progressFilePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}

// Instantiate and run the updater
$updater = new RevisionProgressUpdater(__DIR__ . '/../revision.md', __DIR__ . '/../progress.json');
$updater->update();

