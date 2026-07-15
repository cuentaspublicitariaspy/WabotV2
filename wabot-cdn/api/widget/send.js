const { getClient, isAuthorizedDomain } = require('../_lib/kv');
const { confirmExactProposal } = require('../_lib/agenda-confirmation');

const OPENAI_API_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_MODEL = 'gpt-4o-mini';

function asuncionDate() {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
  const value = Object.fromEntries(parts.filter(p => p.type !== 'literal').map(p => [p.type, p.value]));
  return `${value.year}-${value.month}-${value.day}`;
}

function agendaInstructions() {
  return `AGENDA CONVERSACIONAL: hoy es ${asuncionDate()} en la zona horaria America/Asuncion. Convertí referencias naturales: “mañana”, “pasado mañana”, “el viernes” y “por la mañana/tarde” a fechas y preferencias reales; nunca le exijas una fecha exacta a quien ya dijo “mañana”, ni inventes una fecha. Si una persona pide la próxima disponibilidad, el próximo horario, “decime vos” o una alternativa sin indicar fecha, consultá catálogo y luego llamá proximos_horarios desde hoy. Si pide “la más próxima” o “lo más temprano posible en la mañana”, ofrecé directamente el primer horario válido; solo si necesita una alternativa, mostrale como máximo dos. Si una persona pide una agenda por nombre, usá el catálogo para identificarla. Si solo existe un servicio o una agenda activos, la herramienta los resuelve sin pedirlos. Si el horario pedido no existe, llamá próximos_horarios y ofrecé alternativas concretas de los siguientes días, respetando mañana/tarde si la persona lo indicó. Si ya inició una reserva, eligió una fecha y hora concreta y luego entrega los datos solicitados, creá la cita sin pedir una confirmación adicional. Pedí solo el dato que falta para continuar; hacelo con calidez, explicando que sirve para registrar la cita y enviarle el recordatorio. No hagas un cuestionario ni uses frases robóticas como “parece que necesito”. Para cambiar o cancelar una cita, buscá primero las citas activas de esa persona, aclarando cuál si hay más de una. La disponibilidad, los buffers y las colisiones siempre los decide la herramienta; vos solo conversás. Nunca uses la expresión “parece que” ni variantes. PROHIBIDO ofrecer, confirmar o insinuar disponibilidad de una hora que no aparezca en el resultado de agenda de esta misma respuesta. CANAL ACTUAL: Chatbot web. Este canal no trae un teléfono validado: solicitalo solo cuando sea indispensable para cerrar una reserva.`;
}

