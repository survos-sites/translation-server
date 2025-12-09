<?php
declare(strict_types=1);

namespace App\Util;

class HashCache
{
    private \SQLite3 $db;

    public function __construct(string $path)
    {
        $this->db = new \SQLite3($path);
        $this->db->exec('CREATE TABLE IF NOT EXISTS loaded_sources (hash TEXT PRIMARY KEY)');
    }

    public function has(string $hash): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM loaded_sources WHERE hash = :h');
        $stmt->bindValue(':h', $hash, SQLITE3_TEXT);
        $r = $stmt->execute()->fetchArray(SQLITE3_NUM);
        return $r !== false;
    }

    public function add(string $hash): void
    {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO loaded_sources (hash) VALUES (:h)');
        $stmt->bindValue(':h', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
}
