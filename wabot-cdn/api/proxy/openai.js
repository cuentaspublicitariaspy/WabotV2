const { getClient, getAllClients } = require('../_lib/kv');
const { confirmExactProposal } = require('../_lib/agenda-confirmation');

const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_TRANSCRIBE_URL = 'https://api.openai.com/v1/audio/transcriptions';

function asuncionDate() {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
  const value = Object.fromEntries(parts.filter(p => p.type !== 'literal').map(p => [p.type, p.value]));
  return `${value.year}-${value.month}-${value.day}`;
}
function agendaInstructions() {
  return `AGENDA CONVERSACIONAL: hoy es ${asuncionDate()} en America/Asuncion. Interpretá “mañana”, días de la semana y mañana/tarde sin pedir una fecha exacta cuando ya existe una referencia natural. Si el cliente pide la próxima disponibilidad, el próximo horario, “decime vos” o una alternativa sin fecha, consultá catálogo y luego usá próximos_horarios desde hoy. Consultá siempre disponibilidad real antes de ofrecer horarios. Si no hay lugar, usá próximos_horarios y ofrecé alternativas de los días siguientes. Si la persona pide “la más próxima” o “lo más temprano posible en la mañana”, consultá y ofrecé directamente el primer horario válido; solo si necesita una alternativa, mostrale como máximo dos. Si ya inició una reserva, eligió una fecha y hora concreta y después entrega los datos solicitados, creá la cita sin pedir otra confirmación. Al pedir datos, hacelo con calidez y explicá que se usan para registrar la cita y enviarle el recordatorio. Pedí solo el dato faltante, sin tono de formulario. Nunca uses la expresión “parece que” ni variantes. Para reprogramar: buscá primero las citas activas, identificá su cita_id, consultá disponibilidad enviando ese mismo cita_id (para no bloquear la propia cita) y ejecutá reprogramar; nunca crees una segunda cita. Para cancelar, buscá primero las citas activas y aclarar cuál si hay varias. Nunca inventes fechas, horarios, disponibilidad ni ignores buffers. PROHIBIDO afirmar “tengo”, “hay” o “puedo ofrecer” una hora si no aparece en el resultado de agenda de esta misma respuesta. Si una hora fue rechazada por agenda, no la vuelvas a proponer ni confirmar. CANAL: esta conversación llega por WhatsApp; el número de contacto ya fue autenticado por Meta y se recibe transitoriamente en el pedido. Nunca lo solicites de nuevo; solo pedí el nombre si todavía falta para la reserva.`;
}

async function semanticConfirmationIntent(openaiKey, context, answer) {
  if (!answer) return { decision: '', nombreCliente: '' };
  try {
    const recent = (Array.isArray(context) ? context : []).slice(-6).map(item => ({
      role: item?.role || '',
      content: typeof item?.content === 'string' ? item.content : ''
    }));
    const response = await fetch(OPENAI_CHAT_URL, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: 'gpt-4o-mini',
        response_format: { type: 'json_object' },
        temperature: 0,
        max_tokens: 100,
        messages: [
          { role: 'system', content: 'Comprendé semánticamente una reserva usando todo el tramo reciente de conversación. Respondé SOLO JSON válido: {"decision":"confirmar"|"completar_reserva"|"rechazar_o_cambiar"|"indeterminado","nombre_cliente":""}. confirmar: acepta la última propuesta concreta. completar_reserva: la persona ya quiso agendar, eligió una fecha/hora concreta y ahora entrega datos solicitados; eso autoriza crear la cita sin pedir otra confirmación. rechazar_o_cambiar: rechaza, corrige o cambia la propuesta. indeterminado: otro caso. No uses frases clave ni exijas la palabra “sí”; interpretá la intención humana. nombre_cliente solo si fue declarado explícitamente por la persona en la respuesta actual.' },
          { role: 'user', content: JSON.stringify({ contexto_reciente: recent, respuesta_actual: answer }) }
        ]
      })
    });
    const data = await response.json();
    const parsed = JSON.parse(data?.choices?.[0]?.message?.content || '{}');
    const decision = ['confirmar', 'completar_reserva', 'rechazar_o_cambiar', 'indeterminado'].includes(parsed?.decision) ? parsed.decision : '';
    return { decision, nombreCliente: String(parsed?.nombre_cliente || '').trim() };
  } catch { return { decision: '', nombreCliente: '' }; }
}

function polishResponse(content) {
  return String(content || '').replace(/\bparece\s+que\s+/gi, '').replace(/\bpareciera\s+que\s+/gi, '');
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
    const { action, license_key, api_key, messages, audio_base64, model, telefono, nombre_cliente } = req.body || {};

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

      const latestUser = [...messages].reverse().find(item => item?.role === 'user');
      const semantic = await semanticConfirmationIntent(openaiKey, messages.slice(0, -1), latestUser?.content || '');
      const confirmed = await confirmExactProposal({ history: messages.slice(0, -1), message: latestUser?.content || '', agendaCall, channel: 'whatsapp', telefono, nombre_cliente: semantic.nombreCliente || nombre_cliente, semanticIntent: semantic.decision });
      if (confirmed?.handled) {
        res.json({ success: confirmed.success, content: confirmed.reply });
        return;
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
      let content = data?.choices?.[0]?.message?.content?.trim();
      if (!content) {
        const fallback = await fetch(OPENAI_CHAT_URL, { method: 'POST', headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' }, body: JSON.stringify({ model: model || 'gpt-4o-mini', messages: [...toolMessages, { role: 'system', content: 'Respondé ahora usando los resultados de agenda ya obtenidos. No llames herramientas.' }], tool_choice: 'none', max_tokens: 512, temperature: 0.25 }) });
        if (fallback.ok) { const fallbackData = await fallback.json(); content = fallbackData?.choices?.[0]?.message?.content?.trim(); }
      }
      if (!content) {
        res.status(502).json({ success: false, error: 'No se pudo consultar los próximos horarios. Probá de nuevo en unos segundos.' }); return;
      }

      // Contrato estable para WC. No se filtra la respuesta completa de OpenAI.
      res.json({ success: true, content: polishResponse(content) });
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
