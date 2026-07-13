<?php

require_once __DIR__ . '/Database.php';

class KnowledgeManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS knowledge_sources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function getAll(): array
    {
        $stmt = $this->db->query("SELECT id, title, LEFT(content, 200) AS preview, CHAR_LENGTH(content) AS size, created_at FROM knowledge_sources ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM knowledge_sources")->fetchColumn();
    }

    public function add(string $title, string $content): bool
    {
        if ($this->count() >= 5) return false;
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') return false;
        $stmt = $this->db->prepare("INSERT INTO knowledge_sources (title, content) VALUES (?, ?)");
        return $stmt->execute([$title, $content]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM knowledge_sources WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getSystemPrompt(): string
    {
        $sources = $this->db->query("SELECT title, content FROM knowledge_sources ORDER BY id ASC")->fetchAll();

        if (empty($sources)) {
            return "Eres Lolia, una asistente virtual amable y servicial de Vendiendo En Internet. Respondes en español, usas 'vos' y sos conversacional.";
        }

        $parts = [];
        foreach ($sources as $s) {
            $parts[] = "=== " . $s['title'] . " ===";
            $parts[] = $s['content'];
        }
        return implode("\n\n", $parts);
    }
}
