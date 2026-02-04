<?php

namespace NGN\Lib\Discovery;

use NGN\Config;
use NGN\Lib\Database\ConnectionFactory;
use NGN\Lib\Logger\LoggerFactory;
use NGN\Lib\Email\EmailService;
use PDO;
use PDOException;

class NikoDiscoveryService
{
    private PDO $readConnection;
    private PDO $writeConnection;
    private Config $config;
    private DiscoveryEngineService $discoveryEngine;
    private EmailService $emailService;

    public function __construct(Config $config, ?DiscoveryEngineService $discoveryEngine = null, ?EmailService $emailService = null)
    {
        $this->config = $config;
        $this->readConnection = ConnectionFactory::read();
        $this->writeConnection = ConnectionFactory::write();
        $this->discoveryEngine = $discoveryEngine ?? new DiscoveryEngineService($config);
        $this->emailService = $emailService ?? new EmailService($config);
    }

    /**
     * Generate weekly digest for user
     */
    public function generateWeeklyDigest(int $userId): array
    {
        try {
            $currentWeek = date('Y-W');

            // Check if digest already exists for this week
            $stmt = $this->readConnection->prepare(
                'SELECT id FROM `ngn_2025`.`niko_discovery_digests` WHERE user_id = ? AND digest_week = ?'
            );
            $stmt->execute([$userId, $currentWeek]);
            if ($stmt->fetch()) {
                return []; // Already generated
            }

            $featuredArtists = $this->selectFeaturedArtists($userId);

            if (empty($featuredArtists)) {
                return [];
            }

            $subject = $this->generateSubjectLine($userId, $featuredArtists);

            return [
                'user_id' => $userId,
                'featured_artists' => $featuredArtists,
                'digest_week' => $currentWeek,
                'subject_line' => $subject
            ];
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error generating weekly digest', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Select 3 featured artists for digest
     * Criteria: High affinity, emerging status, genre diversity, recent activity
     */
    public function selectFeaturedArtists(int $userId): array
    {
        try {
            $recommendations = $this->discoveryEngine->getRecommendedArtists($userId, 20);
            $emergingArtists = $this->discoveryEngine->getEmergingArtists($userId, 10);

            // Combine and score
            $candidates = [];

            foreach ($recommendations as $rec) {
                $score = ($rec['score'] ?? 0) * 0.4;
                $rec['digest_score'] = $score;
                $rec['reason'] = $rec['reason'] ?? 'Personalized for you';
                $candidates[] = $rec;
            }

            foreach ($emergingArtists as $emerging) {
                $emerging['digest_score'] = ($emerging['ngn_score'] ?? 0) * 0.3;
                $candidates[] = $emerging;
            }

            // Sort by digest score
            usort($candidates, function ($a, $b) {
                return ($b['digest_score'] ?? 0) <=> ($a['digest_score'] ?? 0);
            });

            // Apply diversity rules (max 2 from same genre)
            $featured = [];
            $genreCount = [];

            foreach ($candidates as $artist) {
                try {
                    $stmt = $this->readConnection->prepare(
                        'SELECT primary_genre FROM `ngn_2025`.`artists` WHERE id = ? LIMIT 1'
                    );
                    $stmt->execute([$artist['artist_id']]);
                    $artistData = $stmt->fetch(PDO::FETCH_ASSOC);
                    $genre = $artistData['primary_genre'] ?? 'unknown';
                } catch (PDOException $e) {
                    $genre = 'unknown';
                }

                if (!isset($genreCount[$genre])) {
                    $genreCount[$genre] = 0;
                }

                if ($genreCount[$genre] < 2 && count($featured) < 3) {
                    $featured[] = $artist;
                    $genreCount[$genre]++;
                }

                if (count($featured) === 3) {
                    break;
                }
            }

            return $featured;
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error selecting featured artists', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create digest HTML content
     */
    public function createDigestContent(int $userId, array $artists): string
    {
        try {
            $user = $this->getUser($userId);
            if (!$user) {
                return '';
            }

            $artistCards = '';
            foreach ($artists as $artist) {
                $artistCards .= $this->createArtistCard($artist);
            }

            $topGenres = $this->getUserTopGenres($userId, 2);
            $genreText = implode(', ', $topGenres);

            $template = file_get_contents($this->config->get('paths.templates') . '/niko_discovery_digest.html');

            $content = str_replace(
                ['{USER_NAME}', '{GENRE_TEXT}', '{ARTIST_CARDS}'],
                [htmlspecialchars($user['name'] ?? $user['email']), htmlspecialchars($genreText), $artistCards],
                $template
            );

            return $content;
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error creating digest content', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Send digest to user
     */
    public function sendDigest(int $userId): bool
    {
        try {
            $digest = $this->generateWeeklyDigest($userId);

            if (empty($digest)) {
                LoggerFactory::getLogger('discovery')->info('No digest generated', ['user_id' => $userId]);
                return false;
            }

            $user = $this->getUser($userId);
            if (!$user || !$user['email']) {
                return false;
            }

            $content = $this->createDigestContent($userId, $digest['featured_artists']);

            if (!$content) {
                return false;
            }

            // Send email
            $subject = $digest['subject_line'];
            $success = $this->emailService->send(
                $user['email'],
                $subject,
                $content,
                ['template' => 'niko_discovery_digest']
            );

            if ($success) {
                $this->trackDigestSent($userId, $digest['featured_artists']);
                LoggerFactory::getLogger('discovery')->info('Digest sent', ['user_id' => $userId]);
                return true;
            } else {
                $this->recordDigestError($userId, $digest, 'Email delivery failed');
                return false;
            }
        } catch (Exception $e) {
            LoggerFactory::getLogger('discovery')->error('Error sending digest', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send batch digests to multiple users
     */
    public function sendBatchDigests(array $userIds): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        foreach ($userIds as $userId) {
            if ($this->sendDigest($userId)) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get digest recipients (active users with email enabled)
     */
    public function getDigestRecipients(): array
    {
        try {
            $currentWeek = date('Y-W');

            $stmt = $this->readConnection->prepare(
                'SELECT DISTINCT u.id
                 FROM `ngn_2025`.`users` u
                 WHERE u.status = "active"
                 AND u.email_notifications = 1
                 AND u.last_login > DATE_SUB(NOW(), INTERVAL 30 DAY)
                 AND u.id NOT IN (
                     SELECT user_id FROM `ngn_2025`.`niko_discovery_digests` WHERE digest_week = ?
                 )
                 LIMIT 10000'
            );
            $stmt->execute([$currentWeek]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting digest recipients', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Track digest sent
     */
    public function trackDigestSent(int $userId, array $artists): void
    {
        try {
            $currentWeek = date('Y-W');
            $artistIds = array_column($artists, 'artist_id');

            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`niko_discovery_digests` (user_id, featured_artists, digest_week, subject_line, status, sent_at)
                 VALUES (?, ?, ?, ?, "sent", NOW())
                 ON DUPLICATE KEY UPDATE
                 status = "sent",
                 sent_at = NOW(),
                 featured_artists = VALUES(featured_artists)'
            )->execute([
                $userId,
                json_encode($artists),
                $currentWeek,
                'Niko\'s Discovery: 3 Artists You\'ll Love This Week'
            ]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error tracking digest sent', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Track digest opened
     */
    public function trackDigestOpened(int $userId, string $digestWeek): void
    {
        try {
            $this->writeConnection->prepare(
                'UPDATE `ngn_2025`.`niko_discovery_digests` SET opened_at = NOW() WHERE user_id = ? AND digest_week = ?'
            )->execute([$userId, $digestWeek]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error tracking digest opened', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Track artist click
     */
    public function trackArtistClicked(int $userId, int $artistId, string $digestWeek): void
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT clicked_artist_ids FROM `ngn_2025`.`niko_discovery_digests` WHERE user_id = ? AND digest_week = ?'
            );
            $stmt->execute([$userId, $digestWeek]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $clicked = [];
            if ($result && $result['clicked_artist_ids']) {
                $clicked = json_decode($result['clicked_artist_ids'], true) ?: [];
            }

            if (!in_array($artistId, $clicked)) {
                $clicked[] = $artistId;
            }

            $this->writeConnection->prepare(
                'UPDATE `ngn_2025`.`niko_discovery_digests` SET clicked_artist_ids = ? WHERE user_id = ? AND digest_week = ?'
            )->execute([json_encode($clicked), $userId, $digestWeek]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error tracking artist click', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get digest performance metrics
     */
    public function getDigestPerformance(string $digestWeek): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT
                    COUNT(*) as total_sent,
                    COUNT(CASE WHEN opened_at IS NOT NULL THEN 1 END) as total_opened,
                    COUNT(CASE WHEN clicked_artist_ids IS NOT NULL THEN 1 END) as total_clicked
                 FROM `ngn_2025`.`niko_discovery_digests`
                 WHERE digest_week = ? AND status = "sent"'
            );
            $stmt->execute([$digestWeek]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalSent = (int) ($stats['total_sent'] ?? 0);
            $totalOpened = (int) ($stats['total_opened'] ?? 0);
            $totalClicked = (int) ($stats['total_clicked'] ?? 0);

            return [
                'total_sent' => $totalSent,
                'total_opened' => $totalOpened,
                'total_clicked' => $totalClicked,
                'open_rate' => $totalSent > 0 ? round($totalOpened / $totalSent * 100, 2) : 0,
                'click_rate' => $totalSent > 0 ? round($totalClicked / $totalSent * 100, 2) : 0
            ];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting digest performance', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get user's digest history
     */
    public function getUserDigestHistory(int $userId, int $limit = 10): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT id, user_id, digest_week, subject_line, status, sent_at, opened_at, clicked_artist_ids FROM `ngn_2025`.`niko_discovery_digests`
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error getting user digest history', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Helper: Get user data
     */
    private function getUser(int $userId): ?array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT id, email, display_name AS name FROM `ngn_2025`.`users` WHERE id = ? LIMIT 1'
            );
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Helper: Get user's top genres
     */
    private function getUserTopGenres(int $userId, int $limit = 2): array
    {
        try {
            $stmt = $this->readConnection->prepare(
                'SELECT genre_name FROM `ngn_2025`.`user_genre_affinity`
                 WHERE user_id = ?
                 ORDER BY affinity_score DESC
                 LIMIT ?'
            );
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Helper: Create artist card HTML
     */
    private function createArtistCard(array $artist): string
    {
        $name = htmlspecialchars($artist['artist_name'] ?? '');
        $genre = htmlspecialchars($artist['genre'] ?? 'Music');
        $reason = htmlspecialchars($artist['reason'] ?? 'Personalized for you');

        return <<<HTML
<div class="artist-card">
    <h3>$name</h3>
    <p class="genre">$genre</p>
    <p class="reason">$reason</p>
    <div class="actions">
        <a href="/artist/{$artist['artist_id']}" class="btn btn-primary">View Profile</a>
    </div>
</div>
HTML;
    }

    /**
     * Helper: Generate subject line
     */
    private function generateSubjectLine(int $userId, array $artists): string
    {
        $variants = [
            'Niko\'s Discovery: 3 Artists You\'ll Love This Week',
            'Your Weekly Music Picks from Niko',
            'Niko\'s Discovery: New Artists Just for You',
            'Discover Your Next Favorite Artist with Niko'
        ];

        return $variants[$userId % count($variants)];
    }

    /**
     * Helper: Record digest error
     */
    private function recordDigestError(int $userId, array $digest, string $errorMessage): void
    {
        try {
            $currentWeek = date('Y-W');
            $this->writeConnection->prepare(
                'INSERT INTO `ngn_2025`.`niko_discovery_digests` (user_id, featured_artists, digest_week, subject_line, status, error_message)
                 VALUES (?, ?, ?, ?, "failed", ?)
                 ON DUPLICATE KEY UPDATE
                 status = "failed",
                 error_message = ?'
            )->execute([
                $userId,
                json_encode($digest['featured_artists'] ?? []),
                $currentWeek,
                $digest['subject_line'] ?? 'Niko\'s Discovery',
                $errorMessage,
                $errorMessage
            ]);
        } catch (PDOException $e) {
            LoggerFactory::getLogger('discovery')->error('Error recording digest error', ['error' => $e->getMessage()]);
        }
    }
}
