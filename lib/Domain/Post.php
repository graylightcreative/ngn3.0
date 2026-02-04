<?php
namespace NGN\Domain;

class Post
{
    public int $id;
    public string $title;
    public ?string $slug;
    public ?string $teaser;
    public ?string $publishedAt;

    public function __construct(int $id, string $title, ?string $slug = null, ?string $teaser = null, ?string $publishedAt = null)
    {
        $this->id = $id;
        $this->title = $title;
        $this->slug = $slug;
        $this->teaser = $teaser;
        $this->publishedAt = $publishedAt;
    }

    public static function fromArray(array $row): self
    {
        $id = (int)($row['id'] ?? 0);
        $title = trim((string)($row['title'] ?? ($row['name'] ?? '')));
        $slug = isset($row['slug']) ? (string)$row['slug'] : null;
        $teaser = isset($row['teaser']) ? (string)$row['teaser'] : (isset($row['excerpt']) ? (string)$row['excerpt'] : null);
        $publishedAt = isset($row['published_at']) ? (string)$row['published_at'] : (isset($row['created_at']) ? (string)$row['created_at'] : null);
        return new self($id, $title, $slug, $teaser, $publishedAt);
    }

    public function displayTitle(): string { return $this->title !== '' ? $this->title : 'Untitled Post'; }
}
