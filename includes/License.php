<?php

class License
{
    private static ?array $detailsCache = null;
    private static bool $detailsLoaded = false;
    private static int $cacheTtl = 30;
    private static int $staleGrace = 3600;

    public static function check(): bool
    {
        return !empty(self::details()['activo']);
    }

    public static function capabilities(): array
    {
        $details = self::details();
        return is_array($details['capabilities'] ?? null) ? $details['capabilities'] : [];
    }

    public static function hasCapability(string $capability): bool
    {
        if (!self::check()) return false;
        return !empty(self::capabilities()[$capability]);
    }

    /**
     * Protege tanto páginas como endpoints. Ocultar el menú es solo UX; este
     * control de servidor es el que impide usar una capacidad deshabilitada.
     */
    public static function requireCapability(string $capability, bool $json = false): void
    {
        if (self::hasCapability($capability)) return;

        http_response_code(403);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'code' => 'CAPABILITY_DISABLED',
                'error' => 'Esta capacidad no está habilitada para el cliente.'
            ]);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $safeName = htmlspecialchars(ucfirst($capability), ENT_QUOTES, 'UTF-8');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>Capacidad no habilitada - Wabot</title><style>body{margin:0;background:#f8fafc;color:#0f172a;font-family:system-ui,sans-serif;display:grid;place-items:center;min-height:100vh;padding:24px;box-sizing:border-box}.card{max-width:520px;background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:32px;box-shadow:0 18px 50px rgba(15,23,42,.08)}h1{font-size:24px;margin:0 0 12px}p{color:#64748b;line-height:1.6}a{display:inline-block;margin-top:12px;background:#059669;color:#fff;text-decoration:none;padding:11px 18px;border-radius:12px;font-weight:700}</style></head><body><main class="card"><h1>'.$safeName.' no está habilitada</h1><p>La capacidad existe, pero debe habilitarse para este cliente desde WS. Sus datos permanecen intactos.</p><a href="index.php">Volver al Dashboard</a></main></body></html>';
        exit;
    }

    /** Mantiene el comportamiento histórico para mensajes entrantes. */
    public static function require(): void
    {
        if (!self::check()) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok']);
            exit;
        }
    }

    public static function details(): array
    {
        if (self::$detailsLoaded) return self::$detailsCache ?? self::inactiveDetails();
        self::$detailsLoaded = true;

        $now = time();
        $cached = self::readPersistentCache();
        if ($cached && ($now - (int)$cached['fetched_at']) < self::$cacheTtl) {
            self::$detailsCache = self::normalizeDetails($cached['payload']);
            return self::$detailsCache;
        }

        $fresh = self::fetchFromServer();
        if ($fresh !== null) {
            self::$detailsCache = self::normalizeDetails($fresh);
            self::writePersistentCache($fresh);
            return self::$detailsCache;
        }

        // Una caída breve de WS no apaga de golpe capacidades ya autorizadas.
        // La gracia es corta y nunca reemplaza la autoridad de WS.
        if ($cached && ($now - (int)$cached['fetched_at']) < self::$staleGrace) {
            self::$detailsCache = self::normalizeDetails($cached['payload']);
            return self::$detailsCache;
        }

        self::$detailsCache = self::inactiveDetails();
        return self::$detailsCache;
    }

    private static function fetchFromServer(): ?array
    {
        $serverUrl = 'https://wabot-cdn.vercel.app';
        $domain = self::requestDomain();
        $key = defined('LICENSE_KEY') ? trim((string)LICENSE_KEY) : '';
        if ($serverUrl === '' || $key === '' || !function_exists('curl_init')) return null;

        $url = rtrim($serverUrl, '/') . '/api/license/check?domain=' . urlencode($domain) . '&key=' . urlencode($key);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error || $httpCode !== 200 || !is_string($response)) return null;

        $data = json_decode($response, true);
        if (!is_array($data)) return null;
        if (!self::verifyCapabilityManifest($data, $key)) return null;
        return $data;
    }

    private static function verifyCapabilityManifest(array $data, string $licenseKey): bool
    {
        $manifest = $data['capability_manifest'] ?? null;
        $signature = (string)($data['capability_signature'] ?? '');

        // Compatibilidad durante el despliegue escalonado de WS y WC.
        if (!is_array($manifest) || $signature === '') return true;
        if ((int)($manifest['version'] ?? 0) !== 1) return false;
        $issuedAt = (string)($manifest['issued_at'] ?? '');
        $agenda = !empty($manifest['capabilities']['agenda']);
        $material = 'v1|' . $issuedAt . '|agenda=' . ($agenda ? '1' : '0');
        $expected = hash_hmac('sha256', $material, $licenseKey);
        return hash_equals($expected, $signature);
    }

    private static function normalizeDetails(array $data): array
    {
        $active = !empty($data['activo']);
        $capabilities = $data['capability_manifest']['capabilities'] ?? $data['capabilities'] ?? null;
        if (!is_array($capabilities)) {
            // Las instalaciones existentes tenían Agenda antes del manifiesto.
            $capabilities = ['agenda' => $active];
        }
        return [
            'activo' => $active,
            'nombre' => (string)($data['nombre'] ?? ''),
            'api_key' => (string)($data['api_key'] ?? ''),
            'client_url' => (string)($data['client_url'] ?? ''),
            'capabilities' => [
                'agenda' => $active && !empty($capabilities['agenda'])
            ]
        ];
    }

    private static function inactiveDetails(): array
    {
        return ['activo' => false, 'nombre' => '', 'api_key' => '', 'client_url' => '', 'capabilities' => ['agenda' => false]];
    }

    private static function requestDomain(): string
    {
        $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
        $host = preg_replace('/:\d+$/', '', $host) ?: 'localhost';
        return preg_replace('/^www\./', '', $host) ?: $host;
    }

    private static function cachePath(): string
    {
        $key = defined('LICENSE_KEY') ? (string)LICENSE_KEY : '';
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'wabot-license-' . hash('sha256', $key . '|' . self::requestDomain()) . '.json';
    }

    private static function readPersistentCache(): ?array
    {
        $path = self::cachePath();
        if (!is_file($path) || !is_readable($path)) return null;
        $decoded = json_decode((string)@file_get_contents($path), true);
        if (!is_array($decoded) || !isset($decoded['fetched_at']) || !is_array($decoded['payload'] ?? null)) return null;
        return $decoded;
    }

    private static function writePersistentCache(array $payload): void
    {
        $encoded = json_encode(['fetched_at' => time(), 'payload' => $payload]);
        if ($encoded === false) return;
        @file_put_contents(self::cachePath(), $encoded, LOCK_EX);
    }
}
