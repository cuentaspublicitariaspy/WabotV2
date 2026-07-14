<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ChatManager.php';
require_once __DIR__ . '/License.php';
require_once __DIR__ . '/KnowledgeManager.php';
require_once __DIR__ . '/AgentRouter.php';
require_once __DIR__ . '/MetricsCollector.php';

class WebhookHandler
{
    private ChatManager $chatManager;
    private AgentRouter $agentRouter;
    private string $whatsappToken;
    private string $whatsappPhoneNumberId;
    private string $proxyUrl = 'https://wabot-cdn.vercel.app/api/proxy/openai';

    public function __construct(string $whatsappToken = '', string $whatsappPhoneNumberId = '')
    {
        $this->chatManager = new ChatManager();
        $this->agentRouter = new AgentRouter();
        $this->whatsappToken = $whatsappToken ?: WHATSAPP_TOKEN;
        $this->whatsappPhoneNumberId = $whatsappPhoneNumberId ?: WHATSAPP_PHONE_NUMBER_ID;
    }

    public function handleMessage(string $rawBody, string $token = '', string $phoneNumberId = ''): void
    {
        if ($token) $this->whatsappToken = $token;
        if ($phoneNumberId) $this->whatsappPhoneNumberId = $phoneNumberId;

        License::require();

        $input = json_decode($rawBody, true);

        if (!$input || !isset($input['entry'][0]['changes'][0]['value'])) {
            http_response_code(200);
            echo json_encode(['status' => 'ok']);
            return;
        }

        $change = $input['entry'][0]['changes'][0]['value'];
        $phoneNumberId = $change['metadata']['phone_number_id'] ?? $this->whatsappPhoneNumberId;

        if (isset($change['messages'][0])) {
            $message = $change['messages'][0];

            $waPhone = str_replace('+', '', $message['from']);
            $waName = $change['contacts'][0]['profile']['name'] ?? 'Unknown';
            $messageId = $message['id'];

            if ($message['type'] === 'audio') {
                $mediaId = $message['audio']['id'];
                $text = $this->downloadAndTranscribeAudio($mediaId);
                if ($text === null) {
                    return;
                }
            } elseif ($message['type'] === 'text') {
                $text = $message['text']['body'];
            } else {
                return;
            }

            $this->processIncomingMessage($waPhone, $waName, $text, $messageId, $phoneNumberId);
        }
    }

    public function handleStatus(string $rawBody): void
    {
        $input = json_decode($rawBody, true);
        if (!$input) return;

        if (isset($input['entry'][0]['changes'][0]['value']['statuses'][0])) {
            $status = $input['entry'][0]['changes'][0]['value']['statuses'][0];
            $messageId = $status['id'] ?? '';
            $statusName = $status['status'] ?? '';

            if ($statusName === 'read') {
                $this->chatManager->marcarComoLeido($messageId);
            }
        }
    }

    public function markAsRead(string $waPhone, string $messageId, string $phoneNumberId): void
    {
        $url = "https://graph.facebook.com/v22.0/$phoneNumberId/messages";
        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->whatsappToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    public function sendMessage(string $to, string $text, string $phoneNumberId): ?string
    {
        $url = "https://graph.facebook.com/v22.0/$phoneNumberId/messages";
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->whatsappToken,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Cloud API responde 200 cuando acepta un mensaje. Algunas versiones
        // también pueden devolver otro 2xx válido, por lo que no hay que
        // reportar un falso error después de que el cliente ya lo recibió.
        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("WhatsApp send error: $httpCode - $resp");
            return null;
        }

        $result = json_decode($resp, true);
        return $result['messages'][0]['id'] ?? null;
    }

    private function downloadAndTranscribeAudio(string $mediaId): ?string
    {
        $mediaUrl = "https://graph.facebook.com/v22.0/$mediaId";
        $ch = curl_init($mediaUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->whatsappToken],
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("WhatsApp media info error: $httpCode");
            return null;
        }

