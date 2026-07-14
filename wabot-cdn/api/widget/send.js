const { getClient, getCachedConfig, setCachedConfig, isAuthorizedDomain } = require('../_lib/kv');

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
      const cached = await getCachedConfig(key);
      if (cached && cached._system_prompt) {
        systemPrompt = cached._system_prompt;
      } else {
        try {
          const phpUrl = `${client.client_url.replace(/\/+$/, '')}/api/widget-config.php?key=${encodeURIComponent(key)}`;
          const controller = new AbortController();
          const timeout = setTimeout(() => controller.abort(), 5000);
          const response = await fetch(phpUrl, { signal: controller.signal });
          clearTimeout(timeout);

          if (response.ok) {
            const data = await response.json();
            const knowledgeBase = data.knowledge_base || '';
            const sysPrompt = data.config?.system_prompt || '';

            systemPrompt = sysPrompt || knowledgeBase || systemPrompt;

            const configForCache = await getCachedConfig(key) || {};
            await setCachedConfig(key, { ...configForCache, _system_prompt: systemPrompt });
          }
        } catch {
          // Fallback a systemPrompt actual
        }
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

    const messages = [];
    if (systemPrompt) {
      messages.push({ role: 'system', content: systemPrompt });
    }
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
    const reply = openaiData.choices?.[0]?.message?.content || '';

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
