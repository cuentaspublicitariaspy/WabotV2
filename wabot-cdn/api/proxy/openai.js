const { getClient, getAllClients } = require('../_lib/kv');

const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_TRANSCRIBE_URL = 'https://api.openai.com/v1/audio/transcriptions';

function asuncionDate() {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
  const value = Object.fromEntries(parts.filter(p => p.type !== 'literal').map(p => [p.type, p.value]));
  return `${value.year}-${value.month}-${value.day}`;
}
function agendaInstructions() {
  return `AGENDA CONVERSACIONAL: hoy es ${asuncionDate()} en America/Asuncion. Interpretá “mañana”, días de la semana y mañana/tarde sin pedir una fecha exacta cuando ya existe una referencia natural. Consultá catálogo y luego disponibilidad real antes de ofrecer horarios. Si no hay lugar, usá próximos_horarios y ofrecé alternativas de los días siguientes. “Sí” confirma la última alternativa exacta propuesta. Pedí solo el dato faltante, sin tono de formulario. Para reprogramar o cancelar, buscá primero las citas activas y aclarar cuál si hay varias. Nunca inventes fechas, horarios, disponibilidad ni ignores buffers.`;
}

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
            accion: { type: 'string', enum: ['catalogo', 'disponibilidad', 'proximos_horarios', 'crear', 'citas_cliente', 'reprogramar', 'cancelar'] }, servicio_id: { type: 'integer' }, agenda_id: { type: 'integer' }, cita_id: { type: 'integer' }, fecha: { type: 'string' }, fecha_desde: { type: 'string' }, dias: { type: 'integer' }, franja: { type: 'string', enum: ['manana','tarde'] }, inicio: { type: 'string' }, nombre_cliente: { type: 'string' }, telefono: { type: 'string' }, email: { type: 'string' }, motivo: { type: 'string' }, confirmada: { type: 'boolean' }
          }}
        }
      }] : [];
      async function agendaCall(args) {
        const base = client.client_url.replace(/\/+$/, '');
        const map = { catalogo: 'catalogo', disponibilidad: 'disponibilidad', proximos_horarios: 'proximos_horarios', crear: 'crear', citas_cliente: 'citas_cliente', reprogramar: 'reprogramar', cancelar: 'cancelar' };
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
          messages: [{ role: 'system', content: agendaInstructions() }, ...messages],
          tools: agendaTools.length ? agendaTools : undefined,
          tool_choice: agendaTools.length ? 'auto' : undefined,
          max_tokens: 1024,
          temperature: 0.45
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

      let toolCalls = data?.choices?.[0]?.message?.tool_calls || [];
      let toolRound = 0;
      const toolMessages = [{ role: 'system', content: agendaInstructions() }, ...messages];
      while (toolCalls.length && toolRound < 4) {
        toolMessages.push(data.choices[0].message);
        for (const call of toolCalls) {
          let args = {}; try { args = JSON.parse(call.function.arguments || '{}'); } catch { args = {}; }
          toolMessages.push({ role: 'tool', tool_call_id: call.id, content: JSON.stringify(await agendaCall(args)) });
        }
        const followup = await fetch(OPENAI_CHAT_URL, { method: 'POST', headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: model || 'gpt-4o-mini', messages: toolMessages, tools: agendaTools.length ? agendaTools : undefined, tool_choice: agendaTools.length ? 'auto' : undefined, max_tokens: 1024, temperature: 0.25 }) });
        if (!followup.ok) break;
        data = await followup.json();
        toolCalls = data?.choices?.[0]?.message?.tool_calls || [];
        toolRound++;
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
