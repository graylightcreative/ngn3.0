<?php

use Google\Cloud\Language\V1\LanguageServiceClient;
use Google\Cloud\Language\V1\Document;
use Google\Cloud\Language\V1\Document\Type;

class CentralBot
{
    private LanguageServiceClient $languageClient;
    private array $departments;
    private array $bots;

    public function __construct(array $departments, array $bots)
    {
        $this->departments = $departments;
        $this->bots = $bots;
        $this->languageClient = new LanguageServiceClient();
    }

    public function analyzeAndDelegate(string $task): array
    {
        try {
            $analysis = $this->analyzeTaskContent($task);
            $phrasePool = $this->fetchPhrasePool();
            $scores = $this->contextualScoring($analysis, $phrasePool);
            $bot = $this->assignByRole($scores);

            if ($bot !== null) {
                return $this->delegateTask($task, $bot);
            } else {
                return $this->flagForHumanReview($task);
            }
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function analyzeTaskContent(string $task): array
    {
        $document = new Document();
        $document->setContent($task);
        $document->setType(Type::PLAIN_TEXT);

        try {
            $entityResponse = $this->languageClient->analyzeEntities($document);
            $sentimentResponse = $this->languageClient->analyzeSentiment($document);
            $annotation = $this->languageClient->analyzeSyntax($document);
        } catch (Exception $e) {
            throw new Exception('Error while analyzing document: ' . $e->getMessage());
        }

        $entities = iterator_to_array($entityResponse->getEntities());
        $sentiment = $sentimentResponse->getDocumentSentiment();
        $tokens = iterator_to_array($annotation->getTokens());

        $nounPhrases = $this->extractPhrases($tokens, ['NOUN', 'ADJ']);
        $verbPhrases = $this->extractPhrases($tokens, ['VERB']);
        $keywords = array_map(fn($token) => $token->getText()->getContent(), $tokens);

        return [
            'entities' => $entities,
            'sentiment' => $sentiment,
            'nounPhrases' => $nounPhrases,
            'verbPhrases' => $verbPhrases,
            'keywords' => $keywords
        ];
    }

    private function extractPhrases(array $tokens, array $posTags): array
    {
        $phrases = [];
        $currentPhrase = [];
        foreach ($tokens as $token) {
            $posTag = $token->getPartOfSpeech()->getTag();
            if (in_array($posTag, $posTags)) {
                $currentPhrase[] = $token->getText()->getContent();
            } else {
                if (!empty($currentPhrase)) {
                    $phrases[] = implode(' ', $currentPhrase);
                    $currentPhrase = [];
                }
            }
        }
        if (!empty($currentPhrase)) {
            $phrases[] = implode(' ', $currentPhrase);
        }

        return $phrases;
    }

    private function assignByRole(array $scores): ?array
    {
        $matchedBots = [];
        foreach ($this->departments as $department) {
            $keywords = json_decode($department['Keywords'], true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($keywords)) {
                // Handle JSON decode error
                continue;
            }

            foreach ($keywords as $keyword) {
                if (array_key_exists($keyword, $scores)) {
                    foreach ($this->bots as $bot) {
                        if ($bot['Department'] == $department['id']) {
                            $matchedBots[$bot['id']] = ($matchedBots[$bot['id']] ?? 0) + $scores[$keyword];
                        }
                    }
                }
            }
        }

        arsort($matchedBots);
        $bestBotId = key($matchedBots);

        foreach ($this->bots as $bot) {
            if ($bot['Id'] === $bestBotId) {
                return $bot;
            }
        }

        return null;
    }

    private function delegateTask(string $task, array $bot): array
    {
        return [
            'task' => $task,
            'bot' => $bot,
            'details' => $this->handleTaskBasedOnRole($task, $bot),
            'needs_feedback' => true
        ];
    }

    private function handleTaskBasedOnRole(string $task, array $bot): string
    {
        if (!isset($this->departments) || !is_array($this->departments)) {
            return 'General task delegation';
        }

        foreach ($this->departments as $department) {
            if (isset($bot['Role']) && isset($department['Role']) && strtolower($bot['Role']) == strtolower($department['Role'])) {
                return $this->genericTaskHandler($task, strtolower($department['Role']));
            }
        }

        return 'General task delegation';
    }

    private function genericTaskHandler(string $task, string $subject): string
    {
        return "Handling {$subject} task for: '{$task}'.";
    }

    private function assignBasedOnEmotion(string $task, $sentiment): ?array
    {
        return reset($this->bots);
    }

    private function fallbackBot(): ?array
    {
        return reset($this->bots);
    }

    private function flagForHumanReview(string $task): array
    {
        return [
            'message' => 'Task requires human review.',
            'code' => 202,
            'task' => $task
        ];
    }

    private function contextualScoring(array $analysis, array $phrasePool): array
    {
        $scores = [];
        foreach ($phrasePool as $phrase) {
            $weight = $phrase['Weight'];
            $phraseText = $phrase['Phrase'];
            foreach ($analysis['keywords'] as $keyword) {
                if (stripos($phraseText, $keyword) !== false) {
                    $scores[$phrase['Category']] = ($scores[$phrase['Category']] ?? 0) + $weight;
                }
            }
        }
        return $scores;
    }

    private function fetchPhrasePool(): array
    {
        return browse('PhrasePool');
    }
}
?>