        $mediaInfo = json_decode($resp, true);
        $downloadUrl = $mediaInfo['url'] ?? null;
        if (!$downloadUrl) {
            error_log("No media download URL");
            return null;
        }

        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->whatsappToken],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $audioData === false) {
            error_log("WhatsApp audio download error: $httpCode");
            return null;
        }

        $audioBase64 = base64_encode($audioData);
        $payload = json_encode([
            'action' => 'transcribe',
            'license_key' => LICENSE_KEY,
            'audio_base64' => $audioBase64,
        ]);

        $ch = curl_init($this->proxyUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Proxy transcribe error: $httpCode");
            return null;
        }

        $data = json_decode($resp, true);
        return $data['text'] ?? null;
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

        $hayAgentes = $this->agentRouter->hayAgentesDisponibles();

        if ($hayAgentes) {
            $conversacion = $this->chatManager->getConversacion($conversacionId);
            $agenteActual = $conversacion['asignado_a'] ?? null;

            if ($agenteActual !== null && $this->agentRouter->estaActivo((int)$agenteActual)) {
                return;
            }

            $agenteId = $this->agentRouter->getAgenteParaAsignar();
            if ($agenteId !== null) {
                $this->agentRouter->asignarConversacion($conversacionId, $agenteId);
            }
            return;
        }

        $conversacion = $this->chatManager->getConversacion($conversacionId);
        if ($conversacion && $conversacion['departamento'] === null) {
            $payload = json_encode([
                'action' => 'chat',
                'license_key' => LICENSE_KEY,
                'messages' => [
                    ['role' => 'system', 'content' => 'Clasificá el siguiente mensaje de un cliente en UNA de estas categorías exactas: ventas, soporte, administracion, general. Respondé SOLO con el nombre de la categoría, sin explicación, sin puntuación, sin mayúsculas.'],
                    ['role' => 'user', 'content' => $text],
                ],
            ]);
            $ch = curl_init($this->proxyUrl);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($resp, true);
            $departamento = trim(strtolower($this->getProxyContent($data) ?? 'general'));
            $validas = ['ventas', 'soporte', 'administracion', 'general'];
            if (!in_array($departamento, $validas, true)) $departamento = 'general';
            $stmt = Database::getConnection()->prepare("UPDATE conversaciones SET departamento = ? WHERE id = ?");
            $stmt->execute([$departamento, $conversacionId]);
        }

        $knowledge = new KnowledgeManager();
        $historial = $this->chatManager->getHistorial($conversacionId);
        $messages = [];
        $systemPrompt = $knowledge->getSystemPrompt();
        if ($systemPrompt !== '') {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($historial as $msg) {
            $messages[] = $msg;
        }

        $payload = json_encode([
            'action' => 'chat',
            'license_key' => LICENSE_KEY,
            'messages' => $messages,
        ]);
        $ch = curl_init($this->proxyUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($resp, true);
            $response = $this->getProxyContent($data);
            if ($response !== null) {
                $mensajeOutId = $this->sendMessage($waPhone, $response, $phoneNumberId);
                if ($mensajeOutId !== null) {
                    $metrics = new MetricsCollector();
                    $metrics->registrarRespuesta($conversacionId, $mensajeInId, $mensajeOutId, null, true);
                }
            } else {
                error_log("Proxy chat sin contenido para conversación $conversacionId");
            }
        } else {
            error_log("Proxy chat falló para conversación $conversacionId (HTTP $httpCode)");
        }
    }

    /**
     * WS expone la respuesta simplificada como `content`. Se conserva el
     * formato de OpenAI como respaldo para instalaciones durante una
     * actualización parcial entre WC y WS.
     */
    private function getProxyContent(array $data): ?string
    {
        $content = $data['content'] ?? ($data['choices'][0]['message']['content'] ?? null);
        if (!is_string($content)) {
            return null;
        }

        $content = trim($content);
        return $content === '' ? null : $content;
    }
}
