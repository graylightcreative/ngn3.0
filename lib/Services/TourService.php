<?php

namespace NGN\Lib\Services;

use Ramsey\Uuid\Uuid;

/**
 * TourService
 *
 * Handles tour management including CRUD operations, tour date management,
 * route calculation, statistics tracking, and publishing workflows.
 *
 * Related: Bible Ch. 10 (Touring Ecosystem), Ch. 11 (Booking Workflows)
 */
class TourService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new tour
     *
     * @param array $data Tour data
     * @return array Created tour
     * @throws \Exception
     */
    public function createTour(array $data): array
    {
        if (empty($data['name']) || empty($data['artist_id'])) {
            throw new \Exception("Tour name and artist_id are required");
        }

        if (empty($data['tour_starts_at']) || empty($data['tour_ends_at'])) {
            throw new \Exception("tour_starts_at and tour_ends_at are required");
        }

        $tourId = Uuid::uuid4()->toString();
        $slug = $this->generateSlug($data['name']);

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.tours (
                id, slug, name, description,
                artist_id, label_id,
                tour_starts_at, tour_ends_at,
                image_url, banner_url,
                status, genres_json, tags_json,
                total_dates, total_tickets_sold, total_capacity
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?,
                ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $tourId,
            $slug,
            $data['name'],
            $data['description'] ?? null,
            $data['artist_id'],
            $data['label_id'] ?? null,
            $data['tour_starts_at'],
            $data['tour_ends_at'],
            $data['image_url'] ?? null,
            $data['banner_url'] ?? null,
            $data['status'] ?? 'planning',
            json_encode($data['genres_json'] ?? []),
            json_encode($data['tags_json'] ?? []),
            0,
            0,
            0
        ]);

        return $this->getTour($tourId);
    }

    /**
     * Update a tour
     */
    public function updateTour(string $tourId, array $data): array
    {
        $allowedFields = [
            'name', 'description',
            'label_id',
            'tour_starts_at', 'tour_ends_at',
            'image_url', 'banner_url',
            'status', 'cancelled_reason',
            'genres_json', 'tags_json'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = in_array($field, ['genres_json', 'tags_json'])
                    ? json_encode($data[$field])
                    : $data[$field];
            }
        }

        if (empty($updates)) {
            throw new \Exception("No valid fields to update");
        }

        $params[] = $tourId;

        $sql = "UPDATE ngn_2025.tours SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->getTour($tourId);
    }

    /**
     * Delete a tour
     */
    public function deleteTour(string $tourId): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM ngn_2025.tours WHERE id = ?");
        $stmt->execute([$tourId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Get tour by ID
     */
    public function getTour(string $tourId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, a.name as artist_name, l.name as label_name
            FROM ngn_2025.tours t
            LEFT JOIN ngn_2025.artists a ON a.id = t.artist_id
            LEFT JOIN ngn_2025.labels l ON l.id = t.label_id
            WHERE t.id = ?
        ");
        $stmt->execute([$tourId]);
        $tour = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($tour) {
            $tour['genres_json'] = json_decode($tour['genres_json'] ?? '[]', true);
            $tour['tags_json'] = json_decode($tour['tags_json'] ?? '[]', true);
            $tour['dates'] = $this->getTourDates($tourId);
            $this->calculateTourStats($tour);
        }

        return $tour ?: null;
    }

    /**
     * Get tour by slug
     */
    public function getTourBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT t.*, a.name as artist_name, l.name as label_name
            FROM ngn_2025.tours t
            LEFT JOIN ngn_2025.artists a ON a.id = t.artist_id
            LEFT JOIN ngn_2025.labels l ON l.id = t.label_id
            WHERE t.slug = ?
        ");
        $stmt->execute([$slug]);
        $tour = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($tour) {
            $tour['genres_json'] = json_decode($tour['genres_json'] ?? '[]', true);
            $tour['tags_json'] = json_decode($tour['tags_json'] ?? '[]', true);
            $tour['dates'] = $this->getTourDates($tour['id']);
            $this->calculateTourStats($tour);
        }

        return $tour ?: null;
    }

    /**
     * List tours with filters
     */
    public function listTours(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT t.*, a.name as artist_name, l.name as label_name
            FROM ngn_2025.tours t
            LEFT JOIN ngn_2025.artists a ON a.id = t.artist_id
            LEFT JOIN ngn_2025.labels l ON l.id = t.label_id
            WHERE 1=1
        ";

        $params = [];

        if (isset($filters['artist_id'])) {
            $sql .= " AND t.artist_id = ?";
            $params[] = $filters['artist_id'];
        }

        if (isset($filters['label_id'])) {
            $sql .= " AND t.label_id = ?";
            $params[] = $filters['label_id'];
        }

        if (isset($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['upcoming']) && $filters['upcoming']) {
            $sql .= " AND t.tour_ends_at >= CURDATE()";
        }

        if (isset($filters['past']) && $filters['past']) {
            $sql .= " AND t.tour_ends_at < CURDATE()";
        }

        $sql .= " ORDER BY t.tour_starts_at ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $tours = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($tours as &$tour) {
            $tour['genres_json'] = json_decode($tour['genres_json'] ?? '[]', true);
            $tour['tags_json'] = json_decode($tour['tags_json'] ?? '[]', true);
            $tour['dates'] = $this->getTourDates($tour['id']);
            $this->calculateTourStats($tour);
        }

        return $tours;
    }

    /**
     * Get tour dates for a tour
     */
    public function getTourDates(string $tourId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT td.*, e.title as event_title, e.starts_at as event_date,
                   e.city, e.region, e.venue_id,
                   v.name as venue_name,
                   e.tickets_sold, e.total_capacity
            FROM ngn_2025.tour_dates td
            LEFT JOIN ngn_2025.events e ON e.id = td.event_id
            LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
            WHERE td.tour_id = ?
            ORDER BY td.position ASC
        ");
        $stmt->execute([$tourId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add event to tour
     */
    public function addTourDate(string $tourId, string $eventId, int $position): bool
    {
        // Get the tour to calculate day_number
        $tour = $this->getTour($tourId);
        if (!$tour) {
            throw new \Exception("Tour not found");
        }

        // Check if event already in tour
        $stmt = $this->pdo->prepare("
            SELECT id FROM ngn_2025.tour_dates
            WHERE tour_id = ? AND event_id = ?
        ");
        $stmt->execute([$tourId, $eventId]);
        if ($stmt->fetch()) {
            throw new \Exception("Event already in tour");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.tour_dates (
                tour_id, event_id, position, day_number, is_day_off
            ) VALUES (?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            $tourId,
            $eventId,
            $position,
            $position  // day_number same as position initially
        ]);

        // Recalculate route
        $this->calculateRoute($tourId);

        return $stmt->rowCount() > 0;
    }

    /**
     * Remove event from tour
     */
    public function removeTourDate(string $tourId, string $eventId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM ngn_2025.tour_dates
            WHERE tour_id = ? AND event_id = ?
        ");
        $stmt->execute([$tourId, $eventId]);

        // Recalculate route
        $this->calculateRoute($tourId);

        return $stmt->rowCount() > 0;
    }

    /**
     * Reorder tour dates
     */
    public function reorderTourDates(string $tourId, array $eventIdOrder): bool
    {
        $position = 1;
        foreach ($eventIdOrder as $eventId) {
            $stmt = $this->pdo->prepare("
                UPDATE ngn_2025.tour_dates
                SET position = ?, day_number = ?
                WHERE tour_id = ? AND event_id = ?
            ");
            $stmt->execute([$position, $position, $tourId, $eventId]);
            $position++;
        }

        // Recalculate route
        $this->calculateRoute($tourId);

        return true;
    }

    /**
     * Calculate route distances between tour dates
     *
     * Uses Haversine formula to calculate distance between consecutive tour dates
     * Stores distance and estimated drive time in tour_dates
     */
    public function calculateRoute(string $tourId): array
    {
        $dates = $this->getTourDates($tourId);
        $route = [];

        for ($i = 0; $i < count($dates); $i++) {
            $date = $dates[$i];

            if ($i > 0 && !$dates[$i - 1]['is_day_off'] && !$date['is_day_off']) {
                $previousDate = $dates[$i - 1];

                // Calculate distance using Haversine formula
                if ($previousDate['longitude'] && $previousDate['latitude'] && $date['longitude'] && $date['latitude']) {
                    $distance = $this->haversineDistance(
                        (float)$previousDate['latitude'],
                        (float)$previousDate['longitude'],
                        (float)$date['latitude'],
                        (float)$date['longitude']
                    );

                    // Estimate drive time: ~60 mph average
                    $driveTime = $distance / 60;

                    // Update tour_dates
                    $stmt = $this->pdo->prepare("
                        UPDATE ngn_2025.tour_dates
                        SET distance_from_previous_km = ?, drive_time_hours = ?
                        WHERE tour_id = ? AND event_id = ?
                    ");
                    $stmt->execute([$distance, $driveTime, $tourId, $date['event_id']]);

                    $route[] = [
                        'from' => $previousDate['event_title'] . ' (' . $previousDate['city'] . ')',
                        'to' => $date['event_title'] . ' (' . $date['city'] . ')',
                        'distance_km' => round($distance, 2),
                        'drive_time_hours' => round($driveTime, 2)
                    ];
                }
            }
        }

        return $route;
    }

    /**
     * Haversine formula to calculate distance between two points
     *
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     * @return float Distance in kilometers
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371; // Earth's radius in km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $R * $c;

        return $distance;
    }

    /**
     * Get tour statistics
     */
    public function getTourStats(string $tourId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(DISTINCT td.event_id) as total_dates,
                COALESCE(SUM(e.tickets_sold), 0) as total_tickets_sold,
                COALESCE(SUM(e.total_capacity), 0) as total_capacity,
                COUNT(DISTINCT e.city) as cities_visited,
                COUNT(DISTINCT e.region) as regions_visited
            FROM ngn_2025.tour_dates td
            LEFT JOIN ngn_2025.events e ON e.id = td.event_id
            WHERE td.tour_id = ?
        ");
        $stmt->execute([$tourId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($stats && $stats['total_capacity'] > 0) {
            $stats['sellout_percentage'] = round(($stats['total_tickets_sold'] / $stats['total_capacity']) * 100, 2);
        } else {
            $stats['sellout_percentage'] = 0;
        }

        return $stats;
    }

    /**
     * Update tour stats in database
     */
    public function updateTourStats(string $tourId): bool
    {
        $stats = $this->getTourStats($tourId);

        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tours
            SET total_dates = ?,
                total_tickets_sold = ?,
                total_capacity = ?,
                sellout_percentage = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $stats['total_dates'],
            $stats['total_tickets_sold'],
            $stats['total_capacity'],
            $stats['sellout_percentage'],
            $tourId
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Calculate tour stats inline (for getTour response)
     */
    private function calculateTourStats(&$tour): void
    {
        $totalTickets = 0;
        $totalCapacity = 0;

        foreach ($tour['dates'] as $date) {
            $totalTickets += (int)($date['tickets_sold'] ?? 0);
            $totalCapacity += (int)($date['total_capacity'] ?? 0);
        }

        $sellout = 0;
        if ($totalCapacity > 0) {
            $sellout = round(($totalTickets / $totalCapacity) * 100, 2);
        }

        $tour['total_tickets_sold'] = $totalTickets;
        $tour['total_capacity'] = $totalCapacity;
        $tour['sellout_percentage'] = $sellout;
        $tour['cities_visited'] = count(array_unique(array_filter(array_column($tour['dates'], 'city'))));
    }

    /**
     * Announce tour (status: planning → announced)
     */
    public function announceTour(string $tourId): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tours
            SET status = 'announced',
                announced_at = COALESCE(announced_at, NOW())
            WHERE id = ?
        ");
        $stmt->execute([$tourId]);

        return $this->getTour($tourId);
    }

    /**
     * Put tour on sale (status: announced → on_sale)
     */
    public function putOnSale(string $tourId): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tours
            SET status = 'on_sale'
            WHERE id = ?
        ");
        $stmt->execute([$tourId]);

        return $this->getTour($tourId);
    }

    /**
     * Mark tour as in progress (status: on_sale → in_progress)
     */
    public function markInProgress(string $tourId): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tours
            SET status = 'in_progress'
            WHERE id = ?
        ");
        $stmt->execute([$tourId]);

        return $this->getTour($tourId);
    }

    /**
     * Mark tour as completed (status: in_progress → completed)
     */
    public function markCompleted(string $tourId): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tours
            SET status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$tourId]);

        return $this->getTour($tourId);
    }

    /**
     * Cancel a tour
     */
    public function cancelTour(string $tourId, ?string $reason = null): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.tours
            SET status = 'cancelled',
                cancelled_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $tourId]);

        return $this->getTour($tourId);
    }

    /**
     * Generate unique slug from name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        // Check if slug exists
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM ngn_2025.tours WHERE slug LIKE ?");
        $stmt->execute([$slug . '%']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $slug .= '-' . uniqid();
        }

        return $slug;
    }

    /**
     * Search tours by location
     */
    public function searchToursByLocation(string $city, ?string $region = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $sql = "
            SELECT DISTINCT t.*, a.name as artist_name
            FROM ngn_2025.tours t
            LEFT JOIN ngn_2025.cdm_artists a ON a.id = t.artist_id
            LEFT JOIN ngn_2025.tour_dates td ON td.tour_id = t.id
            LEFT JOIN ngn_2025.events e ON e.id = td.event_id
            WHERE t.status IN ('announced', 'on_sale')
            AND e.city = ?
        ";

        $params = [$city];

        if ($region) {
            $sql .= " AND e.region = ?";
            $params[] = $region;
        }

        if ($dateFrom) {
            $sql .= " AND e.starts_at >= ?";
            $params[] = $dateFrom;
        }

        if ($dateTo) {
            $sql .= " AND e.starts_at <= ?";
            $params[] = $dateTo;
        }

        $sql .= " ORDER BY t.tour_starts_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
