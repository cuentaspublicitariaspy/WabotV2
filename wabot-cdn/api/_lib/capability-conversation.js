const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';

function disabledAgendaInstructions(channel = 'chatbot') {
  const channelLabel = channel === 'whatsapp' ? 'WhatsApp' : 'Chatbot web';
  return `CAPACIDAD AGENDA DESHABILITADA: en esta cuenta no está habilitada la gestión de citas. CANAL ACTUAL: ${channelLabel}. Si la persona intenta agendar, consultar disponibilidad, reprogramar o cancelar una cita, explicá con amabilidad que la gestión de citas no está disponible en este momento y ofrecé ayudar con otra consulta. No pidas nombre, teléfono, correo ni otros datos para coordinar una cita. No prometas que alguien se comunicará, que pasarás sus datos, que coordinarán después ni que la solicitud quedó registrada. No simules reservas, derivaciones, seguimientos o acciones que el sistema no puede ejecutar. No menciones licencias, WS, capacidades ni detalles técnicos. Una pregunta meramente informativa sobre horarios de atención, servicios o el negocio no es una solicitud de cita y puede responderse normalmente con la información disponible.`;
}

function agendaUnavailableReply() {
  return 'En este momento no está disponible la gestión de citas por este canal. Puedo ayudarte con información sobre el negocio o con cualquier otra consulta.';
}

function shouldInspectDisabledAgenda(history = [], userMessage = '', draft = '') {
  const recent = [...history.slice(-8), { role: 'user', content: userMessage }, { role: 'assistant', content: draft }]
    .map(item => String(item?.content || ''))
    .join(' ');
  return /\b(?:agend\w*|reserv\w*|cita\w*|turno\w*|reuni[oó]n\w*|disponibilidad\w*|reprogram\w*|cancel\w*|coordinar\w*|horario\w*)\b/i.test(recent);
}

function unsafeDisabledAgendaReply(reply = '') {
  const value = String(reply || '');
  const requestsContact = /(?:necesit\w*|compart\w*|indic\w*|proporcion\w*|decir\w*|decime|pasar\w*|pasame|dame|enviar\w*)[\s\S]{0,90}(?:nombre|tel[eé]fono|n[uú]mero|correo|contacto|datos)/i.test(value);
  const promisesAction = /(?:voy|vamos|podemos|puedo|queda|qued[oó]|ser[aá]|te van)\s+(?:a\s+)?(?:pasar|enviar|derivar|comunicar|contactar|coordinar|agendar|reservar|registrar|llamar)/i.test(value);
  const falseCompletion = /(?:cita|reuni[oó]n|turno|reserva)[\s\S]{0,60}(?:confirmad|agendad|reservad|registrad|coordinad)/i.test(value);
  return requestsContact || promisesAction || falseCompletion;
}

async function enforceDisabledAgendaResponse({
  openaiKey,
  model = 'gpt-4o-mini',
  history = [],
  userMessage = '',
  draft = '',
  channel = 'chatbot',
  fetchImpl = fetch
}) {
  if (!shouldInspectDisabledAgenda(history, userMessage, draft)) return draft;

  const recent = [...history.slice(-10), { role: 'user', content: userMessage }]
    .filter(item => item && typeof item.content === 'string')
    .map(item => ({ role: item.role === 'assistant' ? 'assistant' : 'user', content: item.content }));

  try {
    const response = await fetchImpl(OPENAI_CHAT_URL, {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${openaiKey}`, 'Content-Type': 'application/json' },
      body: JSON.stringify({
        model,
        response_format: { type: 'json_object' },
        temperature: 0,
        max_tokens: 180,
        messages: [
          {
            role: 'system',
            content: `Clasificá la intención conversacional usando todo el tramo reciente. Respondé SOLO JSON válido: {"solicita_gestion_de_cita":true|false,"respuesta_segura":""}. Es true si la persona intenta crear, consultar disponibilidad, reprogramar o cancelar una cita/reunión/reserva, incluso mediante referencias contextuales. Es false para preguntas informativas sobre horarios de atención, servicios o el negocio. Cuando sea true, respuesta_segura debe comunicar en español natural y amable que la gestión de citas no está disponible en este momento y ofrecer ayuda con otra consulta. Nunca debe pedir datos personales, prometer contacto o derivación, ni afirmar que se registró o coordinó algo. Canal: ${channel === 'whatsapp' ? 'WhatsApp' : 'Chatbot web'}.`
          },
          { role: 'user', content: JSON.stringify({ conversacion: recent, borrador: draft }) }
        ]
      })
    });
    if (!response.ok) throw new Error(`CAPABILITY_GUARD_HTTP_${response.status}`);
    const data = await response.json();
    const parsed = JSON.parse(data?.choices?.[0]?.message?.content || '{}');
    if (!parsed.solicita_gestion_de_cita) return draft;
    const safeReply = String(parsed.respuesta_segura || '').trim();
    return safeReply && !unsafeDisabledAgendaReply(safeReply) ? safeReply : agendaUnavailableReply();
  } catch (error) {
    console.error('[capability-guard] disabled Agenda classification failed', error?.message || error);
    return unsafeDisabledAgendaReply(draft) ? agendaUnavailableReply() : draft;
  }
}

module.exports = {
  disabledAgendaInstructions,
  agendaUnavailableReply,
  shouldInspectDisabledAgenda,
  unsafeDisabledAgendaReply,
  enforceDisabledAgendaResponse
};
