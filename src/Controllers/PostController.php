<?php

declare(strict_types=1);

namespace App\Controllers;

// Use Authoritative PostService from NGN\Lib
use NGN\Lib\Posts\PostService;
use NGN\Lib\Config;
use Monolog\Logger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PostController
{
    private PDO $pdo;
    private Logger $logger;
    private Config $config;
    private PostService $postService;

    public function __construct(PDO $pdo, Logger $logger, Config $config, PostService $postService)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->postService = $postService;
    }

    /**
     * Handles the creation of a new post.
     * Expects POST data including: title, slug, summary, body, tags, etc.
     */
    public function createPost(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();

        // --- Validation Rules ---
        $summary = $data['summary'] ?? '';
        $tags = $data['tags'] ?? ''; // Assuming tags are passed as a string, possibly comma-separated

        $errors = [];

        // 1. Summary length validation (max 250 characters)
        if (mb_strlen($summary) > 250) {
            $errors[] = 'Summary cannot exceed 250 characters.';
        }

        // 2. Tags length validation (max 250 characters total length)
        if (mb_strlen($tags) > 250) {
            $errors[] = 'Tags cannot exceed 250 characters.';
        }

        if (!empty($errors)) {
            // Return validation errors if any fail
            $this->logger->warning('Post creation validation failed: ' . implode(', ', $errors));
            return $response->withStatus(400)->withJson(['success' => false, 'errors' => $errors]);
        }

        // --- Post Creation Logic ---
        try {
            // Proceed to create the post using PostService after validation
            $createdPost = $this->postService->create($data);

            if ($createdPost) {
                $this->logger->info('Post created successfully. ID: ' . ($createdPost['id'] ?? 'N/A'));
                return $response->withStatus(201)->withJson(['success' => true, 'message' => 'Post created successfully.', 'data' => $createdPost]);
            } else {
                $this->logger->error('Failed to create post.');
                return $response->withStatus(500)->withJson(['success' => false, 'message' => 'Failed to create post.']);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Error during post creation: ' . $e->getMessage());
            return $response->withStatus(500)->withJson(['success' => false, 'message' => 'An internal error occurred during post creation.']);
        }
    }

    /**
     * Handles the update of an existing post.
     * Expects POST data including: summary, tags, etc.
     */
    public function updatePost(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $postId = (int)($args['id'] ?? 0); // Assuming post ID is passed in URL parameters

        if (!$postId) {
            return $response->withStatus(400)->withJson(['success' => false, 'message' => 'Post ID is required for update.']);
        }

        // --- Validation Rules ---
        $summary = $data['summary'] ?? '';
        $tags = $data['tags'] ?? '';

        $errors = [];

        // 1. Summary length validation (max 250 characters)
        if (mb_strlen($summary) > 250) {
            $errors[] = 'Summary cannot exceed 250 characters.';
        }

        // 2. Tags length validation (max 250 characters total length)
        if (mb_strlen($tags) > 250) {
            $errors[] = 'Tags cannot exceed 250 characters.';
        }

        if (!empty($errors)) {
            // Return validation errors if any fail
            $this->logger->warning('Post update validation failed for post ID ' . $postId . ': ' . implode(', ', $errors));
            return $response->withStatus(400)->withJson(['success' => false, 'errors' => $errors]);
        }

        // --- Post Update Logic ---
        try {
            // Proceed to update the post using PostService after validation
            $updated = $this->postService->update($postId, $data); // Pass validated data

            if ($updated) {
                $this->logger->info("Post ID {$postId} updated successfully.");
                return $response->withJson(['success' => true, 'message' => 'Post updated successfully.']);
            } else {
                $this->logger->error("Failed to update post ID {$postId}.");
                return $response->withStatus(500)->withJson(['success' => false, 'message' => 'Failed to update post.']);
            }

        } catch (\Throwable $e) {
            $this->logger->error("Error updating post ID {$postId}: " . $e->getMessage());
            return $response->withStatus(500)->withJson(['success' => false, 'message' => 'An internal error occurred during post update.']);
        }
    }

    // Other methods like deletePost, getPost, listPosts would be here...
}