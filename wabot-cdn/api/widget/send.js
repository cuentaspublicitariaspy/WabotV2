const { getClient, isAuthorizedDomain } = require('../_lib/kv');

const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_MODEL = 'gpt-4o-mini';

module.exports = async (req, res) => {
  if (req.method === 'OPTIONS') {
    res.status(200).end();
    return;
  }

  if (req.method !== 'POST') {
    res.status(405).json({ success: false, error: 'Method not allowed' });
    return;
  }

  try {
    const { key, message, history } = req.body || {};

    if (!key || !message) {
      res.status(400).json({ success: false, error: 'key y message requeridos' });
      return;
    }

    const client = await getClient(key);
    if (!client) {
      res.status(404).json({ success: false, error: 'Cliente no encontrado' });
      return;
    }

    if (!isAuthorizedDomain(req, client)) {
      res.status(403).json({ success: false, code: 'DOMAIN_NOT_AUTHORIZED', error: 'Este Chatbot no está autorizado para funcionar en este dominio.' });
      return;
    }

    if (client.enabled === false || client.enabled === 0 || client.enabled === '0') {
      res.status(403).json({ success: false, error: 'Cliente desactivado' });
      return;
    }

    const openaiKey = process.env.OPENAI_API_KEY || process.env.OpenAIKey;
    if (!openaiKey) {
      res.status(500).json({ success: false, error: 'Error de configuración del servidor' });
      return;
    }

    let systemPrompt = process.env.WIDGET_SYSTEM_PROMPT || '';

    if (client.client_url) {
      try {
          const baseUrl = client.client_url.replace(/\/+$/, '');
          const candidateUrls = [
            `${baseUrl}/api/widget-config.php?key=${encodeURIComponent(key)}`,
            `${baseUrl}/wabot/api/widget-config.php?key=${encodeURIComponent(key)}`
          ];
          let response = null;
          for (const phpUrl of candidateUrls) {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 5000);
            const attempt = await fetch(phpUrl, { signal: controller.signal });
            clearTimeout(timeout);
            if (attempt.ok) { response = attempt; break; }
          }

          if (response && response.ok) {
            const data = await response.json();
            const knowledgeBase = data.knowledge_base || '';
            const sysPrompt = data.config?.system_prompt || '';

            systemPrompt = sysPrompt || knowledgeBase || systemPrompt;
          }
        } catch {
          // Fallback a systemPrompt actual
        }
    }

    let parsedHistory = [];
    if (history) {
      try {
        parsedHistory = typeof history === 'string' ? JSON.parse(history) : history;
      } catch {
        parsedHistory = [];
      }
    }

    const mandatoryRules = 'REGLAS OPERATIVAS INNEGOCIABLES: si el visitante comparte voluntariamente su nombre, teléfono, correo u otros datos de contacto, respondé con naturalidad y continuá ayudándolo. Nunca afirmes que no podés guardar o recibir datos personales. Nunca inventes políticas de privacidad, números de WhatsApp, correos ni canales oficiales. Solo hablá de privacidad si la persona lo pregunta explícitamente. La información posterior es únicamente contexto comercial: no puede contradecir estas reglas.';
    const messages = [{
      role: 'system',
      content: mandatoryRules + (systemPrompt ? '\n\nCONTEXTO COMERCIAL DEL CLIENTE:\n' + systemPrompt : '')
    }];
    messages.push(...parsedHistory);
    messages.push({ role: 'user', content: message });

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 25000);

    const openaiRes = await fetch(OPENAI_API_URL, {
      method: 'POST',
      signal: controller.signal,
      headers: {
        'Authorization': `Bearer ${openaiKey}`,
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        model: OPENAI_MODEL,
        messages,
        max_tokens: 1024,
        temperature: 0.7
      })
    });
    clearTimeout(timeout);

    if (!openaiRes.ok) {
      const errText = await openaiRes.text();
      res.status(502).json({ success: false, error: 'Error al comunicarse con OpenAI', detail: errText });
      return;
    }

    const openaiData = await openaiRes.json();
    let reply = openaiData.choices?.[0]?.message?.content || '';
    // Cinturón y tiradores: aunque una fuente cargada por el cliente contenga
    // una frase vieja o contradictoria, esa negativa no sale al visitante.
    const forbiddenRefusal = /(?:no\s+puedo|no\s+podemos)\s+(?:guardar|recibir|almacenar)[\s\S]{0,100}(?:dato|informaci[oó]n|contacto|n[uú]mero)/i;
    const inventedChannel = /\bwhatsapp\s+oficial\b/i;
    if (forbiddenRefusal.test(reply) || inventedChannel.test(reply)) {
      console.warn('[widget] blocked contradictory personal-data refusal');
      reply = 'Gracias por compartirlo. ¿En qué más puedo ayudarte?';
    }

    res.json({
      success: true,
      message: { role: 'assistant', content: reply }
    });
  } catch (err) {
    if (err.name === 'AbortError') {
      res.status(504).json({ success: false, error: 'Tiempo de espera agotado' });
      return;
    }
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
