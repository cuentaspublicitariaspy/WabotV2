<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ChatManager.php';

class TemplateManager
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function getTodas(): array
    {
        $stmt = $this->db->query("SELECT * FROM plantillas ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public function getActivas(): array
    {
        $stmt = $this->db->query("SELECT * FROM plantillas WHERE activo = 1 ORDER BY nombre");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM plantillas WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function crear(string $nombre, string $idioma, string $contenido, int $parametros): bool
    {
        $stmt = $this->db->prepare("INSERT INTO plantillas (nombre, idioma, contenido, parametros) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$nombre, $idioma, $contenido, $parametros]);
    }

    public function actualizar(int $id, string $nombre, string $idioma, string $contenido, int $parametros, bool $activo): bool
    {
        $stmt = $this->db->prepare("UPDATE plantillas SET nombre = ?, idioma = ?, contenido = ?, parametros = ?, activo = ? WHERE id = ?");
        return $stmt->execute([$nombre, $idioma, $contenido, $parametros, $activo ? 1 : 0, $id]);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM plantillas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function reemplazarParametros(string $contenido, array $valores): string
    {
        foreach ($valores as $i => $val) {
            $contenido = str_replace("{{" . ($i + 1) . "}}", $val, $contenido);
        }
        return $contenido;
    }

    public function enviarWhatsApp(string $telefono, int $plantillaId, array $parametros = []): bool
    {
        $plantilla = $this->getById($plantillaId);
        if (!$plantilla) return false;

        $url = "https://graph.facebook.com/v21.0/" . WHATSAPP_PHONE_NUMBER_ID . "/messages";

        $componentes = [
            [
                'type' => 'body',
                'parameters' => [],
            ],
        ];

        foreach ($parametros as $val) {
            $componentes[0]['parameters'][] = ['type' => 'text', 'text' => $val];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $telefono,
            'type' => 'template',
            'template' => [
                'name' => $plantilla['nombre'],
                'language' => ['code' => $plantilla['idioma']],
                'components' => $componentes,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . WHATSAPP_TOKEN,
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode >= 400) {
            error_log("WhatsApp template error: " . ($error ?: $response));
            return false;
        }

        $respData = json_decode($response, true);
        $waMessageId = $respData['messages'][0]['id'] ?? null;

        $contenido = $this->reemplazarParametros($plantilla['contenido'], $parametros);
        $chatManager = new \ChatManager();
        $conversacionId = $chatManager->getOrCreateConversacion($telefono);
        $chatManager->guardarMensaje($conversacionId, $contenido, 'out', $waMessageId);
        $chatManager->actualizarConversacion($conversacionId, $contenido, 'respondido');

        return true;
    }
}
