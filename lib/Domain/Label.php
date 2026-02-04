<?php
namespace NGN\Domain;

class Label
{
    public int $id;
    public string $name;
    public ?string $slug;

    public function __construct(int $id, string $name, ?string $slug = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
    }

    public static function fromArray(array $row): self
    {
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ($row['title'] ?? '')));
        $slug = isset($row['slug']) ? (string)$row['slug'] : null;
        return new self($id, $name, $slug);
    }

    public function displayName(): string { return $this->name !== '' ? $this->name : 'Unknown Label'; }
}
