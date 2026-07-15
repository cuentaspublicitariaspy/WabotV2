const MONTHS = { enero: 1, febrero: 2, marzo: 3, abril: 4, mayo: 5, junio: 6, julio: 7, agosto: 8, septiembre: 9, setiembre: 9, octubre: 10, noviembre: 11, diciembre: 12 };

function text(value) { return typeof value === 'string' ? value : (value?.text || ''); }
function normalize(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
function isConfirmationIntent(value, proposalText = '') {
  const raw = String(value || '').trim();
  const normalized = normalize(raw);
  // Una negativa o un cambio explícito nunca puede crear una reserva.
  if (/\b(no|cancel|cambi|otro horario|otra hora|mejor)\b/.test(normalized)) return false;
  if (/^(si|confirmo|confirmamos|correcto|dale|de acuerdo|ok|okay|va|vamos)\b/.test(normalized)) return true;
  if (/\b(esta bien|perfecto|adelante|proced[ea]|reservalo|agendalo|agendame|reservame|anotame|hacelo|hazlo|lo hago|ya lo hice|ya te dije|me sirve|me queda bien|quiero ese|quiero esa|dejalo asi|obvio|elegi|elige|favorito)\b/.test(normalized)) return true;

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
function asuncionToday() {
  const parts = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Asuncion', year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(new Date());
  const values = Object.fromEntries(parts.filter(part => part.type !== 'literal').map(part => [part.type, Number(part.value)]));
  return { year: values.year, month: values.month, day: values.day };
}
function parseProposal(value) {
  const pattern = /(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\s+de\s+(\d{4})[^\d]{0,35}(\d{1,2})(?:[:.](\d{2}))?/ig;
  let match, last = null;
  while ((match = pattern.exec(value))) {
    const month = MONTHS[normalize(match[2])];
    if (month) last = `${match[3]}-${String(month).padStart(2, '0')}-${String(match[1]).padStart(2, '0')} ${String(match[4]).padStart(2, '0')}:${String(match[5] || '00').padStart(2, '0')}`;
  }
  if (last) return last;

  // También se admite “20 de julio a las 10:30”. Se toma el año actual de
  // America/Asuncion, o el siguiente si esa fecha ya pasó.
  const monthPattern = /(\d{1,2})\s+de\s+(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)\b(?!\s+de\s+\d{4})[^\d]{0,35}(\d{1,2})(?:[:.](\d{2}))?/ig;
  let monthMatch, monthLast = null;
  while ((monthMatch = monthPattern.exec(value))) monthLast = monthMatch;
  if (monthLast) {
    const today = asuncionToday();
    const day = Number(monthLast[1]);
    const month = MONTHS[normalize(monthLast[2])];
    const year = month < today.month || (month === today.month && day < today.day) ? today.year + 1 : today.year;
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')} ${String(monthLast[3]).padStart(2, '0')}:${String(monthLast[4] || '00').padStart(2, '0')}`;
  }

  // También se admite “lunes 20 a las 10:30”. El día del mes se interpreta
  // en America/Asuncion; si ya pasó, corresponde al mes siguiente.
  const shortPattern = /\b(?:lunes|martes|miercoles|miércoles|jueves|viernes|sabado|sábado|domingo)\s+(\d{1,2})\b[^\d]{0,35}(\d{1,2})(?:[:.](\d{2}))?/ig;
  let shortMatch, shortLast = null;
  while ((shortMatch = shortPattern.exec(value))) shortLast = shortMatch;
  if (!shortLast) return null;
  const today = asuncionToday();
  let year = today.year;
  let month = today.month;
  const day = Number(shortLast[1]);
  if (day < today.day) {
    month += 1;
    if (month === 13) { month = 1; year += 1; }
  }
  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')} ${String(shortLast[2]).padStart(2, '0')}:${String(shortLast[3] || '00').padStart(2, '0')}`;
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
async function confirmExactProposal({ history, message, agendaCall, channel, telefono = '', nombre_cliente = '', semanticIntent = '' }) {
  const entries = [...(Array.isArray(history) ? history : []), { role: 'user', content: message }];
  const confirmationPrompt = [...entries].reverse().find(item => {
    const candidate = text(item?.content);
    return item?.role === 'assistant' && /(confirm|reserv|agend)/i.test(candidate) && !!parseProposal(candidate);
  });
  const proposalText = text(confirmationPrompt?.content);
  // La clasificación semántica proviene de la IA y entiende el sentido de la respuesta.
  // Los patrones locales solo son una red de seguridad ante un fallo temporal.
  if (!proposalText || semanticIntent === 'rechazar_o_cambiar') return null;
  const semanticAllowsBooking = ['confirmar', 'completar_reserva'].includes(semanticIntent);
  if (!semanticAllowsBooking && !isConfirmationIntent(message, proposalText)) return null;
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
    telefono: phone, nombre_cliente, confirmada: true, canal: channel
  });
  if (!created?.success) return { handled: true, success: false, reply: created?.error || 'No pude confirmar ese horario porque acaba de dejar de estar disponible.' };
  return { handled: true, success: true, reply: `Listo. Tu ${service.nombre} con ${agenda.nombre} quedó agendada para el ${inicio.slice(8, 10)}/${inicio.slice(5, 7)}/${inicio.slice(0, 4)} a las ${inicio.slice(11, 16)}. Te contactaremos al ${phone}.` };
}

module.exports = { confirmExactProposal };
