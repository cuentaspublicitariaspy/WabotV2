function clean(value) {
  return String(value || '').trim();
}

function itemName(items, id, fallback) {
  const found = (items || []).find(item => Number(item?.id) === Number(id));
  return clean(found?.nombre) || fallback;
}

/**
 * Respuesta de seguridad cuando el modelo agota sus llamadas de agenda.
 * Nunca inventa disponibilidad: solo presenta datos que WC ya devolvió.
 */
function agendaFallback({ action = '', result = null, catalog = null } = {}) {
  if (!result) return 'No pude completar la consulta de agenda en este momento. Intentá nuevamente en unos segundos.';

  if (!result.success) {
    return clean(result.error) || 'No pude completar la consulta de agenda en este momento. Intentá nuevamente en unos segundos.';
  }

  if (action === 'proximos_horarios') {
    const options = Array.isArray(result.opciones) ? result.opciones : [];
    if (!options.length) return 'No encontré horarios disponibles dentro del período consultado. Si querés, decime otra fecha o franja horaria y reviso nuevas opciones.';

    const agenda = itemName(catalog?.agendas, result.agenda_id, 'esa agenda');
    const service = itemName(catalog?.servicios, result.servicio_id, 'el servicio solicitado');
    const first = options[0] || {};
    const times = (Array.isArray(first.horarios) ? first.horarios : []).slice(0, 2);
    if (!times.length) return 'No encontré horarios disponibles dentro del período consultado. Si querés, decime otra fecha y reviso nuevas opciones.';
    const formattedDate = clean(first.fecha).split('-').reverse().join('/');
    const choices = times.length === 1 ? times[0] : `${times[0]} o ${times[1]}`;
    return `La próxima disponibilidad para ${service} con ${agenda} es el ${formattedDate} a las ${choices}. ¿Cuál horario te queda mejor?`;
  }

  if (action === 'disponibilidad') {
    const slots = Array.isArray(result.slots) ? result.slots.slice(0, 2) : [];
    if (!slots.length) return 'Ese día no tiene horarios disponibles. Si querés, busco la siguiente opción libre.';
    const choices = slots.length === 1 ? slots[0] : `${slots[0]} o ${slots[1]}`;
    return `Tengo disponible ${choices}. ¿Cuál horario te queda mejor?`;
  }

  if (action === 'catalogo') {
    const agendas = (result.agendas || []).filter(item => Number(item?.activo) !== 0);
    if (agendas.length > 1) {
      const names = agendas.slice(0, 4).map(item => clean(item.nombre)).filter(Boolean).join(', ');
      return `Encontré estas agendas disponibles: ${names}. ¿Con cuál querés agendarte?`;
    }
    if (agendas.length === 1) return `Encontré la agenda de ${clean(agendas[0].nombre)}. Decime qué fecha o franja horaria preferís y reviso la disponibilidad.`;
  }

  return 'La consulta de agenda se completó. Decime cómo querés continuar y te ayudo.';
}

module.exports = { agendaFallback };
