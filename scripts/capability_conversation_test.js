const assert = require('assert');
const {
  disabledAgendaInstructions,
  agendaUnavailableReply,
  shouldInspectDisabledAgenda,
  unsafeDisabledAgendaReply,
  enforceDisabledAgendaResponse
} = require('../wabot-cdn/api/_lib/capability-conversation');

async function run() {
  assert.match(disabledAgendaInstructions('chatbot'), /no está habilitada la gestión de citas/i);
  assert.match(disabledAgendaInstructions('whatsapp'), /CANAL ACTUAL: WhatsApp/);
  assert.strictEqual(shouldInspectDisabledAgenda([], 'Quiero agendarme con Rolando', ''), true);
  assert.strictEqual(shouldInspectDisabledAgenda([], '¿Qué servicios ofrecen?', 'Te cuento.'), false);
  assert.strictEqual(shouldInspectDisabledAgenda([{ role: 'user', content: 'Quiero una cita' }], 'Quiero dejarle un mensaje', 'La gestión de citas no está disponible.'), true);
  assert.strictEqual(unsafeDisabledAgendaReply('Pasame tu nombre y número para coordinar la reunión.'), true);
  assert.strictEqual(unsafeDisabledAgendaReply('Puedo ayudarte con información sobre nuestros servicios.'), false);

  let calls = 0;
  const agendaFetch = async () => {
    calls++;
    return {
      ok: true,
      json: async () => ({ choices: [{ message: { content: JSON.stringify({ solicita_gestion_de_cita: true, bloqueo_indebido: false, respuesta_segura: 'Ahora mismo no está disponible la gestión de citas. Con gusto puedo ayudarte con otra consulta.', respuesta_corregida: '' }) } }] })
    };
  };
  const guarded = await enforceDisabledAgendaResponse({
    openaiKey: 'test',
    history: [],
    userMessage: 'Quiero una cita con Rolando',
    draft: 'Pasame tu teléfono y voy a coordinar la reunión.',
    fetchImpl: agendaFetch
  });
  assert.strictEqual(calls, 1);
  assert.match(guarded, /no está disponible la gestión de citas/i);
  assert.doesNotMatch(guarded, /tel[eé]fono|coordinar|comunicar/i);

  const unsafeClassifierFetch = async () => ({
    ok: true,
    json: async () => ({ choices: [{ message: { content: JSON.stringify({ solicita_gestion_de_cita: true, respuesta_segura: 'Dame tu número y voy a coordinar la cita.' }) } }] })
  });
  const fallback = await enforceDisabledAgendaResponse({
    openaiKey: 'test',
    userMessage: 'Reservame para mañana',
    draft: '¿Cómo te llamás?',
    fetchImpl: unsafeClassifierFetch
  });
  assert.strictEqual(fallback, agendaUnavailableReply());

  calls = 0;
  const untouched = await enforceDisabledAgendaResponse({
    openaiKey: 'test',
    userMessage: '¿Qué servicios ofrecen?',
    draft: 'Ofrecemos consultoría y soporte.',
    fetchImpl: async () => { calls++; throw new Error('no debe ejecutarse'); }
  });
  assert.strictEqual(untouched, 'Ofrecemos consultoría y soporte.');
  assert.strictEqual(calls, 0);

  const corrected = await enforceDisabledAgendaResponse({
    openaiKey: 'test',
    history: [
      { role: 'user', content: 'Quiero agendarme con Rolando' },
      { role: 'assistant', content: 'La gestión de citas no está disponible.' }
    ],
    userMessage: 'Quiero dejarle un mensaje. Soy Humberto y mi número es 0985123456.',
    draft: 'La gestión de citas no está disponible en este momento.',
    fetchImpl: async () => ({
      ok: true,
      json: async () => ({ choices: [{ message: { content: JSON.stringify({
        solicita_gestion_de_cita: false,
        bloqueo_indebido: true,
        respuesta_segura: '',
        respuesta_corregida: 'Entendí, Humberto. Tu mensaje y número quedan registrados en esta conversación para que el equipo pueda verlos.'
      }) } }] })
    })
  });
  assert.match(corrected, /Humberto/);
  assert.match(corrected, /registrados en esta conversación/i);
  assert.doesNotMatch(corrected, /gestión de citas no está disponible/i);

  console.log('✓ regla prioritaria para Agenda deshabilitada');
  console.log('✓ detección semántica de intención de cita');
  console.log('✓ bloqueo de solicitud de datos y promesas falsas');
  console.log('✓ conversaciones ajenas a Agenda no pagan una segunda llamada');
  console.log('✓ una intención nueva no queda secuestrada por Agenda');
  console.log('5/5 pruebas superadas');
}

run().catch(error => {
  console.error(error);
  process.exit(1);
});
