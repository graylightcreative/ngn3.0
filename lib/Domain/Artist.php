<?php
namespace NGN\Domain;

class Artist
{
    public int $id;
    public ?string $slug;
    public string $name;
    public ?string $imageUrl;

    public function __construct(int $id, string $name, ?string $slug = null, ?string $imageUrl = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->imageUrl = $imageUrl;
    }

    public static function fromArray(array $row): self
    {
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ($row['title'] ?? '')));
        $slug = isset($row['slug']) ? (string)$row['slug'] : null;
        $image = isset($row['image_url']) ? (string)$row['image_url'] : (isset($row['image']) ? (string)$row['image'] : null);
        return new self($id, $name, $slug, $image);
    }

    public function displayName(): string
    {
        return $this->name !== '' ? $this->name : 'Unknown Artist';
    }
}