async function semanticConfirmationIntent(openaiKey, context, answer) {
  if (!answer) return { decision: '', nombreCliente: '' };
  try {
    const recent = (Array.isArray(context) ? context : []).slice(-6).map(item => ({
      role: item?.role || '',
      content: typeof item?.content === 'string' ? item.content : ''
    }));
    const response = await fetch(OPENAI_API_URL, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model: OPENAI_MODEL,
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
      content: mandatoryRules + '\n\n' + agendaInstructions() + (systemPrompt ? '\n\nCONTEXTO COMERCIAL DEL CLIENTE:\n' + systemPrompt : '')
    }];
    messages.push(...parsedHistory);
    messages.push({ role: 'user', content: message });

    const agendaTools = client.client_url ? [{
      type: 'function', function: {
        name: 'agenda', description: 'Consulta y ejecuta acciones determinísticas de agenda. Usala para citas; jamás inventes disponibilidad.',
        parameters: {
          type: 'object', additionalProperties: false, required: ['accion'],
          properties: {
            accion: { type: 'string', enum: ['catalogo', 'disponibilidad', 'proximos_horarios', 'crear', 'citas_cliente', 'reprogramar', 'cancelar'] },
            servicio_id: { type: 'integer' }, agenda_id: { type: 'integer', description: 'ID de la agenda única que se reserva; no es un profesional genérico.' },
            fecha: { type: 'string', description: 'Fecha ISO YYYY-MM-DD' }, fecha_desde: { type: 'string', description: 'Fecha ISO inicial para buscar alternativas' }, dias: { type: 'integer' }, franja: { type: 'string', enum: ['manana','tarde'] }, inicio: { type: 'string', description: 'Fecha y hora ISO local YYYY-MM-DD HH:mm' },
            cita_id: { type: 'integer', description: 'ID de una cita existente, requerido para reprogramar.' }, nombre_cliente: { type: 'string' }, telefono: { type: 'string' }, email: { type: 'string' }, motivo: { type: 'string' },
            confirmada: { type: 'boolean', description: 'Solo true si el cliente confirmó explícitamente el horario exacto.' }
          }
        }
      }
    }] : [];

    async function agendaCall(args) {
      const base = client.client_url.replace(/\/+$/, '');
      const actionMap = { catalogo: 'catalogo', disponibilidad: 'disponibilidad', proximos_horarios: 'proximos_horarios', crear: 'crear', citas_cliente: 'citas_cliente', reprogramar: 'reprogramar', cancelar: 'cancelar' };
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

    const semantic = await semanticConfirmationIntent(openaiKey, parsedHistory, message);
    const confirmed = await confirmExactProposal({ history: parsedHistory, message, agendaCall, channel: 'chatbot', nombre_cliente: semantic.nombreCliente, semanticIntent: semantic.decision });
    if (confirmed?.handled) {
      res.json({ success: confirmed.success, message: { role: 'assistant', content: confirmed.reply } });
      return;
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
        temperature: 0.45
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
    let toolCalls = openaiData.choices?.[0]?.message?.tool_calls || [];
    let toolRound = 0;
    while (toolCalls.length && toolRound < 4) {
      messages.push(openaiData.choices[0].message);
      for (const call of toolCalls) {
        let args = {};
        try { args = JSON.parse(call.function.arguments || '{}'); } catch { args = {}; }
        const result = await agendaCall(args);
        messages.push({ role: 'tool', tool_call_id: call.id, content: JSON.stringify(result) });
      }
      const finalRes = await fetch(OPENAI_API_URL, {
        method: 'POST', headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: OPENAI_MODEL, messages, tools: agendaTools.length ? agendaTools : undefined, tool_choice: agendaTools.length ? 'auto' : undefined, max_tokens: 1024, temperature: 0.25 })
      });
      if (!finalRes.ok) break;
      openaiData = await finalRes.json();
      toolCalls = openaiData.choices?.[0]?.message?.tool_calls || [];
      toolRound++;
    }
    let reply = openaiData.choices?.[0]?.message?.content || '';
    if (!reply.trim()) {
      const fallback = await fetch(OPENAI_API_URL, {
        method: 'POST', headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' },
        body: JSON.stringify({ model: OPENAI_MODEL, messages: [...messages, { role: 'system', content: 'Respondé ahora en lenguaje natural usando únicamente los resultados de agenda ya obtenidos. No llames herramientas.' }], tool_choice: 'none', max_tokens: 512, temperature: 0.25 })
      });
      if (fallback.ok) {
        const fallbackData = await fallback.json();
        reply = fallbackData.choices?.[0]?.message?.content || '';
      }
    }
    if (!reply.trim()) reply = 'No pude consultar los próximos horarios en este momento. Probá de nuevo en unos segundos.';
    // Cinturón y tiradores: aunque una fuente cargada por el cliente contenga
    // una frase vieja o contradictoria, esa negativa no sale al visitante.
    const forbiddenRefusal = /(?:no\s+puedo|no\s+podemos)\s+(?:guardar|recibir|almacenar)[\s\S]{0,100}(?:dato|informaci[oó]n|contacto|n[uú]mero)/i;
    if (forbiddenRefusal.test(reply)) {
      console.warn('[widget] blocked contradictory personal-data refusal');
      reply = 'Gracias por compartirlo. ¿En qué más puedo ayudarte?';
    }

    res.json({
      success: true,
      message: { role: 'assistant', content: polishResponse(reply) }
    });
  } catch (err) {
    if (err.name === 'AbortError') {
      res.status(504).json({ success: false, error: 'Tiempo de espera agotado' });
      return;
    }
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
