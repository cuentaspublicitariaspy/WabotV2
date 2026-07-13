<?php

class License
{
    private static ?bool $cache = null;
    private static int $cacheTime = 0;
    private static int $cacheTtl = 3600; // 1 hora

    public static function check(): bool
    {
        $now = time();

        if (self::$cache !== null && ($now - self::$cacheTime) < self::$cacheTtl) {
            return self::$cache;
        }

        $serverUrl = 'https://wabot-cdn.vercel.app';
        $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $key = LICENSE_KEY;

        if ($serverUrl === '' || $key === '') {
            self::$cache = true;
            self::$cacheTime = $now;
            return true;
        }

        $url = rtrim($serverUrl, '/') . "/api/license/check?domain=" . urlencode($domain) . "&key=" . urlencode($key);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode !== 200) {
            // Si el license server no responde, usamos la última respuesta en caché
            if (self::$cache !== null) {
                return self::$cache;
            }
            self::$cache = true;
            self::$cacheTime = $now;
            return true;
        }

        $data = json_decode($resp, true);
        $activo = !empty($data['activo']);

        self::$cache = $activo;
        self::$cacheTime = $now;

        return $activo;
    }

    public static function require(): void
    {
        if (!self::check()) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok']);
            exit;
        }
    }
}
