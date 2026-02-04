<?php

class Bot
{
    public $name;
    public $purpose;
    public $personality;

    public function __construct($name, $purpose, $personality) {
        $this->name = $name;
        $this->purpose = $purpose;
        $this->personality = $personality;
    }

    // Getters
    public function getName() {
        return $this->name;
    }

    public function getPurpose() {
        return $this->purpose;
    }

    public function getPersonality() {
        return $this->personality;
    }

    // More functionalities can be added
}