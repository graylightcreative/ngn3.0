<?php
namespace NGN\Lib\Spins;

class SpinValidator
{
    /**
     * Validate a spin payload.
     * Expected keys: station_id (int > 0), track_id (int > 0), played_at (ISO8601 string, UTC preferred)
     * Optional: artist_id (int > 0), notes (string)
     *
     * @param array $input
     * @return array [bool $valid, array $errors, array $normalized]
     */
    public function validate(array $input): array
    {
        $errors = [];
        $norm = [];

        // station_id can come from URL; must be positive int if present
        if (isset($input['station_id'])) {
            $stationId = (int)$input['station_id'];
            if ($stationId <= 0) {
                $errors[] = ['field' => 'station_id', 'message' => 'station_id must be a positive integer'];
            } else {
                $norm['station_id'] = $stationId;
            }
        }

        // track_id required
        if (!isset($input['track_id'])) {
            $errors[] = ['field' => 'track_id', 'message' => 'track_id is required'];
        } else {
            $trackId = (int)$input['track_id'];
            if ($trackId <= 0) {
                $errors[] = ['field' => 'track_id', 'message' => 'track_id must be a positive integer'];
            } else {
                $norm['track_id'] = $trackId;
            }
        }

        // artist_id optional
        if (isset($input['artist_id'])) {
            $artistId = (int)$input['artist_id'];
            if ($artistId <= 0) {
                $errors[] = ['field' => 'artist_id', 'message' => 'artist_id must be a positive integer'];
            } else {
                $norm['artist_id'] = $artistId;
            }
        }

        // played_at required (ISO8601)
        if (!isset($input['played_at']) || !is_string($input['played_at']) || trim($input['played_at']) === '') {
            $errors[] = ['field' => 'played_at', 'message' => 'played_at is required (ISO8601)'];
        } else {
            $ts = strtotime($input['played_at']);
            if ($ts === false) {
                $errors[] = ['field' => 'played_at', 'message' => 'played_at must be a valid ISO8601 datetime'];
            } else {
                // Sanity checks: not too far in the future, not unreasonably old
                $now = time();
                if ($ts > $now + 300) { // >5 minutes future
                    $errors[] = ['field' => 'played_at', 'message' => 'played_at cannot be in the future'];
                }
                $oneYearAgo = $now - 365*24*3600;
                if ($ts < $oneYearAgo) {
                    $errors[] = ['field' => 'played_at', 'message' => 'played_at is too far in the past'];
                }
                $norm['played_at_iso'] = gmdate('c', $ts);
                $norm['played_at_epoch'] = $ts;
            }
        }

        // notes optional
        if (isset($input['notes'])) {
            $notes = (string)$input['notes'];
            if (strlen($notes) > 1000) {
                $errors[] = ['field' => 'notes', 'message' => 'notes must be <= 1000 characters'];
            } else {
                $norm['notes'] = $notes;
            }
        }

        $valid = empty($errors);
        return [$valid, $errors, $valid ? $norm : []];
    }
}
