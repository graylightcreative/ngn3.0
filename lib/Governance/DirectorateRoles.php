<?php

namespace NGN\Lib\Governance;

/**
 * DirectorateRoles
 *
 * Manages director role mappings and user verification.
 * Maps director slugs to user IDs and registry divisions.
 *
 * Bible Reference: Chapter 31 - Directorate SIR Registry System
 */
class DirectorateRoles
{
    /**
     * Chairman User ID (Jon Brock Lamb)
     */
    private int $chairmanUserId;

    /**
     * Director configurations
     */
    private array $directors;

    /**
     * Constructor
     *
     * @param int $chairmanUserId Chairman user ID (Jon Brock Lamb)
     * @param array $directors Director configuration with keys: brandon, pepper, erik
     */
    public function __construct(int $chairmanUserId = 1, array $directors = [])
    {
        $this->chairmanUserId = $chairmanUserId;

        // Set defaults, allow overrides via $directors parameter
        $this->directors = [
            'brandon' => [
                'user_id' => $directors['brandon']['user_id'] ?? (int)($_ENV['GOVERNANCE_BRANDON_USER_ID'] ?? 2),
                'name' => 'Brandon Lamb',
                'registry' => 'saas_fintech',
                'focus' => 'Logic gates, Stripe splits, MRR, D&B credit',
            ],
            'pepper' => [
                'user_id' => $directors['pepper']['user_id'] ?? (int)($_ENV['GOVERNANCE_PEPPER_USER_ID'] ?? 3),
                'name' => 'Pepper Gomez',
                'registry' => 'strategic_ecosystem',
                'focus' => 'Label facilitation, VC readiness, heat validation',
            ],
            'erik' => [
                'user_id' => $directors['erik']['user_id'] ?? (int)($_ENV['GOVERNANCE_ERIK_USER_ID'] ?? 4),
                'name' => 'Erik Baker',
                'registry' => 'data_integrity',
                'focus' => 'SMR ingestion, SHA-256, bot-kill accuracy',
            ],
        ];
    }

    /**
     * Get director user ID from slug
     *
     * @param string $directorSlug Director slug (brandon, pepper, erik)
     * @return int|null Director user ID or null if not found
     */
    public function getDirectorUserId(string $directorSlug): ?int
    {
        return $this->directors[strtolower($directorSlug)]['user_id'] ?? null;
    }

    /**
     * Get director name from slug
     *
     * @param string $directorSlug Director slug
     * @return string|null Director name or null if not found
     */
    public function getDirectorName(string $directorSlug): ?string
    {
        return $this->directors[strtolower($directorSlug)]['name'] ?? null;
    }

    /**
     * Get director registry division
     *
     * @param string $directorSlug Director slug
     * @return string|null Registry division or null if not found
     */
    public function getRegistryDivision(string $directorSlug): ?string
    {
        return $this->directors[strtolower($directorSlug)]['registry'] ?? null;
    }

    /**
     * Get director focus area
     *
     * @param string $directorSlug Director slug
     * @return string|null Focus area description
     */
    public function getDirectorFocus(string $directorSlug): ?string
    {
        return $this->directors[strtolower($directorSlug)]['focus'] ?? null;
    }

    /**
     * Check if user is a director
     *
     * @param int $userId User ID to check
     * @return bool True if user is a director
     */
    public function isDirector(int $userId): bool
    {
        foreach ($this->directors as $director) {
            if ($director['user_id'] === $userId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get director slug from user ID
     *
     * @param int $userId User ID
     * @return string|null Director slug or null if not found
     */
    public function getDirectorSlug(int $userId): ?string
    {
        foreach ($this->directors as $slug => $director) {
            if ($director['user_id'] === $userId) {
                return $slug;
            }
        }
        return null;
    }

    /**
     * Check if user is chairman
     *
     * @param int $userId User ID
     * @return bool True if user is chairman
     */
    public function isChairman(int $userId): bool
    {
        return $userId === $this->chairmanUserId;
    }

    /**
     * Get chairman user ID
     *
     * @return int Chairman user ID
     */
    public function getChairmanUserId(): int
    {
        return $this->chairmanUserId;
    }

    /**
     * Get all directors
     *
     * @return array Array of all directors with full config
     */
    public function getAllDirectors(): array
    {
        return $this->directors;
    }

    /**
     * Get list of all director slugs
     *
     * @return array Director slugs (brandon, pepper, erik)
     */
    public function getDirectorSlugs(): array
    {
        return array_keys($this->directors);
    }

    /**
     * Validate director slug
     *
     * @param string $directorSlug Director slug to validate
     * @return bool True if valid
     */
    public function isValidDirector(string $directorSlug): bool
    {
        return isset($this->directors[strtolower($directorSlug)]);
    }

    /**
     * Get director user IDs (all)
     *
     * @return array Array of director user IDs
     */
    public function getDirectorUserIds(): array
    {
        return array_map(fn($d) => $d['user_id'], $this->directors);
    }
}
