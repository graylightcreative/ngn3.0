<?php


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SpotifyAPI
{
    private string $clientId;
    private string $clientSecret;
    private string $accessToken;
    private Client $httpClient;

    public function __construct(string $clientId, string $clientSecret)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->httpClient = new Client([
            'base_uri' => 'https://api.spotify.com/v1/',
        ]);
        $this->accessToken = $this->authenticate();
    }

    /**
     * Authenticate with Spotify API to get an access token
     */
    private function authenticate(): string
    {
        $authClient = new Client(['base_uri' => 'https://accounts.spotify.com/api/']);
        try {
            $response = $authClient->post('token', [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['access_token'] ?? throw new RuntimeException('Failed to retrieve access token.');
        } catch (RequestException $e) {
            throw new RuntimeException('Spotify API authentication failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get artist details by their Spotify ID
     */
    public function getArtistDetails(string $artistId): array
    {
        try {
            $response = $this->httpClient->get('artists/' . $artistId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Accept' => 'application/json',
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            throw new RuntimeException('Failed to retrieve artist details: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Evaluate artist by analyzing their popularity and genre
     */
    public function evaluateArtist(array $artistData): string
    {
        $popularity = $artistData['popularity'] ?? 0;
        $genres = $artistData['genres'] ?? [];

        if ($popularity > 80) {
            return 'Highly popular artist';
        } elseif ($popularity > 50) {
            return 'Moderately popular artist';
        } else {
            return 'Less popular artist';
        }
    }
    
    /**
     * Get genres from artist data
     */
    public function getGenres(array $artistData): array
    {
        return $artistData['genres'] ?? [];
    }

    /**
     * Get preferred genres from environment configuration
     */
    public function getPreferredGenres(): array
    {
        $preferredGenres = $_ENV['PREFERRED_GENRES'] ?? '';
        return explode(',', $preferredGenres);
    }
    
    /**
     * Get metrics of an artist based on popularity and preferred genres.
     */
    public function getMetrics(array $artistData): array
    {
        $popularity = $artistData['popularity'] ?? 0;
        $artistGenres = $this->getGenres($artistData);
        $preferredGenres = $this->getPreferredGenres();
        $followers = $artistData['followers']['total'] ?? 0;
        $releases = $artistData['releases'] ?? [];
        $songs = $artistData['songs'] ?? [];

        return [
            'popularity' => $this->evaluateArtist($artistData),
            'matches_preferred_genres' => !empty(array_intersect($artistGenres, $preferredGenres)),
            'genres' => $artistGenres,
            'followers' => $followers,
            'releases' => count($releases),
            'songs' => count($songs),
        ];
    }


    /**
     * Get releases of an artist by their Spotify ID
     */
    public function getReleases(string $artistId): array
    {
        try {
            $response = $this->httpClient->get('artists/' . $artistId . '/albums', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Accept' => 'application/json',
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['items'] ?? [];
        } catch (RequestException $e) {
            throw new RuntimeException('Failed to retrieve artist releases: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get songs from a release (album or single) by its Spotify ID
     */
    public function getSongsFromRelease(string $releaseId): array
    {
        try {
            $response = $this->httpClient->get('albums/' . $releaseId . '/tracks', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->accessToken,
                    'Accept' => 'application/json',
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['items'] ?? [];
        } catch (RequestException $e) {
            throw new RuntimeException('Failed to retrieve songs from release: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get metrics of a release (album or single)
     */
    public function getMetricsOfRelease(array $releaseData): array
    {
        $name = $releaseData['name'] ?? 'Unknown';
        $releaseDate = $releaseData['release_date'] ?? 'Unknown';
        $totalTracks = $releaseData['total_tracks'] ?? 0;
        $popularity = $releaseData['popularity'] ?? 0;

        return [
            'name' => $name,
            'release_date' => $releaseDate,
            'total_tracks' => $totalTracks,
            'popularity' => $popularity,
        ];
    }

    /**
     * Get metrics of a song (track)
     */
    public function getMetricsOfSong(array $songData): array
    {
        $name = $songData['name'] ?? 'Unknown';
        $durationMs = $songData['duration_ms'] ?? 0;
        $explicit = $songData['explicit'] ?? false;
        $popularity = $songData['popularity'] ?? 0;

        return [
            'name' => $name,
            'duration' => $durationMs / 1000, // Convert duration to seconds
            'explicit' => $explicit,
            'popularity' => $popularity,
        ];
    }
}