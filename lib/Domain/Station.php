<?php
namespace NGN\Domain;

class Station
{
    public int $id;
    public string $name;
    public ?string $callSign;
    public ?string $region;
    public ?string $format;

    public function __construct(int $id, string $name, ?string $callSign = null, ?string $region = null, ?string $format = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->callSign = $callSign;
        $this->region = $region;
        $this->format = $format;
    }

    public static function fromArray(array $row): self
    {
        $id = (int)($row['id'] ?? 0);
        $name = trim((string)($row['name'] ?? ($row['title'] ?? ($row['call_sign'] ?? ''))));
        $call = isset($row['call_sign']) ? (string)$row['call_sign'] : null;
        $region = isset($row['region']) ? (string)$row['region'] : null;
        $format = isset($row['format']) ? (string)$row['format'] : null;
        return new self($id, $name, $call, $region, $format);
    }

    public function displayName(): string
    {
        if ($this->name !== '') return $this->name;
        if ($this->callSign) return $this->callSign;
        return 'Unknown Station';
    }
}
