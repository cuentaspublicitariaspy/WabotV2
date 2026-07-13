<?php

require_once __DIR__ . '/config.php';

class KnowledgeManager
{
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? KNOWLEDGE_FILE;
    }

    public function getSystemPrompt(): string
    {
        if (!file_exists($this->filePath)) {
            return "Eres Lolia, una asistente virtual amable y servicial de Vendiendo En Internet. Respondes en español, usas 'vos' y sos conversacional.";
        }

        $content = file_get_contents($this->filePath);
        return $content !== false ? trim($content) : '';
    }

    public function getContent(): string
    {
        if (!file_exists($this->filePath)) {
            return '';
        }
        $content = file_get_contents($this->filePath);
        return $content !== false ? $content : '';
    }

    public function save(string $content): bool
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return file_put_contents($this->filePath, $content, LOCK_EX) !== false;
    }

    public function getStats(): array
    {
        if (!file_exists($this->filePath)) {
            return ['lines' => 0, 'size' => 0];
        }
        return [
            'lines' => count(file($this->filePath)),
            'size' => strlen(file_get_contents($this->filePath)),
        ];
    }
}
