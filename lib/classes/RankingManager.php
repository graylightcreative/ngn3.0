<?php

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

class RankingManager {
    public $userId;
    public $interval;
    public $type;
    private $pdo;

    public function __construct($userId=1286, $interval='', $type='Artists') {
        $this->userId = $userId;
        $this->interval = $interval;
        $this->type = $type;

        $config = new Config();
        $this->pdo = ConnectionFactory::write($config);
    }

    public function getRankingsByUserIdAndType() {
        // examples
        // id: 1286 (heroes and villains
        // type: Artists
        // interval: ''

        return  readByDB($this->pdo, $this->type.$this->interval,$this->type.'Id', $this->userId);
    }
}