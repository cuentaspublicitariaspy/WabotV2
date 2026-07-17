const assert = require('assert');
const fs = require('fs');
const path = require('path');
const { storageBases, persistWidgetExchange } = require('../wabot-cdn/api/_lib/widget-storage');

async function run() {
  assert.deepStrictEqual(storageBases('https://cliente.example'), ['https://cliente.example/wabot', 'https://cliente.example']);
  assert.deepStrictEqual(storageBases('https://cliente.example/wabot/'), ['https://cliente.example/wabot']);

  const originalFetch = global.fetch;
  const requests = [];
  global.fetch = async (url, options) => {
    requests.push({ url: String(url), options, body: JSON.parse(options.body) });
    return { ok: true, status: 200, json: async () => ({ success: true }) };
  };
  try {
    const result = await persistWidgetExchange({
      client: { client_url: 'https://cliente.example' },
      apiKey: 'wak_test', sessionId: 'session-1',
      visitorMessage: 'Hola', assistantMessage: 'Hola, ¿cómo estás?',
      visitorMessageId: 'message-in', assistantMessageId: 'message-out'
    });
    assert.strictEqual(result.stored, true);
    assert.strictEqual(requests.length, 2);
    assert.deepStrictEqual(requests.map(item => item.body.role), ['visitor', 'assistant']);
    assert.deepStrictEqual(requests.map(item => item.body.client_message_id), ['message-in', 'message-out']);
    assert(requests.every(item => item.options.headers.Origin === 'https://cliente.example'));
  } finally {
    global.fetch = originalFetch;
  }

  const widget = fs.readFileSync(path.resolve(__dirname, '../wabot-cdn/public/widget.js'), 'utf8');
  assert.match(widget, /session_id=/);
  assert.match(widget, /visitor_message_id=/);
  assert.match(widget, /assistant_message_id=/);

  console.log('✓ rutas WC compatibles');
  console.log('✓ intercambio visitante/asistente persistido con IDs estables');
  console.log('✓ navegador y WS comparten los mismos identificadores');
  console.log('3/3 pruebas de persistencia superadas');
}

run().catch(error => { console.error(error); process.exit(1); });
