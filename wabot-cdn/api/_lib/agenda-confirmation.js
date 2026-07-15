const MONTHS = { enero: 1, febrero: 2, marzo: 3, abril: 4, mayo: 5, junio: 6, julio: 7, agosto: 8, septiembre: 9, setiembre: 9, octubre: 10, noviembre: 11, diciembre: 12 };

function text(value) { return typeof value === 'string' ? value : (value?.text || ''); }
function normalize(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
function isConfirmationIntent(value, proposalText = '') {
  const raw = String(value || '').trim();
  const normalized = normalize(raw);
  // Una negativa o un cambio explícito nunca puede crear una reserva.
  if (/\b(no|cancel|cambi|otro horario|otra hora|mejor)\b/.test(normalized)) return false;
  if (/^(si|confirmo|confirmamos|correcto|dale|de acuerdo|ok|okay|va|vamos)\b/.test(normalized)) return true;
  if (/\b(esta bien|perfecto|adelante|proced[ea]|reservalo|agendalo|hacelo|hazlo|lo hago|ya lo hice|ya te dije|me sirve|me queda bien|quiero ese|quiero esa|dejalo asi|obvio)\b/.test(normalized)) return true;

  // También cuenta como confirmación que la persona repita la hora exacta de
  // la última propuesta: “10:30 como te dije” es una aceptación válida.
  const start = parseProposal(proposalText);
  if (start && /^\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2})$/.test(start)) {
    const time = start.slice(11, 16);
    const [hour, minute] = time.split(':');
    const timePattern = new RegExp(`\\b0?${Number(hour)}(?:[:.]${minute})?\\b`);
    if (timePattern.test(normalized)) return true;
  }
  return false;
}
function parseProposal(value) {
  const pattern = /(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+de\s+(\d{4})[^\d]{0,35}(\d{1,2})(?:[:.](\d{2}))?/ig;
  let match, last = null;
  while ((match = pattern.exec(value))) {
    const month = MONTHS[normalize(match[2])];
    if (month) last = `${match[3]}-${String(month).padStart(2, '0')}-${String(match[1]).padStart(2, '0')} ${String(match[4]).padStart(2, '0')}:${String(match[5] || '00').padStart(2, '0')}`;
  }
  return last;
}
function phoneFrom(messages) {
  for (let i = messages.length - 1; i >= 0; i--) {
    if (messages[i]?.role !== 'user') continue;
    const match = text(messages[i].content).match(/(?:\+?595\s*|0)(?:\d[\s-]*){8,10}/);
    if (match) return match[0].replace(/\D/g, '');
  }
  return '';
}
function selected(items, proposalText, field = 'nombre') {
  const active = (items || []).filter(item => Number(item.activo) !== 0);
  if (active.length === 1) return active[0];
  const haystack = normalize(proposalText);
  return active.find(item => normalize(item[field]).length > 2 && haystack.includes(normalize(item[field]))) || null;
}

/**
 * La propuesta visible no conserva sus IDs entre mensajes del Chatbot. Esta
 * confirmación es deliberadamente acotada: solo reserva cuando el asistente
 * anterior pidió confirmar una fecha/hora exactas y el visitante acepta esa
 * propuesta de forma natural.
 */
async function confirmExactProposal({ history, message, agendaCall, channel, telefono = '', nombre_cliente = '' }) {
  const entries = [...(Array.isArray(history) ? history : []), { role: 'user', content: message }];
  const confirmationPrompt = [...entries].reverse().find(item => {
    const candidate = text(item?.content);
    return item?.role === 'assistant' && /(confirm|reserv|agend)/i.test(candidate) && !!parseProposal(candidate);
  });
  const proposalText = text(confirmationPrompt?.content);
  if (!isConfirmationIntent(message, proposalText)) return null;
  if (!proposalText) return null;
  const inicio = parseProposal(proposalText);
  const phone = String(telefono || phoneFrom(entries)).replace(/\D/g, '');
  if (!inicio || !phone) return null;

  const catalog = await agendaCall({ accion: 'catalogo' });
  if (!catalog?.success) return null;
  const service = selected(catalog.servicios, proposalText);
  const agenda = selected(catalog.agendas, proposalText);
  if (!service || !agenda) return null;

  const created = await agendaCall({
    accion: 'crear', agenda_id: Number(agenda.id), servicio_id: Number(service.id), inicio,
    telefono: phone, nombre_cliente, confirmada: true, canal
  });
  if (!created?.success) return { handled: true, success: false, reply: created?.error || 'No pude confirmar ese horario porque acaba de dejar de estar disponible.' };
  return { handled: true, success: true, reply: `Listo. Tu ${service.nombre} con ${agenda.nombre} quedó agendada para el ${inicio.slice(8, 10)}/${inicio.slice(5, 7)}/${inicio.slice(0, 4)} a las ${inicio.slice(11, 16)}. Te contactaremos al ${phone}.` };
}

module.exports = { confirmExactProposal };
