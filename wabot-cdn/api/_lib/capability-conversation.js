const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';

function disabledAgendaInstructions(channel = 'chatbot') {
  const channelLabel = channel === 'whatsapp' ? 'WhatsApp' : 'Chatbot web';
  return `CAPACIDAD AGENDA DESHABILITADA: en esta cuenta no está habilitada la gestión de citas. CANAL ACTUAL: ${channelLabel}. Esta restricción se aplica únicamente a crear, consultar disponibilidad, reprogramar o cancelar citas. Nunca dejes que esta restricción anule la conversación ni se arrastre a una intención nueva. Si la persona intenta una de esas acciones, explicá con amabilidad que la gestión de citas no está disponible en este momento y ofrecé ayudar con otra consulta. No pidas nombre, teléfono, correo ni otros datos para coordinar una cita. No simules reservas ni afirmes que una cita quedó registrada. Preguntas sobre horarios de atención, servicios o el negocio se responden normalmente. Si la persona quiere dejar un mensaje, comparte voluntariamente sus datos, solicita información, corrige lo que entendiste o cambia de tema, comprendé y atendé esa intención con naturalidad: sus datos pueden quedar registrados en esta conversación y su ficha de prospecto. Podés confirmar que el mensaje quedó registrado para que el equipo lo vea, pero nunca garantizar que una persona concreta responderá o se comunicará. No menciones licencias, WS, capacidades ni detalles técnicos.`;
}

function agendaUnavailableReply() {
  return 'En este momento no está disponible la gestión de citas por este canal. Puedo ayudarte con información sobre el negocio o con cualquier otra consulta.';
}

function shouldInspectDisabledAgenda(history = [], userMessage = '', draft = '') {
  // El historial sirve al clasificador semántico para resolver referencias,
  // pero jamás debe activar por sí solo el bloqueo de una intención nueva.
  const currentTurn = `${String(userMessage || '')} ${String(draft || '')}`;
  return /\b(?:agend\w*|reserv\w*|cita\w*|turno\w*|reuni[oó]n\w*|disponibilidad\w*|reprogram\w*|cancel\w*|coordinar\w*|horario\w*)\b/i.test(currentTurn);
}

function looksLikeAgendaBlock(reply = '') {
  const value = String(reply || '');
  return /(?:gesti[oó]n|manejo)\s+de\s+citas[\s\S]{0,90}no\s+est[aá]\s+disponible|no\s+est[aá]\s+disponible[\s\S]{0,90}(?:cita|agenda|reserva|turno)/i.test(value);
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
            content: `Protegé una capacidad deshabilitada sin destruir la conversación. Decidí exclusivamente la intención operativa del MENSAJE ACTUAL; usá el historial solo para resolver referencias y correcciones. Que antes se haya hablado de una cita NO convierte los turnos siguientes en solicitudes de cita. Respondé SOLO JSON válido: {"solicita_gestion_de_cita":true|false,"bloqueo_indebido":true|false,"respuesta_segura":"","respuesta_corregida":""}. solicita_gestion_de_cita es true solo si el mensaje actual intenta crear, consultar disponibilidad, reprogramar o cancelar una cita/reunión/reserva. Es false si quiere dejar un mensaje, comparte datos voluntariamente, pide contacto o información, corrige una incomprensión, pregunta por horarios del negocio o cambia de tema. bloqueo_indebido es true cuando el borrador habla de Agenda no disponible pese a que la intención actual es otra o ignora lo que la persona acaba de decir. Si solicita_gestion_de_cita=true, respuesta_segura comunica con naturalidad que la gestión de citas no está disponible y ofrece otra ayuda, sin pedir datos ni simular acciones. Si bloqueo_indebido=true, respuesta_corregida responde humana y directamente al mensaje actual: puede confirmar que un mensaje y datos voluntarios quedan registrados en la conversación para que el equipo los vea, pero no debe garantizar que una persona concreta responderá. Canal: ${channel === 'whatsapp' ? 'WhatsApp' : 'Chatbot web'}.`
          },
          { role: 'user', content: JSON.stringify({ conversacion: recent, borrador: draft }) }
        ]
      })
    });
    if (!response.ok) throw new Error(`CAPABILITY_GUARD_HTTP_${response.status}`);
    const data = await response.json();
    const parsed = JSON.parse(data?.choices?.[0]?.message?.content || '{}');
    if (parsed.solicita_gestion_de_cita) {
      const safeReply = String(parsed.respuesta_segura || '').trim();
      return safeReply && !unsafeDisabledAgendaReply(safeReply) ? safeReply : agendaUnavailableReply();
    }
    if (parsed.bloqueo_indebido) {
      const corrected = String(parsed.respuesta_corregida || '').trim();
      if (corrected && !looksLikeAgendaBlock(corrected)) return corrected;
    }
    return draft;
  } catch (error) {
    console.error('[capability-guard] disabled Agenda classification failed', error?.message || error);
    if (looksLikeAgendaBlock(draft) && !/\b(?:agend\w*|reserv\w*|cita\w*|turno\w*|reprogram\w*|cancel\w*)\b/i.test(String(userMessage || ''))) {
      return 'Entendí que no estás pidiendo una cita. Disculpá la confusión: contame qué necesitás y te respondo sobre eso.';
    }
    return unsafeDisabledAgendaReply(draft) ? agendaUnavailableReply() : draft;
  }
}

module.exports = {
  disabledAgendaInstructions,
  agendaUnavailableReply,
  shouldInspectDisabledAgenda,
  looksLikeAgendaBlock,
  unsafeDisabledAgendaReply,
  enforceDisabledAgendaResponse
};
