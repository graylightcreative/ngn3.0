<?php
namespace NGN\Domain;

class Venue
{
    public int $id;
    public string $name;
    public ?string $city;
    public ?string $region;

    public function __construct(int $id, string $name, ?string $city = null, ?string $region = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->city = $city;
        $this->region = $region;
    }

    public static function fromArray(array $row): self
    {
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ($row['title'] ?? '')));
        $city = isset($row['city']) ? (string)$row['city'] : null;
        $region = isset($row['region']) ? (string)$row['region'] : null;
        return new self($id, $name, $city, $region);
    }

    public function displayName(): string { return $this->name !== '' ? $this->name : 'Unknown Venue'; }
}
