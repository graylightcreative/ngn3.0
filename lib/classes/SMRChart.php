<?php
namespace NGN\Charts;

use NGN\Lib\DB\ConnectionFactory;
use NGN\Lib\Config;

class SMRChart
{
    public $weekStart;
    public $timestamp;
    public $artists;
    public $labels;
    private $smr_pdo;

    public function __construct($weekStart, $timestamp, $artists = false)
    {
        $this->weekStart = $weekStart;
        $this->timestamp = $timestamp;
        $this->artists = $artists;

        $config = new Config();
        $this->smr_pdo = ConnectionFactory::named($config, 'SMR2025');
    }

    public function handleArtistsandSong(){
        if(!$this->artists) return false;
        $artistSongSplit = explode(' / ',$this->artists);

        if(!isset($artistSongSplit[1])) return false;

        $array = [];
        $array['Artists'] = $artistSongSplit[0];
        $array['Song'] = $artistSongSplit[1];
        return $array;
    }
    public function handleLabels(){
        // labels is a string
        // LABEL/LABEL
        // OR LABEL
        $fullList = [];
        $labels = strtolower($this->labels);
        if(str_contains($labels, '/')){
            // multiple labels
            $labels = explode('/',$labels);
            foreach($labels as $label){
                $fullList[] = $label;
            }
        } else {
            // single label
            $fullList[] = $labels;
        }

        return $fullList;
    }
    public function getRecordsByWeek(){
        $sql = "SELECT * FROM `SMRCharts` WHERE `Timestamp` >= :weekStart AND `Timestamp` <= DATE_ADD(:weekStart, INTERVAL 7 DAY)";
        $result = queryByDB($this->smr_pdo, $sql, ['weekStart' => $this->weekStart]);
        return $result;
    }
    public function getRecordByTimestamp(){
        $sql = "SELECT * FROM `SMRCharts` WHERE `Timestamp` = :timestamp";
        $result = queryByDB($this->smr_pdo,$sql, ['timestamp' => $this->timestamp]);
        return $result;
    }


    public function getWeekStartFromTimestamp()
    {
        $this->weekStart = date('Y-m-d', strtotime('monday this week', strtotime($this->timestamp)));
        return date('Y-m-d', strtotime('monday this week', strtotime($this->timestamp)));
    }
}