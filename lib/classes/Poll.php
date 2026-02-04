<?php

class Poll
{
    private string $question;
    private array $options = [];
    private array $votes = [];

    /**
     * Constructor to initialize the poll with a question and options.
     *
     * @param string $question
     * @param array $options
     */
    public function __construct(string $question, array $options)
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('A poll must have at least two options.');
        }

        $this->question = $question;
        $this->options = $options;

// Initialize vote counts for each option
        foreach ($options as $option) {
            $this->votes[$option] = 0;
        }
    }

    /**
     * Get the poll question.
     *
     * @return string
     */
    public function getQuestion(): string
    {
        return $this->question;
    }

    /**
     * Get the available options for the poll.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Vote for a specific option.
     *
     * @param string $option
     * @return void
     */
    public function vote(string $option): void
    {
        if (!isset($this->votes[$option])) {
            throw new InvalidArgumentException('Invalid poll option.');
        }

        $this->votes[$option]++;
    }

    /**
     * Get the total votes for the poll.
     *
     * @return int
     */
    public function getTotalVotes(): int
    {
        return array_sum($this->votes);
    }

    /**
     * Get the votes for each option.
     *
     * @return array
     */
    public function getResults(): array
    {
        $totalVotes = $this->getTotalVotes();
        $results = [];

        foreach ($this->votes as $option => $count) {
            $percent = ($totalVotes > 0) ? ($count / $totalVotes) * 100 : 0;
            $results[$option] = [
                'votes' => $count,
                'percentage' => round($percent, 2)
            ];
        }

        return $results;
    }

    /**
     * Reset all votes for the poll.
     *
     * @return void
     */
    public function resetVotes(): void
    {
        foreach ($this->votes as $option => &$count) {
            $count = 0;
        }
    }
    

    /**
     * Static method to create a new poll instance.
     *
     * @param string $question
     * @param array $options
     * @return Poll
     */
    public static function createPoll(string $question, array $options): Poll
    {
        return new self($question, $options);
    }
    
    /**
     * Store the poll data into a JSON file.
     *
     * @param string $filePath The file path where the poll data will be saved.
     * @return void
     */
    public function addPoll(): void
    {
        $data = [
            'question' => $this->question,
            'options' => $this->options,
            'votes' => $this->votes
        ];

        add('Polls', $data);
    }

    /**
     * Update the poll data for a given ID.
     *
     * @param mixed $id The identifier of the poll to update.
     * @return void
     */
    public function updatePoll($id): void
    {
        // Prepare the updated poll data
        $data = [
            'question' => $this->question,
            'options' => $this->options,
            'votes' => $this->votes
        ];

        // Update the poll data by calling an external edit function
        edit('Polls', $id, $data);
    }
}

