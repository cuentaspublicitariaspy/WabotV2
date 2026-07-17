const DEFAULT_TIMEOUT_MS = 4500;

function storageBases(clientUrl) {
  const clean = String(clientUrl || '').trim().replace(/\/+$/, '');
  if (!clean) return [];
  if (/\/wabot$/i.test(clean)) return [clean];
  return [`${clean}/wabot`, clean];
}

function sameSiteOrigin(clientUrl) {
  try {
    return new URL(clientUrl).origin;
  } catch {
    return '';
  }
}

async function postStoredMessage(url, origin, payload, timeoutMs = DEFAULT_TIMEOUT_MS) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(url, {
      method: 'POST',
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
        ...(origin ? { Origin: origin, Referer: `${origin}/` } : {})
      },
      body: JSON.stringify(payload)
    });
    if (!response.ok) return { ok: false, status: response.status };
    const data = await response.json().catch(() => ({}));
    return { ok: data?.success === true, status: response.status };
  } finally {
    clearTimeout(timeout);
  }
}

/**
 * Persiste en WC, nunca en WS. El navegador envía los mismos IDs en su
 * outbox; la clave única de widget_messages convierte ambos caminos en una
 * operación idempotente y evita mensajes duplicados.
 */
async function persistWidgetExchange({
  client,
  apiKey,
  sessionId,
  visitorMessage,
  assistantMessage,
  visitorMessageId,
  assistantMessageId
}) {
  const bases = storageBases(client?.client_url);
  const origin = sameSiteOrigin(client?.client_url);
  if (!bases.length || !apiKey || !sessionId || !visitorMessageId || !assistantMessageId) {
    return { stored: false, reason: 'missing_routing_data' };
  }

  const messages = [
    { role: 'visitor', content: String(visitorMessage || ''), client_message_id: visitorMessageId },
    { role: 'assistant', content: String(assistantMessage || ''), client_message_id: assistantMessageId }
  ];

  let lastStatus = 0;
  for (const base of bases) {
    const url = `${base}/api/widget/store.php`;
    let stored = true;
    try {
      for (const item of messages) {
        const result = await postStoredMessage(url, origin, {
          api_key: apiKey,
          session_id: sessionId,
          ...item
        });
        lastStatus = result.status || lastStatus;
        if (!result.ok) { stored = false; break; }
      }
    } catch (error) {
      stored = false;
      if (error?.name !== 'AbortError') {
        console.warn('[widget-storage] WC persistence attempt failed', error?.message || error);
      }
    }
    if (stored) return { stored: true, base };
    if (lastStatus && lastStatus !== 404) break;
  }

  return { stored: false, reason: lastStatus ? `http_${lastStatus}` : 'network_error' };
}

module.exports = { storageBases, persistWidgetExchange };
