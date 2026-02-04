<?php
namespace NGN\Lib\Feed;

use NGN\Lib\Posts\PostService;
use NGN\Lib\Engagement\EngagementService;
use NGN\Config;

class FeedService
{
    private PostService $postService;
    private EngagementService $engagementService;

    public function __construct(PostService $postService, EngagementService $engagementService)
    {
        $this->postService = $postService;
        $this->engagementService = $engagementService;
    }

    public function getFeed(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $posts = $this->postService->list($filters, $limit, $offset);

        $feed = [];
        foreach ($posts as $post) {
            $ev = $this->engagementService->getEngagementVelocity('post', $post['Id']);
            $post['ev_score'] = $ev;
            $feed[] = $post;
        }

        // Sort by EV score descending
        usort($feed, function ($a, $b) {
            return $b['ev_score'] <=> $a['ev_score'];
        });

        return $feed;
    }

    /**
     * Get feed with discovery recommendations injected (Discovery Engine integration)
     * Applies 80/20 rule: 80% followed artists, 20% recommendations
     *
     * @param int $userId User ID
     * @param array $filters Additional filters
     * @param int $limit Number of items to return
     * @param int $offset Pagination offset
     * @return array Feed items with discovery content
     */
    public function getFeedWithDiscovery(int $userId, array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $baseFeed = $this->getFeed($filters, (int) ceil($limit * 0.8), $offset);

        try {
            $discoveryEngine = new \NGN\Lib\Discovery\DiscoveryEngineService(Config::getInstance());
            $recommendations = $discoveryEngine->getRecommendedArtists($userId, (int) ceil($limit * 0.2));

            // Convert recommendations to feed items
            $discoveryItems = [];
            foreach ($recommendations as $rec) {
                $discoveryItems[] = [
                    'id' => 'discovery-' . $rec['artist_id'],
                    'type' => 'discovery',
                    'artist_id' => $rec['artist_id'],
                    'artist_name' => $rec['artist_name'] ?? 'Unknown',
                    'reason' => $rec['reason'] ?? 'Recommended for you',
                    'score' => $rec['score'] ?? 0,
                    'is_emerging' => $rec['is_emerging'] ?? false,
                    'ev_score' => 0
                ];
            }

            // Interleave: alternate between regular feed and discovery items
            $blended = [];
            $feedIdx = 0;
            $discoveryIdx = 0;
            $feedRatio = 4; // Show 4 regular items per 1 discovery item

            while (count($blended) < $limit && ($feedIdx < count($baseFeed) || $discoveryIdx < count($discoveryItems))) {
                // Add regular feed items
                for ($i = 0; $i < $feedRatio && $feedIdx < count($baseFeed) && count($blended) < $limit; $i++) {
                    $blended[] = $baseFeed[$feedIdx++];
                }
                // Add discovery item
                if ($discoveryIdx < count($discoveryItems) && count($blended) < $limit) {
                    $blended[] = $discoveryItems[$discoveryIdx++];
                }
            }

            return array_slice($blended, 0, $limit);
        } catch (\Exception $e) {
            // If discovery fails, return regular feed
            error_log("Discovery Engine feed integration failed: " . $e->getMessage());
            return $baseFeed;
        }
    }
}
