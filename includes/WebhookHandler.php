<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ChatManager.php';
require_once __DIR__ . '/KnowledgeManager.php';
require_once __DIR__ . '/OpenAI.php';
require_once __DIR__ . '/AgentRouter.php';
require_once __DIR__ . '/AIAnalyzer.php';
require_once __DIR__ . '/MetricsCollector.php';

class WebhookHandler
{
    private ChatManager $chatManager;
    private AgentRouter $agentRouter;

    public function __construct()
    {
        $this->chatManager = new ChatManager();
        $this->agentRouter = new AgentRouter();
    }

    public function handleVerification(): void
    {
        $mode = $_GET['hub_mode'] ?? '';
        $token = $_GET['hub_verify_token'] ?? '';
        $challenge = $_GET['hub_challenge'] ?? '';

        if ($mode === 'subscribe' && $token === WHATSAPP_VERIFY_TOKEN) {
            echo $challenge;
            return;
        }

        http_response_code(403);
        echo json_encode(['error' => 'Verification failed']);
    }

    public function handleMessage(string $rawBody): void
    {
        $input = json_decode($rawBody, true);

        if (!$input || !isset($input['entry'][0]['changes'][0]['value'])) {
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            return;
        }

        $change = $input['entry'][0]['changes'][0]['value'];
        $phoneNumberId = $change['metadata']['phone_number_id'] ?? WHATSAPP_PHONE_NUMBER_ID;

        if (isset($change['messages'][0])) {
            $message = $change['messages'][0];
            $waPhone = $change['contacts'][0]['wa_id'] ?? '';
            $waName = $change['contacts'][0]['profile']['name'] ?? '';
            $messageId = $message['id'] ?? '';
            $messageType = $message['type'] ?? '';

            if (!$this->isValidForProcessing($waPhone, $messageId)) {
                http_response_code(200);
                echo json_encode(['status' => 'ok']);
                return;
            }

            $this->chatManager->markProcessed($messageId);

            if ($messageType === 'text') {
                $textBody = trim($message['text']['body'] ?? '');
                if ($textBody !== '') {
                    $this->processIncomingMessage($waPhone, $waName, $textBody, $messageId, $phoneNumberId);
                }
            }
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    }

    public function sendMessage(string $to, string $text, string $phoneNumberId = '', ?int $usuarioId = null): ?int
    {
        $phoneNumberId = $phoneNumberId ?: WHATSAPP_PHONE_NUMBER_ID;
        $url = "https://graph.facebook.com/v21.0/" . $phoneNumberId . "/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
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
            error_log("WhatsApp API error: " . ($error ?: $response));
            return null;
        }

        $respData = json_decode($response, true);
        $waMessageId = $respData['messages'][0]['id'] ?? null;

        $conversacionId = $this->chatManager->getOrCreateConversacion($to);
        $mensajeId = $this->chatManager->guardarMensaje($conversacionId, $text, 'out', $waMessageId, $usuarioId);
        $this->chatManager->actualizarConversacion($conversacionId, $text, 'respondido');

        return $mensajeId;
    }

    public function markAsRead(string $to, string $messageId, string $phoneNumberId = ''): void
    {
        $phoneNumberId = $phoneNumberId ?: WHATSAPP_PHONE_NUMBER_ID;
        $url = "https://graph.facebook.com/v21.0/" . $phoneNumberId . "/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
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
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    private function isValidForProcessing(string $waPhone, string $messageId): bool
    {
        if ($waPhone === '' || $messageId === '') {
            return false;
        }

        if ($this->chatManager->isProcessed($messageId)) {
            return false;
        }

        return true;
    }

    private function processIncomingMessage(string $waPhone, string $waName, string $text, string $messageId, string $phoneNumberId): void
    {
        $this->markAsRead($waPhone, $messageId, $phoneNumberId);

        $conversacionId = $this->chatManager->getOrCreateConversacion($waPhone, $waName);
        $mensajeInId = $this->chatManager->guardarMensaje($conversacionId, $text, 'in', $messageId);
        $this->chatManager->actualizarConversacion($conversacionId, $text, 'pendiente');

        $comando = strtolower(trim($text));
        if (in_array($comando, ['/reset', '/limpiar', '/start'], true)) {
            $this->chatManager->resetConversacion($conversacionId);
            $this->sendMessage($waPhone, 'Conversación reiniciada. ¿En qué puedo ayudarte?', $phoneNumberId);
            return;
        }

        if (OPENAI_API_KEY === '') {
            return;
        }

        $hayAgentes = $this->agentRouter->hayAgentesDisponibles();

        if ($hayAgentes) {
            $agenteId = $this->agentRouter->getAgenteParaAsignar();
            if ($agenteId !== null) {
                $this->agentRouter->asignarConversacion($conversacionId, $agenteId);
            }
            return;
        }

        $conversacion = $this->chatManager->getConversacion($conversacionId);
        if ($conversacion && $conversacion['departamento'] === null) {
            $analyzer = new AIAnalyzer();
            $departamento = $analyzer->clasificar($text);
            $stmt = Database::getConnection()->prepare("UPDATE conversaciones SET departamento = ? WHERE id = ?");
            $stmt->execute([$departamento, $conversacionId]);
        }

        $knowledge = new KnowledgeManager();
        $openai = new OpenAI();

        $historial = $this->chatManager->getHistorial($conversacionId);
        $messages = [];
        $systemPrompt = $knowledge->getSystemPrompt();
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($historial as $msg) {
            $messages[] = $msg;
        }

        $response = $openai->chat($messages);

        if ($response !== null) {
            $mensajeOutId = $this->sendMessage($waPhone, $response, $phoneNumberId);

            if ($mensajeOutId !== null) {
                $metrics = new MetricsCollector();
                $metrics->registrarRespuesta($conversacionId, $mensajeInId, $mensajeOutId, null, true);
            }
        } else {
            error_log("OpenAI falló para conversación $conversacionId");
        }
    }
}
