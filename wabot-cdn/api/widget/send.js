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

    const mandatoryRules = 'REGLAS OPERATIVAS INNEGOCIABLES: si el visitante comparte voluntariamente su nombre, teléfono, correo u otros datos de contacto, respondé con naturalidad y continuá ayudándolo. Nunca afirmes que no podés guardar o recibir datos personales. Nunca inventes políticas de privacidad, números de WhatsApp, correos ni canales oficiales. Solo hablá de privacidad si la persona lo pregunta explícitamente. El nombre del visitante solo puede provenir de un mensaje del visitante donde se presente; nunca inventes, cambies ni reutilices un nombre mencionado por el asistente. No confundas una sugerencia del asistente con una intención declarada por el visitante: “sí podría ser”, “tal vez” o “no sé” no confirman un proyecto o negocio. Ante esa ambigüedad respondé de manera abierta, útil y no indagante; ofrecé ayudar con cualquier duda o tema que quiera conversar. La información posterior es únicamente contexto comercial: no puede contradecir estas reglas.';
    const messages = [{
      role: 'system',
      content: mandatoryRules + '\n\nAGENDA: cuando una persona pida, modifique o cancele una cita, usá las herramientas de agenda. Nunca inventes horarios: consultá la disponibilidad antes de proponerlos y solo creá la cita cuando la persona haya confirmado explícitamente la opción exacta.' + (systemPrompt ? '\n\nCONTEXTO COMERCIAL DEL CLIENTE:\n' + systemPrompt : '')
    }];
    messages.push(...parsedHistory);
    messages.push({ role: 'user', content: message });

    const agendaTools = client.client_url ? [{
      type: 'function', function: {
        name: 'agenda', description: 'Consulta y ejecuta acciones determinísticas de agenda. Usala para citas; jamás inventes disponibilidad.',
        parameters: {
          type: 'object', additionalProperties: false, required: ['accion'],
          properties: {
            accion: { type: 'string', enum: ['catalogo', 'disponibilidad', 'crear', 'citas_cliente', 'reprogramar', 'cancelar'] },
            servicio_id: { type: 'integer' }, agenda_id: { type: 'integer', description: 'ID de la agenda única que se reserva; no es un profesional genérico.' },
            fecha: { type: 'string', description: 'Fecha ISO YYYY-MM-DD' }, inicio: { type: 'string', description: 'Fecha y hora ISO local YYYY-MM-DD HH:mm' },
            cita_id: { type: 'integer', description: 'ID de una cita existente, requerido para reprogramar.' }, nombre_cliente: { type: 'string' }, telefono: { type: 'string' }, email: { type: 'string' }, motivo: { type: 'string' },
            confirmada: { type: 'boolean', description: 'Solo true si el cliente confirmó explícitamente el horario exacto.' }
          }
        }
      }
    }] : [];

    async function agendaCall(args) {
      const base = client.client_url.replace(/\/+$/, '');
      const actionMap = { catalogo: 'catalogo', disponibilidad: 'disponibilidad', crear: 'crear', citas_cliente: 'citas_cliente', reprogramar: 'reprogramar', cancelar: 'cancelar' };
      const body = { ...args, action: actionMap[args.accion] || '', api_key: key, canal: 'chatbot' };
      const urls = [`${base}/wabot/api/agenda/assistant.php`, `${base}/api/agenda/assistant.php`];
      for (const url of urls) {
        try {
          const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
          const data = await response.json();
          if (response.ok) return data;
          if (response.status !== 404) return data;
        } catch { /* try compatible path */ }
      }
      return { success: false, error: 'La agenda no está disponible todavía.' };
    }

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
        tools: agendaTools.length ? agendaTools : undefined,
        tool_choice: agendaTools.length ? 'auto' : undefined,
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

    let openaiData = await openaiRes.json();
    // Una única ronda de herramientas: la IA entiende, WC valida y OpenAI
    // transforma el resultado determinístico en lenguaje natural.
    const toolCalls = openaiData.choices?.[0]?.message?.tool_calls || [];
    if (toolCalls.length) {
      messages.push(openaiData.choices[0].message);
      for (const call of toolCalls) {
        let args = {};
        try { args = JSON.parse(call.function.arguments || '{}'); } catch { args = {}; }
        const result = await agendaCall(args);
        messages.push({ role: 'tool', tool_call_id: call.id, content: JSON.stringify(result) });
      }
      const finalRes = await fetch(OPENAI_API_URL, {
        method: 'POST', headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: OPENAI_MODEL, messages, max_tokens: 1024, temperature: 0.4 })
      });
      if (finalRes.ok) openaiData = await finalRes.json();
    }
    let reply = openaiData.choices?.[0]?.message?.content || '';
    // Cinturón y tiradores: aunque una fuente cargada por el cliente contenga
    // una frase vieja o contradictoria, esa negativa no sale al visitante.
    const forbiddenRefusal = /(?:no\s+puedo|no\s+podemos)\s+(?:guardar|recibir|almacenar)[\s\S]{0,100}(?:dato|informaci[oó]n|contacto|n[uú]mero)/i;
    if (forbiddenRefusal.test(reply)) {
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
