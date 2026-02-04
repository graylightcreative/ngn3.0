<?php

namespace NGN\Lib\Services;

use Ramsey\Uuid\Uuid;

/**
 * EventService
 *
 * Handles event/concert management including CRUD operations, lineup management,
 * capacity tracking, and publishing workflows.
 *
 * Related: Bible Ch. 8 (Ticket Architecture), Ch. 9 (Touring Ecosystem)
 */
class EventService
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new event
     *
     * @param array $data Event data
     * @return array Created event
     * @throws \Exception
     */
    public function createEvent(array $data): array
    {
        $eventId = Uuid::uuid4()->toString();
        $slug = $this->generateSlug($data['title']);

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.events (
                id, slug, title, description,
                venue_id, venue_name, address, city, region, country, postal_code,
                latitude, longitude,
                starts_at, ends_at, doors_at, timezone,
                enable_ticketing, ticket_sales_start_at, ticket_sales_end_at,
                total_capacity, ngn_allocation,
                base_price_cents, ngn_fee_cents, currency,
                status, image_url, banner_url,
                is_all_ages, age_restriction, indemnity_accepted,
                metadata
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?
            )
        ");

        $stmt->execute([
            $eventId,
            $slug,
            $data['title'],
            $data['description'] ?? null,
            $data['venue_id'] ?? null,
            $data['venue_name'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['region'] ?? null,
            $data['country'] ?? 'US',
            $data['postal_code'] ?? null,
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['starts_at'],
            $data['ends_at'] ?? null,
            $data['doors_at'] ?? null,
            $data['timezone'] ?? 'America/New_York',
            $data['enable_ticketing'] ?? 0,
            $data['ticket_sales_start_at'] ?? null,
            $data['ticket_sales_end_at'] ?? null,
            $data['total_capacity'] ?? null,
            $data['ngn_allocation'] ?? null,
            $data['base_price_cents'] ?? null,
            $data['ngn_fee_cents'] ?? null,
            $data['currency'] ?? 'USD',
            $data['status'] ?? 'draft',
            $data['image_url'] ?? null,
            $data['banner_url'] ?? null,
            $data['is_all_ages'] ?? 0,
            $data['age_restriction'] ?? null,
            $data['indemnity_accepted'] ?? 0,
            json_encode($data['metadata'] ?? [])
        ]);

        return $this->getEvent($eventId);
    }

    /**
     * Update an event
     */
    public function updateEvent(string $eventId, array $data): array
    {
        $allowedFields = [
            'title', 'description', 'venue_id', 'venue_name',
            'address', 'city', 'region', 'country', 'postal_code',
            'latitude', 'longitude',
            'starts_at', 'ends_at', 'doors_at', 'timezone',
            'enable_ticketing', 'ticket_sales_start_at', 'ticket_sales_end_at',
            'total_capacity', 'ngn_allocation',
            'base_price_cents', 'ngn_fee_cents', 'currency',
            'status', 'cancellation_reason',
            'image_url', 'banner_url',
            'is_all_ages', 'age_restriction', 'indemnity_accepted',
            'metadata'
        ];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $field === 'metadata' ? json_encode($data[$field]) : $data[$field];
            }
        }

        if (empty($updates)) {
            throw new \Exception("No valid fields to update");
        }

        $params[] = $eventId;

        $sql = "UPDATE ngn_2025.events SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->getEvent($eventId);
    }

    /**
     * Delete an event
     */
    public function deleteEvent(string $eventId): bool
    {
        // Check if event has tickets sold
        $stmt = $this->pdo->prepare("
            SELECT tickets_sold FROM ngn_2025.events WHERE id = ?
        ");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($event && $event['tickets_sold'] > 0) {
            throw new \Exception("Cannot delete event with sold tickets. Cancel it instead.");
        }

        $stmt = $this->pdo->prepare("DELETE FROM ngn_2025.events WHERE id = ?");
        $stmt->execute([$eventId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get event by ID
     */
    public function getEvent(string $eventId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, v.name as venue_full_name, v.city as venue_city
            FROM ngn_2025.events e
            LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
            WHERE e.id = ?
        ");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($event) {
            $event['lineup'] = $this->getLineup($eventId);
            $event['metadata'] = json_decode($event['metadata'] ?? '{}', true);
        }

        return $event ?: null;
    }

    /**
     * Get event by slug
     */
    public function getEventBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT e.*, v.name as venue_full_name, v.city as venue_city
            FROM ngn_2025.events e
            LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
            WHERE e.slug = ?
        ");
        $stmt->execute([$slug]);
        $event = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($event) {
            $event['lineup'] = $this->getLineup($event['id']);
            $event['metadata'] = json_decode($event['metadata'] ?? '{}', true);
        }

        return $event ?: null;
    }

    /**
     * List events with filters
     */
    public function listEvents(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT e.*, v.name as venue_full_name, v.city as venue_city
            FROM ngn_2025.events e
            LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
            WHERE 1=1
        ";

        $params = [];

        if (isset($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['venue_id'])) {
            $sql .= " AND e.venue_id = ?";
            $params[] = $filters['venue_id'];
        }

        if (isset($filters['city'])) {
            $sql .= " AND e.city = ?";
            $params[] = $filters['city'];
        }

        if (isset($filters['upcoming']) && $filters['upcoming']) {
            $sql .= " AND e.starts_at >= NOW()";
        }

        if (isset($filters['past']) && $filters['past']) {
            $sql .= " AND e.starts_at < NOW()";
        }

        if (isset($filters['enable_ticketing'])) {
            $sql .= " AND e.enable_ticketing = ?";
            $params[] = $filters['enable_ticketing'];
        }

        $sql .= " ORDER BY e.starts_at ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Publish an event (make it visible)
     */
    public function publishEvent(string $eventId): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.events
            SET status = 'published',
                published_at = COALESCE(published_at, NOW())
            WHERE id = ?
        ");
        $stmt->execute([$eventId]);

        return $this->getEvent($eventId);
    }

    /**
     * Cancel an event
     */
    public function cancelEvent(string $eventId, string $reason = null): array
    {
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.events
            SET status = 'cancelled',
                cancellation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $eventId]);

        return $this->getEvent($eventId);
    }

    /**
     * Add artist to lineup
     */
    public function addToLineup(string $eventId, array $lineupData): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.event_lineup (
                event_id, artist_id, artist_name, position,
                is_headliner, set_time, set_length_minutes, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $eventId,
            $lineupData['artist_id'] ?? null,
            $lineupData['artist_name'] ?? null,
            $lineupData['position'] ?? 0,
            $lineupData['is_headliner'] ?? 0,
            $lineupData['set_time'] ?? null,
            $lineupData['set_length_minutes'] ?? null,
            $lineupData['notes'] ?? null
        ]);

        return $this->getLineup($eventId);
    }

    /**
     * Remove artist from lineup
     */
    public function removeFromLineup(string $eventId, int $lineupId): bool
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM ngn_2025.event_lineup
            WHERE id = ? AND event_id = ?
        ");
        $stmt->execute([$lineupId, $eventId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Update lineup entry
     */
    public function updateLineup(string $eventId, int $lineupId, array $data): array
    {
        $allowedFields = ['position', 'is_headliner', 'set_time', 'set_length_minutes', 'notes'];

        $updates = [];
        $params = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updates[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($updates)) {
            throw new \Exception("No valid fields to update");
        }

        $params[] = $lineupId;
        $params[] = $eventId;

        $sql = "UPDATE ngn_2025.event_lineup SET " . implode(', ', $updates) . " WHERE id = ? AND event_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->getLineup($eventId);
    }

    /**
     * Get lineup for an event
     */
    public function getLineup(string $eventId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT l.*, a.name as artist_full_name
            FROM ngn_2025.event_lineup l
            LEFT JOIN ngn_2025.artists a ON a.id = l.artist_id
            WHERE l.event_id = ?
            ORDER BY l.position ASC
        ");
        $stmt->execute([$eventId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Generate unique slug from title
     */
    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

        // Check if slug exists
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM ngn_2025.events WHERE slug LIKE ?");
        $stmt->execute([$slug . '%']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            $slug .= '-' . uniqid();
        }

        return $slug;
    }

    /**
     * Check if event is sold out
     */
    public function isSoldOut(string $eventId): bool
    {
        $event = $this->getEvent($eventId);

        if (!$event || !$event['ngn_allocation']) {
            return false;
        }

        return $event['tickets_sold'] >= $event['ngn_allocation'];
    }

    /**
     * Get events by artist
     */
    public function getEventsByArtist(int $artistId, bool $upcomingOnly = true): array
    {
        $sql = "
            SELECT e.*, v.name as venue_full_name, v.city as venue_city
            FROM ngn_2025.events e
            LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
            INNER JOIN ngn_2025.event_lineup l ON l.event_id = e.id
            WHERE l.artist_id = ?
            AND e.status NOT IN ('cancelled')
        ";

        if ($upcomingOnly) {
            $sql .= " AND e.starts_at >= NOW()";
        }

        $sql .= " ORDER BY e.starts_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$artistId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get events by venue
     */
    public function getEventsByVenue(int $venueId, bool $upcomingOnly = true): array
    {
        $sql = "
            SELECT e.*, v.name as venue_full_name, v.city as venue_city
            FROM ngn_2025.events e
            LEFT JOIN ngn_2025.venues v ON v.id = e.venue_id
            WHERE e.venue_id = ?
            AND e.status NOT IN ('cancelled')
        ";

        if ($upcomingOnly) {
            $sql .= " AND e.starts_at >= NOW()";
        }

        $sql .= " ORDER BY e.starts_at ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$venueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
