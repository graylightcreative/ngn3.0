<?php

namespace NGN\Lib\Services;

use Ramsey\Uuid\Uuid;

/**
 * BookingService
 *
 * Handles booking request workflow including CRUD operations, negotiation,
 * messaging, and event creation from confirmed bookings.
 *
 * Related: Bible Ch. 11 (Booking Workflows), Ch. 10 (Touring Ecosystem)
 */
class BookingService
{
    private \PDO $pdo;
    private EventService $eventService;

    public function __construct(\PDO $pdo, EventService $eventService)
    {
        $this->pdo = $pdo;
        $this->eventService = $eventService;
    }

    /**
     * Create a new booking request
     *
     * @param array $data Booking request data
     * @return array Created booking
     * @throws \Exception
     */
    public function createBooking(array $data): array
    {
        if (empty($data['artist_id']) || empty($data['venue_id']) || empty($data['requested_date'])) {
            throw new \Exception("artist_id, venue_id, and requested_date are required");
        }

        if (empty($data['requesting_party']) || !in_array($data['requesting_party'], ['artist', 'venue'])) {
            throw new \Exception("requesting_party must be 'artist' or 'venue'");
        }

        // Check if venue/date already booked
        if (!$this->checkDateAvailability($data['venue_id'], $data['requested_date'])) {
            throw new \Exception("Venue already has an event on this date");
        }

        $bookingId = Uuid::uuid4()->toString();

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.booking_requests (
                id, artist_id, venue_id, requesting_party,
                requested_date, alternative_dates, preferred_door_time,
                expected_attendance, offer_type,
                guarantee_amount_cents, door_split_percentage, ticket_price_suggested_cents,
                status, request_message, metadata
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?,
                ?, ?, ?,
                ?, ?, ?
            )
        ");

        $stmt->execute([
            $bookingId,
            $data['artist_id'],
            $data['venue_id'],
            $data['requesting_party'],
            $data['requested_date'],
            isset($data['alternative_dates']) ? json_encode($data['alternative_dates']) : null,
            $data['preferred_door_time'] ?? null,
            $data['expected_attendance'] ?? null,
            $data['offer_type'] ?? 'door_split',
            $data['guarantee_amount_cents'] ?? null,
            $data['door_split_percentage'] ?? null,
            $data['ticket_price_suggested_cents'] ?? null,
            'pending',
            $data['request_message'] ?? null,
            isset($data['metadata']) ? json_encode($data['metadata']) : null
        ]);

        return $this->getBooking($bookingId);
    }

    /**
     * Get booking by ID
     */
    public function getBooking(string $bookingId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT br.*,
                   a.name as artist_name,
                   v.name as venue_name,
                   e.title as event_title
            FROM ngn_2025.booking_requests br
            LEFT JOIN ngn_2025.artists a ON a.id = br.artist_id
            LEFT JOIN ngn_2025.venues v ON v.id = br.venue_id
            LEFT JOIN ngn_2025.events e ON e.id = br.event_id
            WHERE br.id = ?
        ");
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($booking) {
            $booking['alternative_dates'] = json_decode($booking['alternative_dates'] ?? '[]', true);
            $booking['metadata'] = json_decode($booking['metadata'] ?? '{}', true);
            $booking['message_count'] = $this->getMessageCount($bookingId);
        }

        return $booking ?: null;
    }

    /**
     * List bookings with filters
     */
    public function listBookings(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $sql = "
            SELECT br.*,
                   a.name as artist_name,
                   v.name as venue_name
            FROM ngn_2025.booking_requests br
            LEFT JOIN ngn_2025.artists a ON a.id = br.artist_id
            LEFT JOIN ngn_2025.venues v ON v.id = br.venue_id
            WHERE 1=1
        ";

        $params = [];

        // Filter for entity inbox (artist or venue)
        if (isset($filters['entity_type']) && isset($filters['entity_id'])) {
            if ($filters['entity_type'] === 'artist') {
                $sql .= " AND br.artist_id = ?";
                $params[] = $filters['entity_id'];
            } elseif ($filters['entity_type'] === 'venue') {
                $sql .= " AND br.venue_id = ?";
                $params[] = $filters['entity_id'];
            }
        }

        if (isset($filters['status'])) {
            $sql .= " AND br.status = ?";
            $params[] = $filters['status'];
        }

        if (isset($filters['artist_id'])) {
            $sql .= " AND br.artist_id = ?";
            $params[] = $filters['artist_id'];
        }

        if (isset($filters['venue_id'])) {
            $sql .= " AND br.venue_id = ?";
            $params[] = $filters['venue_id'];
        }

        $sql .= " ORDER BY br.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($bookings as &$booking) {
            $booking['alternative_dates'] = json_decode($booking['alternative_dates'] ?? '[]', true);
            $booking['metadata'] = json_decode($booking['metadata'] ?? '{}', true);
        }

