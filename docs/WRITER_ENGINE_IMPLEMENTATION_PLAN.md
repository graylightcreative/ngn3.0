# AI-Writers Editorial Workflow Implementation Plan

## Context

The NGN Writer Engine is an AI-powered editorial system that automates content creation using 5 writer personas (Alex Reynolds, Sam O'Donnel, Frankie Morale, Kat Blac, Max Thompson). The system is **85% complete** on the backend but missing critical components for the full workflow:

**What EXISTS (Backend):**
- NIKO AI system detecting chart anomalies and assigning to personas ✅
- ArticleService with editorial workflow (claim, approve, reject) ✅
- DraftingService generating articles via LLM ✅
- SafetyFilterService scanning for defamation ✅
- writer_articles, writer_personas, writer_anomalies database tables ✅
- API endpoints at `/api/v1/writer/*` ✅
- Bible documentation (Chapter 10) defining complete workflow ✅

**What's MISSING (Critical Gaps):**
- Admin V2 writer dashboard (no UI for moderators to review/approve) 🚨
- Image upload and formatting system (1080x1080 square, 1080x1920 story) 🚨
- G-Fleet social publishing (stub exists, no actual Instagram/Facebook posting) 🚨
- social_posts database table (referenced but doesn't exist) 🚨
- Automated cron jobs for publishing social queue 🚨

**User's Required Workflow:**
1. **NIKO** (automated) - Detects trending topics, assigns to personas, generates draft articles ✅ EXISTS
2. **Writers** (AI personas) - Create articles based on anomalies ✅ EXISTS
3. **Moderator** (human) - Review drafts, add main image, approve/reject ❌ NEEDS ADMIN UI
4. **Moderator** (continued) - Add Instagram square + story images ❌ NEEDS IMAGE SYSTEM
5. **Automated** (cron) - Post approved articles to Instagram/Facebook ❌ NEEDS G-FLEET UPLINK

**Why This Plan:**
The backend AI engine is production-ready but completely inaccessible to humans. Moderators need a UI to review AI-generated content, add images, and publish to social media. Without this, articles pile up in the database with no way to approve or distribute them.

---

## Implementation Strategy

### Phase 1: Database Schema Additions (Day 1)
**Priority:** P0 - Foundation for all other work

#### 1.1 Create social_posts Table
**File:** `/scripts/setup/create_social_posts_table.php`

```php
CREATE TABLE `social_posts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int unsigned NULL COMMENT 'FK to writer_articles if auto-posted from article',
  `fleet_account_id` int unsigned NULL COMMENT 'FK to g_fleet_accounts',
  `platform` enum('instagram','facebook','twitter') NOT NULL,
  `post_type` enum('feed','story','reel') NOT NULL DEFAULT 'feed',
  `caption` text NOT NULL,
  `media_url` varchar(512) NULL COMMENT 'Image or video URL',
  `media_square_url` varchar(512) NULL COMMENT '1080x1080 for Instagram feed',
  `media_story_url` varchar(512) NULL COMMENT '1080x1920 for Instagram story',
  `instagram_container_id` varchar(128) NULL COMMENT 'IG Container API ID',
  `facebook_post_id` varchar(128) NULL COMMENT 'FB post ID after publish',
  `status` enum('pending','processing','published','failed') NOT NULL DEFAULT 'pending',
  `scheduled_at` datetime NOT NULL,
  `published_at` datetime NULL,
  `error_message` text NULL,
  `retry_count` int NOT NULL DEFAULT 0,
  `engagement_likes` int NOT NULL DEFAULT 0,
  `engagement_comments` int NOT NULL DEFAULT 0,
  `engagement_shares` int NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status_scheduled` (`status`, `scheduled_at`),
  KEY `article_id` (`article_id`),
  KEY `fleet_account_id` (`fleet_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.2 Create g_fleet_accounts Table
**File:** Same migration

```php
CREATE TABLE `g_fleet_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `platform` enum('instagram','facebook','twitter') NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_handle` varchar(128) NOT NULL COMMENT '@username or handle',
  `instagram_user_id` varchar(64) NULL,
  `facebook_page_id` varchar(64) NULL,
  `access_token` varchar(512) NULL COMMENT 'Long-lived token',
  `token_expires_at` datetime NULL,
  `is_active` boolean NOT NULL DEFAULT true,
  `daily_post_limit` int NOT NULL DEFAULT 25 COMMENT 'Platform rate limits',
  `posts_today` int NOT NULL DEFAULT 0,
  `last_post_at` datetime NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_handle` (`platform`, `account_handle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 1.3 Extend writer_articles Table
**File:** `/scripts/setup/alter_writer_articles_images.php`

```sql
ALTER TABLE writer_articles
ADD COLUMN main_image_url varchar(512) NULL AFTER content,
ADD COLUMN social_square_url varchar(512) NULL COMMENT '1080x1080 Instagram feed',
ADD COLUMN social_story_url varchar(512) NULL COMMENT '1080x1920 Instagram story',
ADD COLUMN social_post_caption text NULL COMMENT 'Custom caption for social',
ADD INDEX idx_social_images (social_square_url, social_story_url);
```

**Verification:**
```bash
php scripts/setup/create_social_posts_table.php
php scripts/setup/alter_writer_articles_images.php
mysql -u root -p ngn_db -e "SHOW TABLES LIKE '%social%'"
```

---

### Phase 2: Image Management System (Days 2-4)
**Priority:** P1 - Required before social publishing

#### 2.1 Image Upload Service
**File:** `/lib/Services/Writer/ImageUploadService.php`

```php
<?php
namespace NGN\Lib\Services\Writer;

use NGN\Lib\Config;
use Intervention\Image\ImageManagerStatic as Image;

class ImageUploadService
{
    private Config $config;
    private string $uploadPath;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->uploadPath = $config->get('storage_path') . '/uploads/writer';

        // Create directories if they don't exist
        if (!is_dir($this->uploadPath . '/original')) {
            mkdir($this->uploadPath . '/original', 0755, true);
        }
        if (!is_dir($this->uploadPath . '/social')) {
            mkdir($this->uploadPath . '/social', 0755, true);
        }
    }

    /**
     * Upload and process image for article
     * Returns array with URLs for original, square (1080x1080), and story (1080x1920)
     */
    public function uploadArticleImage(array $file, int $articleId): array
    {
        // Validate file
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file['type'], $allowedMimes)) {
            throw new \InvalidArgumentException('Invalid image type. Only JPG, PNG, WebP allowed.');
        }

        if ($file['size'] > 10 * 1024 * 1024) { // 10MB max
            throw new \InvalidArgumentException('Image too large. Maximum 10MB.');
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = sprintf('article_%d_%s.%s', $articleId, uniqid(), $extension);

        // Save original
        $originalPath = $this->uploadPath . '/original/' . $filename;
        move_uploaded_file($file['tmp_name'], $originalPath);

        // Generate social variants
        $squarePath = $this->uploadPath . '/social/' . str_replace('.' . $extension, '_square.jpg', $filename);
        $storyPath = $this->uploadPath . '/social/' . str_replace('.' . $extension, '_story.jpg', $filename);

        // Use ImageFormattingService to create variants
        $formatter = new ImageFormattingService($this->config);
        $formatter->generateSquare($originalPath, $squarePath);
        $formatter->generateStory($originalPath, $storyPath);

        return [
            'original_url' => '/storage/uploads/writer/original/' . $filename,
            'square_url' => '/storage/uploads/writer/social/' . basename($squarePath),
            'story_url' => '/storage/uploads/writer/social/' . basename($storyPath),
        ];
    }

    /**
     * Delete all images for an article
     */
    public function deleteArticleImages(int $articleId): void
    {
        $pattern = sprintf('%s/*/article_%d_*', $this->uploadPath, $articleId);
        foreach (glob($pattern) as $file) {
            unlink($file);
        }
    }
}
```

#### 2.2 Image Formatting Service
**File:** `/lib/Services/Writer/ImageFormattingService.php`

**Dependencies:**
```bash
composer require intervention/image
```

```php
<?php
namespace NGN\Lib\Services\Writer;

use Intervention\Image\ImageManagerStatic as Image;

class ImageFormattingService
{
    /**
     * Generate 1080x1080 square image for Instagram feed
     */
    public function generateSquare(string $sourcePath, string $outputPath): void
    {
        $img = Image::make($sourcePath);

        // Get dimensions and calculate crop to square
        $width = $img->width();
        $height = $img->height();
        $size = min($width, $height);

        // Crop to square from center
        $img->crop($size, $size);

        // Resize to Instagram spec
        $img->resize(1080, 1080);

        // Save as high-quality JPEG
        $img->save($outputPath, 90, 'jpg');
    }

    /**
     * Generate 1080x1920 story image for Instagram stories
     */
    public function generateStory(string $sourcePath, string $outputPath): void
    {
        $img = Image::make($sourcePath);

        // Calculate dimensions for 9:16 aspect ratio
        $width = $img->width();
        $height = $img->height();

        $targetRatio = 9 / 16;
        $currentRatio = $width / $height;

        if ($currentRatio > $targetRatio) {
            // Image is wider - crop width
            $newWidth = $height * $targetRatio;
            $img->crop((int)$newWidth, $height);
        } else {
            // Image is taller - crop height
            $newHeight = $width / $targetRatio;
            $img->crop($width, (int)$newHeight);
        }

        // Resize to Instagram story spec
        $img->resize(1080, 1920);

        // Save as high-quality JPEG
        $img->save($outputPath, 90, 'jpg');
    }
}
```

#### 2.3 Article Image API Endpoint
**File:** `/public/api/v1/writer_routes.php` (add to existing file)

```php
// POST /api/v1/admin/writer/articles/:id/upload-image
$router->post('/admin/writer/articles/:id/upload-image', function (Request $request) use ($config) {
    requireAuth($request); // JWT validation

    try {
        $articleId = (int)$request->param('id');

        if (!isset($_FILES['image'])) {
            return new JsonResponse(['success' => false, 'message' => 'No image provided'], 400);
        }

        $uploadService = new \NGN\Lib\Services\Writer\ImageUploadService($config);
        $urls = $uploadService->uploadArticleImage($_FILES['image'], $articleId);

        // Update article record
        $pdo = ConnectionFactory::write($config);
        $stmt = $pdo->prepare("
            UPDATE writer_articles
            SET main_image_url = ?,
                social_square_url = ?,
                social_story_url = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $urls['original_url'],
            $urls['square_url'],
            $urls['story_url'],
            $articleId
        ]);

        return new JsonResponse([
            'success' => true,
            'data' => $urls
        ], 200);

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});
```

---

### Phase 3: Admin V2 Writer Dashboard (Days 5-9)
**Priority:** P1 - Primary human interface

#### 3.1 Editorial Queue Page
**File:** `/public/admin/src/pages/writer/EditorialQueue.tsx`

```typescript
import { useState } from 'react'
import { useApiQuery } from '../../hooks/useApiQuery'
import { FileText, AlertCircle, CheckCircle, Clock } from 'lucide-react'
import { Link } from 'react-router-dom'

interface Article {
  id: number
  title: string
  excerpt: string
  persona_name: string
  persona_specialty: string
  safety_status: 'approved' | 'flagged'
  safety_score: number
  artist_name: string
  detection_type: string
  severity: string
  created_at: string
  generation_cost_usd: number
}

export default function EditorialQueue() {
  const [filter, setFilter] = useState<'all' | 'safe' | 'flagged'>('all')

  const { data: articles = [], isLoading } = useApiQuery<Article[]>(
    ['writer', 'editorial-queue', filter],
    () => fetch('/api/v1/admin/writer/editorial-queue?' + new URLSearchParams({
      safety_filter: filter
    })).then(r => r.json()).then(d => d.data)
  )

  const safeCount = articles.filter(a => a.safety_status === 'approved').length
  const flaggedCount = articles.filter(a => a.safety_status === 'flagged').length

  return (
    <div className="max-w-7xl">
      {/* Header */}
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <FileText className="text-purple-500" size={32} />
          <div>
            <h1 className="text-3xl font-bold text-gray-100">Editorial Queue</h1>
            <p className="text-gray-400">Review AI-generated articles before publishing</p>
          </div>
        </div>

        {/* Stats */}
        <div className="flex gap-4">
          <div className="card px-4 py-2">
            <p className="text-sm text-gray-400">Total</p>
            <p className="text-2xl font-bold text-gray-100">{articles.length}</p>
          </div>
          <div className="card px-4 py-2 border-green-700 bg-green-900 bg-opacity-20">
            <p className="text-sm text-green-400">Safe</p>
            <p className="text-2xl font-bold text-green-400">{safeCount}</p>
          </div>
          <div className="card px-4 py-2 border-red-700 bg-red-900 bg-opacity-20">
            <p className="text-sm text-red-400">Flagged</p>
            <p className="text-2xl font-bold text-red-400">{flaggedCount}</p>
          </div>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex gap-2 mb-6">
        <button
          onClick={() => setFilter('all')}
          className={`px-4 py-2 rounded-lg transition ${
            filter === 'all'
              ? 'bg-brand-green text-black font-semibold'
              : 'bg-brand-light text-gray-400 hover:text-gray-200'
          }`}
        >
          All ({articles.length})
        </button>
        <button
          onClick={() => setFilter('safe')}
          className={`px-4 py-2 rounded-lg transition ${
            filter === 'safe'
              ? 'bg-green-600 text-white font-semibold'
              : 'bg-brand-light text-gray-400 hover:text-gray-200'
          }`}
        >
          Safe ({safeCount})
        </button>
        <button
          onClick={() => setFilter('flagged')}
          className={`px-4 py-2 rounded-lg transition ${
            filter === 'flagged'
              ? 'bg-red-600 text-white font-semibold'
              : 'bg-brand-light text-gray-400 hover:text-gray-200'
          }`}
        >
          Flagged ({flaggedCount})
        </button>
      </div>

      {/* Articles List */}
      {isLoading ? (
        <div className="card text-center py-12">
          <Clock className="animate-spin mx-auto text-brand-green mb-3" size={48} />
          <p className="text-gray-400">Loading articles...</p>
        </div>
      ) : (
        <div className="space-y-4">
          {articles.map(article => (
            <Link
              key={article.id}
              to={`/writer/article/${article.id}`}
              className="card hover:border-brand-green transition block"
            >
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  {/* Safety Badge */}
                  <div className="flex items-center gap-2 mb-2">
                    {article.safety_status === 'approved' ? (
                      <span className="px-2 py-1 rounded text-xs font-semibold bg-green-900 bg-opacity-30 text-green-400 border border-green-700">
                        <CheckCircle size={14} className="inline mr-1" />
                        Safe
                      </span>
                    ) : (
                      <span className="px-2 py-1 rounded text-xs font-semibold bg-red-900 bg-opacity-30 text-red-400 border border-red-700">
                        <AlertCircle size={14} className="inline mr-1" />
                        Flagged ({article.safety_score.toFixed(2)})
                      </span>
                    )}
                    <span className="px-2 py-1 rounded text-xs bg-purple-900 bg-opacity-30 text-purple-400">
                      {article.persona_name}
                    </span>
                    <span className="px-2 py-1 rounded text-xs bg-blue-900 bg-opacity-30 text-blue-400">
                      {article.detection_type}
                    </span>
                  </div>

                  {/* Title & Excerpt */}
                  <h3 className="text-xl font-semibold text-gray-100 mb-2">
                    {article.title}
                  </h3>
                  <p className="text-gray-400 text-sm mb-2">
                    {article.excerpt}
                  </p>

                  {/* Meta */}
                  <div className="flex gap-4 text-xs text-gray-500">
                    <span>Artist: {article.artist_name}</span>
                    <span>•</span>
                    <span>{new Date(article.created_at).toLocaleDateString()}</span>
                    <span>•</span>
                    <span>Cost: ${article.generation_cost_usd.toFixed(3)}</span>
                  </div>
                </div>

                {/* Arrow */}
                <div className="text-gray-600">→</div>
              </div>
            </Link>
          ))}

          {articles.length === 0 && (
            <div className="card text-center py-12">
              <CheckCircle className="mx-auto text-gray-600 mb-3" size={48} />
              <p className="text-gray-400">No articles in queue</p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
```

#### 3.2 Article Editor Page
**File:** `/public/admin/src/pages/writer/ArticleEditor.tsx`

```typescript
import { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import { useApiQuery, useApiMutation } from '../../hooks/useApiQuery'
import { CheckCircle, XCircle, Upload, AlertTriangle } from 'lucide-react'

interface ArticleDetail {
  id: number
  title: string
  excerpt: string
  content: string
  persona_name: string
  safety_status: 'approved' | 'flagged'
  safety_score: number
  safety_flags: string[]
  main_image_url: string | null
  social_square_url: string | null
  social_story_url: string | null
  artist_name: string
  created_at: string
}

export default function ArticleEditor() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const [caption, setCaption] = useState('')

  const { data: article, isLoading } = useApiQuery<ArticleDetail>(
    ['writer', 'article', id],
    () => fetch(`/api/v1/admin/writer/articles/${id}`).then(r => r.json()).then(d => d.data)
  )

  const uploadImageMutation = useApiMutation(
    (file: File) => {
      const formData = new FormData()
      formData.append('image', file)
      return fetch(`/api/v1/admin/writer/articles/${id}/upload-image`, {
        method: 'POST',
        body: formData
      }).then(r => r.json())
    },
    { invalidateKeys: [['writer', 'article', id]] }
  )

  const approveMutation = useApiMutation(
    () => fetch(`/api/v1/admin/writer/articles/${id}/approve`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ social_caption: caption })
    }).then(r => r.json()),
    {
      onSuccess: () => navigate('/writer/editorial-queue'),
      invalidateKeys: [['writer', 'editorial-queue']]
    }
  )

  const rejectMutation = useApiMutation(
    (reason: string) => fetch(`/api/v1/admin/writer/articles/${id}/reject`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reason })
    }).then(r => r.json()),
    {
      onSuccess: () => navigate('/writer/editorial-queue'),
      invalidateKeys: [['writer', 'editorial-queue']]
    }
  )

  if (isLoading || !article) {
    return <div>Loading...</div>
  }

  return (
    <div className="max-w-5xl">
      <h1 className="text-3xl font-bold text-gray-100 mb-6">Review Article</h1>

      {/* Safety Warning */}
      {article.safety_status === 'flagged' && (
        <div className="card border-red-700 bg-red-900 bg-opacity-20 mb-6">
          <div className="flex gap-3">
            <AlertTriangle className="text-red-500 flex-shrink-0" size={24} />
            <div>
              <h3 className="font-semibold text-red-400 mb-2">Safety Flags Detected</h3>
              <p className="text-sm text-red-300 mb-2">Score: {article.safety_score.toFixed(2)}</p>
              <ul className="text-sm text-red-300 list-disc list-inside">
                {article.safety_flags.map((flag, idx) => (
                  <li key={idx}>{flag}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* Article Content */}
      <div className="card mb-6">
        <h2 className="text-2xl font-bold text-gray-100 mb-2">{article.title}</h2>
        <p className="text-gray-400 mb-4">{article.excerpt}</p>
        <div
          className="prose prose-invert max-w-none"
          dangerouslySetInnerHTML={{ __html: article.content }}
        />
      </div>

      {/* Image Upload */}
      <div className="card mb-6">
        <h3 className="font-semibold text-gray-100 mb-4">Images</h3>

        <div className="grid grid-cols-3 gap-4 mb-4">
          <div>
            <p className="text-sm text-gray-400 mb-2">Main Image</p>
            {article.main_image_url ? (
              <img src={article.main_image_url} alt="Main" className="w-full rounded" />
            ) : (
              <div className="border-2 border-dashed border-gray-600 rounded p-8 text-center">
                <p className="text-gray-500 text-sm">No image</p>
              </div>
            )}
          </div>

          <div>
            <p className="text-sm text-gray-400 mb-2">Square (1080x1080)</p>
            {article.social_square_url ? (
              <img src={article.social_square_url} alt="Square" className="w-full rounded" />
            ) : (
              <div className="border-2 border-dashed border-gray-600 rounded p-8 text-center">
                <p className="text-gray-500 text-sm">Auto-generated</p>
              </div>
            )}
          </div>

          <div>
            <p className="text-sm text-gray-400 mb-2">Story (1080x1920)</p>
            {article.social_story_url ? (
              <img src={article.social_story_url} alt="Story" className="w-full rounded" />
            ) : (
              <div className="border-2 border-dashed border-gray-600 rounded p-8 text-center">
                <p className="text-gray-500 text-sm">Auto-generated</p>
              </div>
            )}
          </div>
        </div>

        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          onChange={(e) => {
            const file = e.target.files?.[0]
            if (file) uploadImageMutation.mutate(file)
          }}
          className="input-base"
        />
      </div>

      {/* Social Caption */}
      <div className="card mb-6">
        <h3 className="font-semibold text-gray-100 mb-2">Social Media Caption</h3>
        <textarea
          value={caption}
          onChange={(e) => setCaption(e.target.value)}
          placeholder="Write a caption for Instagram/Facebook... (or leave blank to use article excerpt)"
          className="input-base w-full h-32"
        />
      </div>

      {/* Actions */}
      <div className="flex gap-4">
        <button
          onClick={() => approveMutation.mutate()}
          disabled={!article.main_image_url}
          className="btn-primary flex items-center gap-2"
        >
          <CheckCircle size={20} />
          Approve & Publish
        </button>

        <button
          onClick={() => {
            const reason = prompt('Rejection reason:')
            if (reason) rejectMutation.mutate(reason)
          }}
          className="btn-danger flex items-center gap-2"
        >
          <XCircle size={20} />
          Reject
        </button>
      </div>
    </div>
  )
}
```

#### 3.3 Add Writer Routes to Admin V2
**File:** `/public/admin/src/App.tsx` (add to existing routes)

```typescript
import EditorialQueue from './pages/writer/EditorialQueue'
import ArticleEditor from './pages/writer/ArticleEditor'

// In routes array:
<Route path="writer/editorial-queue" element={<EditorialQueue />} />
<Route path="writer/article/:id" element={<ArticleEditor />} />
```

---

### Phase 4: G-Fleet Social Publishing Service (Days 10-14) 🚨 CRITICAL
**Priority:** P0 - Core automation requirement

#### 4.1 Complete SocialPublishingService
**File:** `/lib/Services/Social/SocialPublishingService.php` (replace stub)

**Dependencies:**
```bash
composer require facebook/graph-sdk
```

```php
<?php
namespace NGN\Lib\Services\Social;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use Facebook\Facebook;
use PDO;

class SocialPublishingService
{
    private Config $config;
    private PDO $pdo;
    private Facebook $fb;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->pdo = ConnectionFactory::write($config);

        $this->fb = new Facebook([
            'app_id' => $config->get('FACEBOOK_APP_ID'),
            'app_secret' => $config->get('FACEBOOK_APP_SECRET'),
            'default_graph_version' => 'v18.0',
        ]);
    }

    /**
     * Queue a post for social publishing
     */
    public function queuePost(array $data): int
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO social_posts (
                article_id, platform, post_type, caption,
                media_url, media_square_url, media_story_url,
                status, scheduled_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
        ");

        $stmt->execute([
            $data['article_id'] ?? null,
            $data['platform'],
            $data['post_type'] ?? 'feed',
            $data['caption'],
            $data['media_url'] ?? null,
            $data['media_square_url'] ?? null,
            $data['media_story_url'] ?? null,
            $data['scheduled_at'] ?? date('Y-m-d H:i:s'),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Process pending posts (called by cron every 15 min)
     */
    public function processPendingPosts(): array
    {
        $results = [];

        // Get posts ready to publish
        $stmt = $this->pdo->query("
            SELECT sp.*, gfa.instagram_user_id, gfa.facebook_page_id, gfa.access_token
            FROM social_posts sp
            LEFT JOIN g_fleet_accounts gfa ON sp.fleet_account_id = gfa.id
            WHERE sp.status = 'pending'
              AND sp.scheduled_at <= NOW()
              AND sp.retry_count < 3
            ORDER BY sp.scheduled_at ASC
            LIMIT 10
        ");

        while ($post = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                $this->updatePostStatus($post['id'], 'processing');

                if ($post['platform'] === 'instagram') {
                    if ($post['post_type'] === 'story') {
                        $result = $this->publishInstagramStory($post);
                    } else {
                        $result = $this->publishInstagramFeed($post);
                    }
                } elseif ($post['platform'] === 'facebook') {
                    $result = $this->publishFacebookPost($post);
                }

                $this->updatePostStatus($post['id'], 'published', $result);
                $results[] = ['id' => $post['id'], 'status' => 'success'];

            } catch (\Throwable $e) {
                $this->handlePublishError($post['id'], $e->getMessage());
                $results[] = ['id' => $post['id'], 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Publish to Instagram Feed (2-step Container API)
     */
    private function publishInstagramFeed(array $post): array
    {
        $accessToken = $post['access_token'];
        $igUserId = $post['instagram_user_id'];
        $imageUrl = $this->getPublicImageUrl($post['media_square_url']);

        // Step 1: Create container
        $response = $this->fb->post(
            "/{$igUserId}/media",
            [
                'image_url' => $imageUrl,
                'caption' => $post['caption'],
            ],
            $accessToken
        );

        $containerId = $response->getGraphNode()['id'];

        // Update record with container ID
        $this->pdo->prepare("UPDATE social_posts SET instagram_container_id = ? WHERE id = ?")
            ->execute([$containerId, $post['id']]);

        // Step 2: Publish container (wait 30s for IG to process)
        sleep(30);

        $response = $this->fb->post(
            "/{$igUserId}/media_publish",
            ['creation_id' => $containerId],
            $accessToken
        );

        return [
            'instagram_post_id' => $response->getGraphNode()['id'],
            'container_id' => $containerId,
        ];
    }

    /**
     * Publish to Instagram Story
     */
    private function publishInstagramStory(array $post): array
    {
        $accessToken = $post['access_token'];
        $igUserId = $post['instagram_user_id'];
        $imageUrl = $this->getPublicImageUrl($post['media_story_url']);

        $response = $this->fb->post(
            "/{$igUserId}/media",
            [
                'image_url' => $imageUrl,
                'media_type' => 'STORIES',
            ],
            $accessToken
        );

        $containerId = $response->getGraphNode()['id'];

        sleep(30);

        $response = $this->fb->post(
            "/{$igUserId}/media_publish",
            ['creation_id' => $containerId],
            $accessToken
        );

        return ['instagram_story_id' => $response->getGraphNode()['id']];
    }

    /**
     * Publish to Facebook Page
     */
    private function publishFacebookPost(array $post): array
    {
        $accessToken = $post['access_token'];
        $pageId = $post['facebook_page_id'];
        $imageUrl = $this->getPublicImageUrl($post['media_url']);

        $response = $this->fb->post(
            "/{$pageId}/photos",
            [
                'url' => $imageUrl,
                'caption' => $post['caption'],
            ],
            $accessToken
        );

        return ['facebook_post_id' => $response->getGraphNode()['id']];
    }

    /**
     * Convert local path to public URL
     */
    private function getPublicImageUrl(string $path): string
    {
        $baseUrl = $this->config->get('APP_URL');
        return $baseUrl . $path;
    }

    private function updatePostStatus(int $postId, string $status, array $result = []): void
    {
        $updates = ['status' => $status];

        if ($status === 'published') {
            $updates['published_at'] = date('Y-m-d H:i:s');
            if (isset($result['facebook_post_id'])) {
                $updates['facebook_post_id'] = $result['facebook_post_id'];
            }
        }

        $setClauses = [];
        $values = [];
        foreach ($updates as $key => $value) {
            $setClauses[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $postId;

        $sql = "UPDATE social_posts SET " . implode(', ', $setClauses) . " WHERE id = ?";
        $this->pdo->prepare($sql)->execute($values);
    }

    private function handlePublishError(int $postId, string $error): void
    {
        $this->pdo->prepare("
            UPDATE social_posts
            SET status = 'failed',
                error_message = ?,
                retry_count = retry_count + 1,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$error, $postId]);
    }
}
```

#### 4.2 Approve Article with Social Queueing
**File:** `/public/api/v1/writer_routes.php` (update existing approve endpoint)

```php
// POST /api/v1/admin/writer/articles/:id/approve
$router->post('/admin/writer/articles/:id/approve', function (Request $request) use ($config, $articleService) {
    requireAuth($request);

    try {
        $articleId = (int)$request->param('id');
        $data = $request->json();
        $socialCaption = $data['social_caption'] ?? '';

        // Approve article
        $articleService->approveArticle($articleId, $request->userId());

        // Get article details
        $article = $articleService->getArticleForEdit($articleId);

        // Queue social posts
        $socialService = new \NGN\Lib\Services\Social\SocialPublishingService($config);

        // Instagram Feed Post (square image)
        if ($article['social_square_url']) {
            $socialService->queuePost([
                'article_id' => $articleId,
                'platform' => 'instagram',
                'post_type' => 'feed',
                'caption' => $socialCaption ?: $article['excerpt'],
                'media_square_url' => $article['social_square_url'],
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
            ]);
        }

        // Instagram Story (story image)
        if ($article['social_story_url']) {
            $socialService->queuePost([
                'article_id' => $articleId,
                'platform' => 'instagram',
                'post_type' => 'story',
                'caption' => '',
                'media_story_url' => $article['social_story_url'],
                'scheduled_at' => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Article approved and queued for social publishing'
        ], 200);

    } catch (\Throwable $e) {
        return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
    }
});
```

---

### Phase 5: Cron Automation (Day 15)
**Priority:** P1 - Automation enablement

#### 5.1 Social Publishing Cron Job
**File:** `/scripts/cron/social_publish_queue.php`

```php
<?php
/**
 * Social Publishing Queue Processor
 * Run every 15 minutes to publish pending social posts
 *
 * Crontab: */15 * * * * php /path/to/scripts/cron/social_publish_queue.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Services\Social\SocialPublishingService;
use NGN\Lib\Logging\LoggerFactory;

$config = new Config();
$logger = LoggerFactory::create($config, 'social_publish');
$service = new SocialPublishingService($config);

$logger->info("Starting social publishing queue processor");

try {
    $results = $service->processPendingPosts();

    $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
    $failCount = count(array_filter($results, fn($r) => $r['status'] === 'failed'));

    $logger->info("Published $successCount posts, $failCount failed", [
        'results' => $results
    ]);

} catch (\Throwable $e) {
    $logger->error("Social publish queue failed", [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}

exit(0);
```

#### 5.2 Daily Fleet Reset Cron
**File:** `/scripts/cron/reset_fleet_daily_limits.php`

```php
<?php
/**
 * Reset daily post limits for fleet accounts
 * Run daily at midnight
 *
 * Crontab: 0 0 * * * php /path/to/scripts/cron/reset_fleet_daily_limits.php
 */

require_once __DIR__ . '/../../bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;

$config = new Config();
$pdo = ConnectionFactory::write($config);

$pdo->query("UPDATE g_fleet_accounts SET posts_today = 0");

echo "Fleet daily limits reset\n";
exit(0);
```

#### 5.3 Crontab Configuration
**File:** Add to server crontab via `crontab -e`

```bash
# Social Publishing Queue (every 15 minutes)
*/15 * * * * /usr/bin/php /var/www/ngn_202/scripts/cron/social_publish_queue.php >> /var/log/ngn/social_publish.log 2>&1

# Reset Fleet Daily Limits (midnight daily)
0 0 * * * /usr/bin/php /var/www/ngn_202/scripts/cron/reset_fleet_daily_limits.php >> /var/log/ngn/cron.log 2>&1

# Existing NIKO crons (verify these are scheduled)
*/15 * * * * /usr/bin/php /var/www/ngn_202/scripts/cron/scout_anomaly_detection.php
*/10 * * * * /usr/bin/php /var/www/ngn_202/scripts/cron/niko_dispatcher.php
```

---

### Phase 6: Testing & Validation (Days 16-17)
**Priority:** P1 - Quality assurance

#### 6.1 End-to-End Workflow Test

**Test Sequence:**
1. Trigger NIKO anomaly detection manually
2. Verify draft article created in database
3. Access Editorial Queue in Admin V2
4. Open article editor
5. Upload test image
6. Verify square (1080x1080) and story (1080x1920) generated
7. Add custom social caption
8. Approve article
9. Verify social_posts records created with 5-minute delay
10. Wait 15 minutes for cron to run
11. Verify posts published to Instagram/Facebook
12. Check engagement tracking

**Test Script:** `/scripts/testing/test_writer_workflow.php`

```php
<?php
require_once __DIR__ . '/../../bootstrap.php';

use NGN\Lib\Config;
use NGN\Lib\Writer\ScoutService;
use NGN\Lib\Writer\NikoService;
use NGN\Lib\Writer\DraftingService;
use NGN\Lib\Services\Writer\ImageFormattingService;
use NGN\Lib\Services\Social\SocialPublishingService;

$config = new Config();

echo "=== Writer Workflow End-to-End Test ===\n\n";

// Test 1: Scout detects anomaly
echo "1. Detecting anomalies...\n";
$scout = new ScoutService($config);
$anomalies = $scout->detectChartJumps();
echo "   Found " . count($anomalies) . " anomalies\n\n";

// Test 2: Niko assigns persona
echo "2. Assigning to persona...\n";
$niko = new NikoService($config);
if (!empty($anomalies)) {
    $assignment = $niko->assignPersona($anomalies[0]['id']);
    echo "   Assigned to: " . $assignment['persona_name'] . "\n\n";
}

// Test 3: Generate draft
echo "3. Generating draft article...\n";
$drafting = new DraftingService($config);
// (Drafting happens automatically via cron, skip in test)
echo "   [Skipped - requires LLM API call]\n\n";

// Test 4: Image formatting
echo "4. Testing image formatting...\n";
$formatter = new ImageFormattingService($config);
$testImage = __DIR__ . '/test_assets/sample_image.jpg';
if (file_exists($testImage)) {
    $formatter->generateSquare($testImage, '/tmp/test_square.jpg');
    $formatter->generateStory($testImage, '/tmp/test_story.jpg');

    $squareSize = getimagesize('/tmp/test_square.jpg');
    $storySize = getimagesize('/tmp/test_story.jpg');

    echo "   Square: {$squareSize[0]}x{$squareSize[1]} " . ($squareSize[0] === 1080 && $squareSize[1] === 1080 ? '✓' : '✗') . "\n";
    echo "   Story: {$storySize[0]}x{$storySize[1]} " . ($storySize[0] === 1080 && $storySize[1] === 1920 ? '✓' : '✗') . "\n\n";
}

// Test 5: Social queue
echo "5. Testing social queue...\n";
$social = new SocialPublishingService($config);
$postId = $social->queuePost([
    'platform' => 'instagram',
    'post_type' => 'feed',
    'caption' => 'Test post from Writer Engine',
    'media_square_url' => '/storage/uploads/writer/social/test_square.jpg',
    'scheduled_at' => date('Y-m-d H:i:s'),
]);
echo "   Queued post ID: $postId ✓\n\n";

echo "=== Test Complete ===\n";
```

#### 6.2 Safety Filter Validation
**File:** `/scripts/testing/test_safety_filter.php`

```php
<?php
require_once __DIR__ . '/../../bootstrap.php';

use NGN\Lib\Writer\SafetyFilterService;
use NGN\Lib\Config;

$config = new Config();
$safety = new SafetyFilterService($config);

$testCases = [
    ['text' => 'This album is terrible. The mix is muddy and the guitar tone is weak.', 'expected' => 'approved'], // Attack music, OK
    ['text' => 'The artist is a terrible person and should be ashamed.', 'expected' => 'flagged'], // Personal attack, BAD
    ['text' => 'Coldplay makes boring, corporate garbage music.', 'expected' => 'approved'], // Attack music/corporate, OK
];

foreach ($testCases as $i => $test) {
    echo "Test " . ($i + 1) . ": ";
    $result = $safety->scan($test['text']);
    $status = $result['score'] < 0.1 ? 'approved' : 'flagged';
    echo ($status === $test['expected'] ? '✓' : '✗') . " (score: {$result['score']})\n";
}
```

---

### Phase 7: Documentation & Deployment (Day 18)
**Priority:** P2 - Ops readiness

#### 7.1 Update MEMORY.md
Add to `/Users/brock/.claude/projects/-Users-brock-Documents-Projects-ngn-202/memory/MEMORY.md`:

```markdown
## Writer Engine Implementation Status (2026-02-20)

**Completed:**
- ✅ Backend services (Scout, Niko, Drafting, Safety Filter)
- ✅ Admin V2 Editorial Queue UI
- ✅ Admin V2 Article Editor UI
- ✅ Image upload and formatting (1080x1080, 1080x1920)
- ✅ G-Fleet social publishing service
- ✅ social_posts and g_fleet_accounts tables
- ✅ Cron jobs for automation
- ✅ Instagram Feed and Story publishing via Graph API
- ✅ Facebook Page publishing

**Configuration Required:**
- Set FACEBOOK_APP_ID and FACEBOOK_APP_SECRET in .env
- Obtain Instagram Business Account access tokens
- Configure g_fleet_accounts table with credentials
- Schedule cron jobs (every 15 minutes for publishing)

**Key Files:**
- `/lib/Services/Social/SocialPublishingService.php` - Instagram/Facebook publishing
- `/lib/Services/Writer/ImageFormattingService.php` - 1080x1080, 1080x1920 generation
- `/public/admin/src/pages/writer/EditorialQueue.tsx` - Review interface
- `/public/admin/src/pages/writer/ArticleEditor.tsx` - Approval interface
- `/scripts/cron/social_publish_queue.php` - Queue processor

**Workflow:**
1. NIKO detects anomalies (automated every 15 min)
2. Drafting generates articles (automated every 30 min)
3. Moderator reviews in Editorial Queue
4. Moderator uploads image → auto-formats to square + story
5. Moderator approves → queues social posts
6. Cron publishes to Instagram/Facebook (every 15 min)
```

#### 7.2 Fleet Deployment
**File:** Update automation config

```bash
php bin/automate.php deploy --version=2.5.0 --message="Writer Engine: Complete Editorial Workflow + G-Fleet Publishing"
```

---

## Critical Files Reference

**NEW Files to Create (10 files):**

1. **`/scripts/setup/create_social_posts_table.php`** - Database schema (social_posts, g_fleet_accounts)
2. **`/scripts/setup/alter_writer_articles_images.php`** - Add image columns to writer_articles
3. **`/lib/Services/Writer/ImageUploadService.php`** - Handle file uploads
4. **`/lib/Services/Writer/ImageFormattingService.php`** - Generate 1080x1080, 1080x1920
5. **`/lib/Services/Social/SocialPublishingService.php`** - Instagram/Facebook Graph API publishing
6. **`/public/admin/src/pages/writer/EditorialQueue.tsx`** - React editorial queue page
7. **`/public/admin/src/pages/writer/ArticleEditor.tsx`** - React article editor with image upload
8. **`/scripts/cron/social_publish_queue.php`** - Cron job to process queue
9. **`/scripts/cron/reset_fleet_daily_limits.php`** - Daily fleet limit reset
10. **`/scripts/testing/test_writer_workflow.php`** - End-to-end test script

**MODIFIED Files (3 files):**

1. **`/public/api/v1/writer_routes.php`** - Add image upload endpoint, update approve to queue social
2. **`/public/admin/src/App.tsx`** - Add writer routes
3. **`/lib/Services/Social/UplinkService.php`** - Replace 52-line stub with full implementation

**EXISTING Files (No Changes):**

- `/lib/Writer/ArticleService.php` - Editorial workflow (already complete)
- `/lib/Writer/ScoutService.php` - Anomaly detection (already complete)
- `/lib/Writer/NikoService.php` - Persona assignment (already complete)
- `/lib/Writer/DraftingService.php` - Article generation (already complete)
- `/lib/Writer/SafetyFilterService.php` - Content scanning (already complete)

---

## Timeline & Effort

| Phase | Days | Person-Days | Priority | Dependencies |
|-------|------|-------------|----------|--------------|
| 1. Database Schema | 1 | 0.5 | P0 | None |
| 2. Image Management | 3 | 2 | P1 | Phase 1 |
| 3. Admin V2 Dashboard | 5 | 4 | P1 | Phase 1, 2 |
| 4. G-Fleet Publishing | 5 | 5 | P0 | Phase 1, 2 |
| 5. Cron Automation | 1 | 0.5 | P1 | Phase 4 |
| 6. Testing | 2 | 2 | P1 | All phases |
| 7. Documentation | 1 | 0.5 | P2 | All phases |
| **TOTAL** | **18 days** | **14.5 days** | | |

**Resource:** 1 full-stack developer
**Calendar Duration:** 3-4 weeks (includes testing buffer)

---

## Success Metrics

**Technical KPIs:**
- ✅ Editorial queue loads in <1s
- ✅ Image upload + format generation <5s
- ✅ Social posts publish within 15 min of scheduling
- ✅ >95% publish success rate
- ✅ <3 retries per failed post

**Business KPIs:**
- ✅ 20-30 articles generated per day
- ✅ >85% editorial approval rate
- ✅ >5% average social engagement
- ✅ <$0.50 cost per article (LLM + API costs)
- ✅ <10 min moderator review time per article

---

## Verification Steps

After implementation:

1. **Database Setup:**
   ```bash
   php scripts/setup/create_social_posts_table.php
   php scripts/setup/alter_writer_articles_images.php
   mysql -u root -p ngn_db -e "SELECT COUNT(*) FROM social_posts"
   ```

2. **Image Formatting Test:**
   ```bash
   php scripts/testing/test_image_formatting.php
   ls -lh /storage/uploads/writer/social/ # Check generated images
   ```

3. **Admin UI Access:**
   - Navigate to `/admin/writer/editorial-queue`
   - Verify articles load from database
   - Click article → verify editor loads
   - Upload image → verify square + story auto-generated

4. **Social Publishing:**
   ```bash
   # Manual queue test
   php scripts/testing/test_social_queue.php

   # Check queue status
   mysql -u root -p ngn_db -e "SELECT * FROM social_posts WHERE status = 'pending'"

   # Run cron manually
   php scripts/cron/social_publish_queue.php

   # Verify published
   mysql -u root -p ngn_db -e "SELECT * FROM social_posts WHERE status = 'published'"
   ```

5. **End-to-End Workflow:**
   ```bash
   # Trigger full pipeline
   php scripts/cron/scout_anomaly_detection.php
   php scripts/cron/niko_dispatcher.php
   # Wait for drafting cron or manually trigger
   # Review in Admin UI → Approve
   # Wait 15 min for publish cron
   # Check Instagram/Facebook for post
   ```

6. **Instagram Verification:**
   - Check Instagram account for new post (feed)
   - Check Instagram story (expires after 24h)
   - Verify caption and image quality

7. **Monitoring:**
   ```bash
   tail -f /var/log/ngn/social_publish.log
   tail -f /var/log/ngn/writer_articles.log
   ```

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Instagram API rate limits | Medium | High | Implement fleet rotation, respect daily limits in g_fleet_accounts |
| Image formatting fails for some images | Medium | Medium | Add fallback to letterbox/pillarbox, validation on upload |
| LLM API costs exceed budget | Low | Medium | Monitor cost per article, set daily generation caps |
| Safety filter false positives | Medium | Low | Manual override capability, tune threshold |
| Social publish cron fails silently | Low | High | Add alerting via email/Slack on 3+ consecutive failures |
| Token expiration breaks publishing | High | Critical | Implement token refresh, alert 7 days before expiry |

---

## Dependencies & Prerequisites

**Composer Packages:**
```bash
composer require intervention/image
composer require facebook/graph-sdk
```

**Environment Variables (.env):**
```bash
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret
INSTAGRAM_APP_ID=your_instagram_app_id
INSTAGRAM_APP_SECRET=your_instagram_app_secret
```

**Server Requirements:**
- PHP GD or Imagick extension for image processing
- Crontab access for scheduled jobs
- Public-facing URL for Instagram image hosting (images must be accessible via HTTPS)
- SSL certificate (required for Meta Graph API)

**Instagram Business Account:**
- Instagram Business or Creator account
- Facebook Page connected to Instagram account
- Instagram Graph API access via Facebook Developer App
- Long-lived access tokens (60 days)

**Fleet Account Setup:**
1. Create Instagram Business account(s)
2. Generate access tokens via Facebook Graph API Explorer
3. Insert into g_fleet_accounts table:
   ```sql
   INSERT INTO g_fleet_accounts (platform, account_name, account_handle, instagram_user_id, access_token, token_expires_at, is_active)
   VALUES ('instagram', 'NGN Music', '@ngnmusic', '1234567890', 'LONG_LIVED_TOKEN', '2026-04-20 00:00:00', true);
   ```

---

## Final Recommendation

**Implement in order:** Phase 1 → Phase 2 → Phase 4 → Phase 3 → Phase 5 → Phase 6 → Phase 7

**Why this order:**
1. Database foundation first (enables all other work)
2. Image system next (required by both UI and publishing)
3. Social publishing service (CRITICAL PATH - most complex component)
4. Admin UI (can develop against mock data while Phase 4 builds)
5. Cron automation (final integration)
6. Testing (validate entire workflow)
7. Documentation & deployment

**Critical Path:** Phase 4 (G-Fleet Social Publishing) is the bottleneck - assign most experienced developer.

The Writer Engine backend is **production-ready**. This plan completes the missing UI, image handling, and social distribution to enable the full editorial workflow described in Bible Chapter 10.
