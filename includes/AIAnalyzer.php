<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/OpenAI.php';

class AIAnalyzer
{
    private OpenAI $openai;

    public function __construct()
    {
        $this->openai = new OpenAI();
    }

    public function clasificar(string $mensaje): string
    {
        $prompt = [
            [
                'role' => 'system',
                'content' => 'Clasificá el siguiente mensaje de un cliente en UNA de estas categorías exactas: ventas, soporte, administracion, general. Respondé SOLO con el nombre de la categoría, sin explicación, sin puntuación, sin mayúsculas.',
            ],
            ['role' => 'user', 'content' => $mensaje],
        ];

        $respuesta = $this->openai->chat($prompt);
        $categoria = trim(strtolower($respuesta ?? 'general'));
        $validas = ['ventas', 'soporte', 'administracion', 'general'];

        return in_array($categoria, $validas, true) ? $categoria : 'general';
    }
}
