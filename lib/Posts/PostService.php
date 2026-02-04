<?php
namespace NGN\Lib\Posts;

use NGN\Lib\Config;
use NGN\Lib\DB\ConnectionFactory;
use PDO;
use PDOException;

class PostService
{
    private Config $config;
    private PDO $read;
    private PDO $write;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->read = ConnectionFactory::read($config);
        $this->write = ConnectionFactory::write($config);
    }

    public function list(array $filters = [], int $limit = 20, int $offset = 0): array
    {
        $where = ['(DeletedAt IS NULL OR DeletedAt = 0)'];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'Status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['q'])) { $where[] = '(Title LIKE :q OR Body LIKE :q)'; $params[':q'] = '%' . $filters['q'] . '%'; }
        $sql = 'SELECT Id, Slug, Title, Status, PublishedAt, UpdatedAt, CreatedAt FROM posts';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY COALESCE(PublishedAt, UpdatedAt, CreatedAt) DESC LIMIT :limit OFFSET :offset';
        $stmt = $this->read->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit', max(1, min(100, $limit)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows ?: [];
    }

    public function count(array $filters = []): int
    {
        $where = ['(DeletedAt IS NULL OR DeletedAt = 0)'];
        $params = [];
        if (!empty($filters['status'])) { $where[] = 'Status = :status'; $params[':status'] = $filters['status']; }
        if (!empty($filters['q'])) { $where[] = '(Title LIKE :q OR Body LIKE :q)'; $params[':q'] = '%' . $filters['q'] . '%'; }
        $sql = 'SELECT COUNT(*) as c FROM posts';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->read->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->read->prepare('SELECT * FROM posts WHERE Id = :id AND (DeletedAt IS NULL OR DeletedAt = 0)');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->read->prepare('SELECT * FROM posts WHERE Slug = :slug AND (DeletedAt IS NULL OR DeletedAt = 0)');
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $sql = 'INSERT INTO Posts (Slug, Title, Body, Status, PublishedAt, CreatedAt, UpdatedAt) VALUES (:slug, :title, :body, :status, :published_at, :created_at, :updated_at)';
        $stmt = $this->write->prepare($sql);
        $stmt->execute([
            ':slug' => $data['slug'],
            ':title' => $data['title'],
            ':body' => $data['body'] ?? '',
            ':status' => $data['status'] ?? 'draft',
            ':published_at' => $data['published_at'] ?? null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $id = (int)$this->write->lastInsertId();
        $row = $this->getById($id);
        return $row ?? ['Id' => $id];
    }

    public function update(int $id, array $data): ?array
    {
        $existing = $this->getById($id);
        if (!$existing) return null;
        $fields = [];
        $params = [':id' => $id];
        foreach ([
            'slug' => 'Slug',
            'title' => 'Title',
            'body' => 'Body',
            'status' => 'Status',
            'published_at' => 'PublishedAt',
        ] as $k => $col) {
            if (array_key_exists($k, $data)) {
                $fields[] = "$col = :$k";
                $params[":$k"] = $data[$k];
            }
        }
        $fields[] = 'UpdatedAt = :updated_at';
        $params[':updated_at'] = date('Y-m-d H:i:s');
        if (empty($fields)) return $existing;
        $sql = 'UPDATE Posts SET ' . implode(', ', $fields) . ' WHERE Id = :id';
        $stmt = $this->write->prepare($sql);
        $stmt->execute($params);
        return $this->getById($id);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->write->prepare('UPDATE Posts SET DeletedAt = :ts WHERE Id = :id');
        return $stmt->execute([':id' => $id, ':ts' => date('Y-m-d H:i:s')]);
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT Id FROM posts WHERE Slug = :slug AND (DeletedAt IS NULL OR DeletedAt = 0)';
        $params = [':slug' => $slug];
        if ($excludeId !== null) { $sql .= ' AND Id <> :exclude'; $params[':exclude'] = $excludeId; }
        $stmt = $this->read->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetch();
    }
}
