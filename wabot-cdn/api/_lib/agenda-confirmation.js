const MONTHS = { enero: 1, febrero: 2, marzo: 3, abril: 4, mayo: 5, junio: 6, julio: 7, agosto: 8, septiembre: 9, setiembre: 9, octubre: 10, noviembre: 11, diciembre: 12 };

function text(value) { return typeof value === 'string' ? value : (value?.text || ''); }
function normalize(value) { return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }
function isAffirmative(value) { return /^(si|sí|confirmo|confirmamos|correcto|dale|de acuerdo|ok|okay)\b/i.test(String(value || '').trim()); }
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
 * anterior pidió confirmar una fecha/hora exactas y el visitante responde sí.
 */
async function confirmExactProposal({ history, message, agendaCall, channel }) {
  if (!isAffirmative(message)) return null;
  const entries = [...(Array.isArray(history) ? history : []), { role: 'user', content: message }];
  const lastAssistant = [...entries].reverse().find(item => item?.role === 'assistant');
  const proposalText = text(lastAssistant?.content);
  if (!/(confirm|reserv|agend)/i.test(proposalText)) return null;
  const inicio = parseProposal(proposalText);
  const telefono = phoneFrom(entries);
  if (!inicio || !telefono) return null;

  const catalog = await agendaCall({ accion: 'catalogo' });
  if (!catalog?.success) return null;
  const service = selected(catalog.servicios, proposalText);
  const agenda = selected(catalog.agendas, proposalText);
  if (!service || !agenda) return null;

  const created = await agendaCall({
    accion: 'crear', agenda_id: Number(agenda.id), servicio_id: Number(service.id), inicio,
    telefono, confirmada: true, canal
  });
  if (!created?.success) return { handled: true, success: false, reply: created?.error || 'No pude confirmar ese horario porque acaba de dejar de estar disponible.' };
  return { handled: true, success: true, reply: `Listo. Tu ${service.nombre} con ${agenda.nombre} quedó agendada para el ${inicio.slice(8, 10)}/${inicio.slice(5, 7)}/${inicio.slice(0, 4)} a las ${inicio.slice(11, 16)}. Te contactaremos al ${telefono}.` };
}

module.exports = { confirmExactProposal };
