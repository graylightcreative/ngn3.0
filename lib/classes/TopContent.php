<?php

// TODO: THIS NEED COMPLETELY REDONE

class TopContent
{
    private $dbConnection;

    // Constructor to initialize a database connection
    public function __construct(PDO $dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    // Method to retrieve most viewed posts
    public function getMostViewedPosts(int $limit = 10): array
    {
        return $this->fetchTopContent('posts', $limit);
    }

    // Method to retrieve top viewed artists
    public function getTopViewedArtists(int $limit = 10): array
    {
        return $this->fetchTopContent('artists', $limit);
    }

    // Method to retrieve top viewed labels
    public function getTopViewedLabels(int $limit = 10): array
    {
        return $this->fetchTopContent('labels', $limit);
    }

    // Method to retrieve top viewed stations
    public function getTopViewedStations(int $limit = 10): array
    {
        return $this->fetchTopContent('stations', $limit);
    }

    // Method to retrieve top viewed releases
    public function getTopViewedReleases(int $limit = 10): array
    {
        return $this->fetchTopContent('releases', $limit);
    }

    // Method to retrieve top viewed songs
    public function getTopViewedSongs(int $limit = 10): array
    {
        return $this->fetchTopContent('songs', $limit);
    }

    // Method to retrieve top streamed songs
    public function getTopStreamedSongs(int $limit = 10): array
    {
        return $this->fetchTopContent('streamed_songs', $limit);
    }

    // Method to retrieve top viewed videos
    public function getTopViewedVideos(int $limit = 10): array
    {
        return $this->fetchTopContent('videos', $limit);
    }

    // Method to retrieve top viewed venues
    public function getTopViewedVenues(int $limit = 10): array
    {
        return $this->fetchTopContent('venues', $limit);
    }

    // Internal method to execute SQL queries for fetching top content
    private function fetchTopContent(string $table, int $limit): array
    {
        $query = "SELECT * FROM {$table} ORDER BY views DESC LIMIT :limit";
        $statement = $this->dbConnection->prepare($query);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}