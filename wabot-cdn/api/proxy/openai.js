const { getClient, getAllClients } = require('../_lib/kv');

const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_TRANSCRIBE_URL = 'https://api.openai.com/v1/audio/transcriptions';

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
    const { action, license_key, api_key, messages, audio_base64, model } = req.body || {};

    if (!action) {
      res.status(400).json({ success: false, error: 'action requerida (chat o transcribe)' });
      return;
    }

    // Toda llamada de WC debe pertenecer a un cliente activo. En la parte
    // pública del Chatbot usamos su API Key; en WhatsApp/administración se
    // valida la License Key. Ningún mensaje se persiste en WS.
    let client = null;
    let resolvedApiKey = api_key || '';
    if (api_key) client = await getClient(api_key);
    if (!client && license_key) {
      const clients = await getAllClients();
      for (const candidate of Object.keys(clients)) {
        const possibleClient = await getClient(candidate);
        if (possibleClient?.license_key === license_key) {
          client = possibleClient;
          resolvedApiKey = candidate;
          break;
        }
      }
    }
    if (!client || (license_key && client.license_key !== license_key)) {
      res.status(403).json({ success: false, error: 'Licencia o cliente no autorizado' });
      return;
    }

    const openaiKey = process.env.OPENAI_API_KEY || process.env.OpenAIKey;
    if (!openaiKey) {
      res.status(500).json({ success: false, error: 'Error de configuración del servidor' });
      return;
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 25000);

    if (action === 'chat') {
      if (!messages) {
        clearTimeout(timeout);
        res.status(400).json({ success: false, error: 'messages requerido' });
        return;
      }

      // La misma agenda se usa desde WhatsApp y el Chatbot. WS interpreta la
      // intención y WC ejecuta la validación/acción de forma determinística.
      const agendaTools = client.client_url ? [{
        type: 'function', function: {
          name: 'agenda', description: 'Consulta o crea citas solo con disponibilidad real. Nunca inventes horarios.',
          parameters: { type: 'object', additionalProperties: false, required: ['accion'], properties: {
            accion: { type: 'string', enum: ['catalogo', 'disponibilidad', 'crear'] }, servicio_id: { type: 'integer' }, profesional_id: { type: 'integer' }, sucursal_id: { type: 'integer' }, fecha: { type: 'string' }, inicio: { type: 'string' }, nombre_cliente: { type: 'string' }, telefono: { type: 'string' }, email: { type: 'string' }, motivo: { type: 'string' }, confirmada: { type: 'boolean' }
          }}
        }
      }] : [];
      async function agendaCall(args) {
        const base = client.client_url.replace(/\/+$/, '');
        const map = { catalogo: 'catalogo', disponibilidad: 'disponibilidad', crear: 'crear' };
        const payload = { ...args, action: map[args.accion] || '', api_key: resolvedApiKey, canal: 'whatsapp' };
        for (const url of [`${base}/wabot/api/agenda/assistant.php`, `${base}/api/agenda/assistant.php`]) {
          try {
            const response = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            const data = await response.json();
            if (response.ok || response.status !== 404) return data;
          } catch { /* compatible installation path */ }
        }
        return { success: false, error: 'La agenda no está disponible todavía.' };
      }

      let openaiRes = await fetch(OPENAI_CHAT_URL, {
        method: 'POST',
        signal: controller.signal,
        headers: {
          'Authorization': `Bearer ${openaiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          model: model || 'gpt-4o-mini',
          messages: [{ role: 'system', content: 'Si hay una solicitud de cita, usá la herramienta agenda. No inventes disponibilidad y solo confirmá una reserva después de una confirmación explícita del cliente.' }, ...messages],
          tools: agendaTools.length ? agendaTools : undefined,
          tool_choice: agendaTools.length ? 'auto' : undefined,
          max_tokens: 1024,
          temperature: 0.7
        })
      });
      clearTimeout(timeout);

      let data = await openaiRes.json();
      if (!openaiRes.ok) {
        res.status(openaiRes.status).json({
          success: false,
          error: 'OpenAI no pudo generar una respuesta',
          detail: data?.error?.message || null
        });
        return;
      }

      const toolCalls = data?.choices?.[0]?.message?.tool_calls || [];
      if (toolCalls.length) {
        const toolMessages = [{ role: 'system', content: 'Si hay una solicitud de cita, usá la herramienta agenda. No inventes disponibilidad y solo confirmá una reserva después de una confirmación explícita del cliente.' }, ...messages, data.choices[0].message];
        for (const call of toolCalls) {
          let args = {}; try { args = JSON.parse(call.function.arguments || '{}'); } catch { args = {}; }
          toolMessages.push({ role: 'tool', tool_call_id: call.id, content: JSON.stringify(await agendaCall(args)) });
        }
        const followup = await fetch(OPENAI_CHAT_URL, { method: 'POST', headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: model || 'gpt-4o-mini', messages: toolMessages, max_tokens: 1024, temperature: 0.4 }) });
        if (followup.ok) data = await followup.json();
      }
      const content = data?.choices?.[0]?.message?.content?.trim();
      if (!content) {
        res.status(502).json({ success: false, error: 'OpenAI devolvió una respuesta vacía' });
        return;
      }

      // Contrato estable para WC. No se filtra la respuesta completa de OpenAI.
      res.json({ success: true, content });
      return;
    }

    if (action === 'transcribe') {
      if (!audio_base64) {
        clearTimeout(timeout);
        res.status(400).json({ success: false, error: 'audio_base64 requerido' });
        return;
      }

      const buffer = Buffer.from(audio_base64, 'base64');
      const blob = new Blob([buffer], { type: 'audio/ogg' });
      const formData = new FormData();
      formData.append('file', blob, 'audio.ogg');
      formData.append('model', 'whisper-1');

      const openaiRes = await fetch(OPENAI_TRANSCRIBE_URL, {
        method: 'POST',
        signal: controller.signal,
        headers: { 'Authorization': `Bearer ${openaiKey}` },
        body: formData
      });
      clearTimeout(timeout);

      const data = await openaiRes.json();
      res.json(data);
      return;
    }

    clearTimeout(timeout);
    res.status(400).json({ success: false, error: 'acción no válida' });
  } catch (err) {
    if (err.name === 'AbortError') {
      res.status(504).json({ success: false, error: 'Tiempo de espera agotado' });
      return;
    }
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