        return $bookings;
    }

    /**
     * Accept a booking request
     */
    public function acceptBooking(string $bookingId, ?string $responseMessage = null): array
    {
        $booking = $this->getBooking($bookingId);
        if (!$booking) {
            throw new \Exception("Booking not found");
        }

        if (!in_array($booking['status'], ['pending', 'negotiating'])) {
            throw new \Exception("Cannot accept booking with status: " . $booking['status']);
        }

        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.booking_requests
            SET status = 'accepted',
                response_message = ?,
                responded_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$responseMessage, $bookingId]);

        return $this->getBooking($bookingId);
    }

    /**
     * Reject a booking request
     */
    public function rejectBooking(string $bookingId, ?string $reason = null): array
    {
        $booking = $this->getBooking($bookingId);
        if (!$booking) {
            throw new \Exception("Booking not found");
        }

        if (!in_array($booking['status'], ['pending', 'negotiating', 'accepted'])) {
            throw new \Exception("Cannot reject booking with status: " . $booking['status']);
        }

        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.booking_requests
            SET status = 'rejected',
                rejection_reason = ?,
                responded_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reason, $bookingId]);

        return $this->getBooking($bookingId);
    }

    /**
     * Send counter-offer on booking
     */
    public function counterOffer(string $bookingId, array $newTerms, string $message): array
    {
        $booking = $this->getBooking($bookingId);
        if (!$booking) {
            throw new \Exception("Booking not found");
        }

        if (!in_array($booking['status'], ['pending', 'negotiating', 'accepted'])) {
            throw new \Exception("Cannot send counter-offer for booking with status: " . $booking['status']);
        }

        // Update booking status to negotiating
        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.booking_requests
            SET status = 'negotiating'
            WHERE id = ?
        ");
        $stmt->execute([$bookingId]);

        // Send message with counter-offer
        $this->sendMessage(
            $bookingId,
            $booking['requesting_party'] === 'artist' ? 'venue' : 'artist',
            $booking['requesting_party'] === 'artist' ? $booking['venue_id'] : $booking['artist_id'],
            $message,
            $newTerms
        );

        return $this->getBooking($bookingId);
    }

    /**
     * Confirm booking and create event
     */
    public function confirmBooking(string $bookingId): array
    {
        $booking = $this->getBooking($bookingId);
        if (!$booking) {
            throw new \Exception("Booking not found");
        }

        if ($booking['status'] !== 'accepted') {
            throw new \Exception("Cannot confirm booking that is not accepted");
        }

        // Create event from booking
        $eventData = [
            'title' => $booking['artist_name'] . ' @ ' . $booking['venue_name'],
            'description' => 'Booked event',
            'venue_id' => $booking['venue_id'],
            'venue_name' => $booking['venue_name'],
            'starts_at' => $booking['requested_date'] . ' ' . ($booking['preferred_door_time'] ?? '20:00:00'),
            'doors_at' => $booking['requested_date'] . ' ' . ($booking['preferred_door_time'] ?? '20:00:00'),
            'timezone' => 'America/New_York',
            'enable_ticketing' => 1,
            'total_capacity' => 500,
            'base_price_cents' => $booking['ticket_price_suggested_cents'] ?? 2000,
            'status' => 'draft'
        ];

        // Add booking terms to metadata
        $metadata = [
            'booking_id' => $bookingId,
            'offer_type' => $booking['offer_type'],
            'guarantee_amount_cents' => $booking['guarantee_amount_cents'],
            'door_split_percentage' => $booking['door_split_percentage']
        ];
        $eventData['metadata'] = $metadata;

        try {
            $event = $this->eventService->createEvent($eventData);
            $eventId = $event['id'];

            // Add artist to lineup
            $this->eventService->addToLineup($eventId, [
                'artist_id' => $booking['artist_id'],
                'is_headliner' => 1,
                'position' => 0
            ]);

            // Update booking with event_id and status
            $stmt = $this->pdo->prepare("
                UPDATE ngn_2025.booking_requests
                SET status = 'confirmed',
                    event_id = ?,
                    confirmed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$eventId, $bookingId]);

            return $this->getBooking($bookingId);
        } catch (\Exception $e) {
            throw new \Exception("Failed to create event from booking: " . $e->getMessage());
        }
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(string $bookingId, ?string $reason = null): array
    {
        $booking = $this->getBooking($bookingId);
        if (!$booking) {
            throw new \Exception("Booking not found");
        }

        if ($booking['status'] === 'rejected' || $booking['status'] === 'cancelled') {
            throw new \Exception("Cannot cancel booking with status: " . $booking['status']);
        }

        $stmt = $this->pdo->prepare("
            UPDATE ngn_2025.booking_requests
            SET status = 'cancelled',
                cancellation_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$reason, $bookingId]);

        return $this->getBooking($bookingId);
    }

    /**
     * Send message on booking
     */
    public function sendMessage(
        string $bookingId,
        string $senderType,
        int $senderId,
        string $message,
        ?array $counterOffer = null
    ): array {
        if (!in_array($senderType, ['artist', 'venue'])) {
            throw new \Exception("senderType must be 'artist' or 'venue'");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO ngn_2025.booking_messages (
                booking_request_id, sender_type, sender_id,
                message, is_counter_offer, counter_offer_json
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $bookingId,
            $senderType,
            $senderId,
            $message,
            $counterOffer ? 1 : 0,
            $counterOffer ? json_encode($counterOffer) : null
        ]);

        return $this->getMessages($bookingId);
    }

    /**
     * Get messages for booking
     */
    public function getMessages(string $bookingId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT bm.*
            FROM ngn_2025.booking_messages bm
            WHERE bm.booking_request_id = ?
            ORDER BY bm.created_at ASC
        ");
        $stmt->execute([$bookingId]);
        $messages = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($messages as &$msg) {
            $msg['counter_offer_json'] = json_decode($msg['counter_offer_json'] ?? '{}', true);
        }

        return $messages;
    }

    /**
     * Get message count for booking
     */
    private function getMessageCount(string $bookingId): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM ngn_2025.booking_messages
            WHERE booking_request_id = ?
        ");
        $stmt->execute([$bookingId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Check if date is available at venue
     */
    public function checkDateAvailability(int $venueId, string $date): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM ngn_2025.events
            WHERE venue_id = ?
            AND DATE(starts_at) = ?
            AND status NOT IN ('cancelled', 'postponed')
        ");
        $stmt->execute([$venueId, $date]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($result['count'] ?? 0) === 0;
    }

    /**
     * Get all booked dates for venue within date range
     */
    public function getVenueBookedDates(int $venueId, string $startDate, string $endDate): array
    {
        $stmt = $this->pdo->prepare("
            SELECT DATE(e.starts_at) as booked_date,
                   e.id, e.title
            FROM ngn_2025.events e
            WHERE e.venue_id = ?
            AND DATE(e.starts_at) >= ?
            AND DATE(e.starts_at) <= ?
            AND e.status NOT IN ('cancelled', 'postponed')
            ORDER BY e.starts_at ASC
        ");
        $stmt->execute([$venueId, $startDate, $endDate]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get booking request count by status
     */
    public function getBookingCountByStatus(int $venueId, string $status): int
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM ngn_2025.booking_requests
            WHERE venue_id = ?
            AND status = ?
        ");
        $stmt->execute([$venueId, $status]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    /**
     * Get upcoming bookings for venue
     */
    public function getUpcomingBookings(int $venueId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare("
            SELECT br.*,
                   a.name as artist_name
            FROM ngn_2025.booking_requests br
            LEFT JOIN ngn_2025.artists a ON a.id = br.artist_id
            WHERE br.venue_id = ?
            AND br.requested_date >= CURDATE()
            AND br.status IN ('pending', 'negotiating', 'accepted')
            ORDER BY br.requested_date ASC
            LIMIT ?
        ");
        $stmt->execute([$venueId, $limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get completed bookings between artist and venue
     */
    public function getHistoricalBookings(int $artistId, int $venueId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT br.*
            FROM ngn_2025.booking_requests br
            WHERE br.artist_id = ?
            AND br.venue_id = ?
            AND br.status = 'confirmed'
            ORDER BY br.confirmed_at DESC
        ");
        $stmt->execute([$artistId, $venueId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
