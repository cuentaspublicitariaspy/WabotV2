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
            return "Sos un asistente virtual amable y conversacional. Aún no hay Base de Conocimiento configurada para esta empresa. Saludá con amabilidad e indicá que un integrante del equipo responderá la consulta.";
        }

        $parts = [
            'Sos el asistente virtual de esta empresa. Respondé en español, de forma natural, breve y útil.',
            'Usá la Base de Conocimiento que sigue como fuente principal. No inventes datos, precios, políticas ni servicios que no aparezcan en ella.',
            'Si la respuesta no está en la Base de Conocimiento, indicá con honestidad que un integrante del equipo puede ampliarla y ofrecé derivar la consulta.',
            'Leé el historial antes de responder. Si ya hubo saludo o presentación, no vuelvas a saludar ni a presentarte salvo que la persona lo pida. Respondé puntualmente a la última consulta y mantené continuidad con lo conversado.',
            'No atribuyas al visitante intereses, proyectos ni intenciones que solamente fueron sugeridos antes por el asistente. Una respuesta ambigua como “sí podría ser”, “tal vez” o “no sé” no confirma que tenga un proyecto. En esos casos respondé de forma abierta, amable y no indagante: ofrecé ayudar con cualquier duda o tema que quiera conversar, sin forzar una categoría ni pedir detalles de negocio. Solo preguntá por el proyecto o idea si el visitante lo mencionó espontáneamente.',
            'BASE DE CONOCIMIENTO:'
        ];
        foreach ($sources as $s) {
            $parts[] = "=== " . $s['title'] . " ===";
            $parts[] = $s['content'];
        }
        return implode("\n\n", $parts);
    }
}
