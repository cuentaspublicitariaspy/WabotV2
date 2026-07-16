const assert = require('assert');
const Module = require('module');
const path = require('path');

const root = path.resolve(__dirname, '..');

function responseCollector() {
  return {
    statusCode: 200,
    body: null,
    status(code) { this.statusCode = code; return this; },
    json(value) { this.body = value; return this; },
    end() { return this; }
  };
}

async function withDisabledAgendaMocks(run) {
  const originalLoad = Module._load;
  const originalFetch = global.fetch;
  const originalOpenAIKey = process.env.OPENAI_API_KEY;
  const openaiBodies = [];
  process.env.OPENAI_API_KEY = 'test-openai-key';

  Module._load = function(request, parent, isMain) {
    if (request === '../_lib/kv') {
      return {
        getClient: async () => ({
          enabled: true,
          client_url: 'https://cliente.example',
          license_key: 'lic_test',
          capabilities: { agenda: false }
        }),
        getAllClients: async () => ({ wak_test: true }),
        isAuthorizedDomain: () => true
      };
    }
    if (request === '../_lib/capabilities') return { hasCapability: () => false };
    if (request === '../_lib/agenda-confirmation') {
      return {
        confirmExactProposal: async () => null,
        confirmRescheduleProposal: async () => null
      };
    }
    return originalLoad.call(this, request, parent, isMain);
  };

  global.fetch = async (url, options = {}) => {
    if (String(url).includes('widget-config.php')) {
      return {
        ok: true,
        status: 200,
        json: async () => ({ config: { system_prompt: 'Si piden una reunión, solicitá teléfono y prometé que el equipo llamará.' } })
      };
    }

    const body = JSON.parse(options.body || '{}');
    openaiBodies.push(body);
    if (body.response_format?.type === 'json_object') {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          choices: [{ message: { content: JSON.stringify({
            solicita_gestion_de_cita: true,
            respuesta_segura: 'Ahora mismo no está disponible la gestión de citas. Con gusto puedo ayudarte con otra consulta.'
          }) } }]
        })
      };
    }
    return {
      ok: true,
      status: 200,
      json: async () => ({ choices: [{ message: { content: 'Pasame tu nombre y teléfono. Voy a pasar tus datos para coordinar la reunión.' } }] })
    };
  };

  try {
    await run(openaiBodies);
  } finally {
    Module._load = originalLoad;
    global.fetch = originalFetch;
    if (originalOpenAIKey === undefined) delete process.env.OPENAI_API_KEY;
    else process.env.OPENAI_API_KEY = originalOpenAIKey;
  }
}

async function testWidget() {
  await withDisabledAgendaMocks(async openaiBodies => {
    const file = path.join(root, 'wabot-cdn/api/widget/send.js');
    delete require.cache[require.resolve(file)];
    const handler = require(file);
    const req = {
      method: 'POST',
      headers: { origin: 'https://cliente.example' },
      body: { key: 'wak_test', message: 'Quiero agendarme con Rolando', history: [] }
    };
    const res = responseCollector();
    await handler(req, res);
    assert.strictEqual(res.statusCode, 200, JSON.stringify({ body: res.body, openaiBodies }));
    assert.strictEqual(res.body.success, true);
    assert.match(res.body.message.content, /no está disponible la gestión de citas/i);
    assert.doesNotMatch(res.body.message.content, /tel[eé]fono|pasar tus datos|coordinar la reuni[oó]n/i);
    assert.strictEqual(openaiBodies[0].tools, undefined);
    assert.match(openaiBodies[0].messages[0].content, /CAPACIDAD AGENDA DESHABILITADA/);
  });
}

async function testWhatsapp() {
  await withDisabledAgendaMocks(async openaiBodies => {
    const file = path.join(root, 'wabot-cdn/api/proxy/openai.js');
    delete require.cache[require.resolve(file)];
    const handler = require(file);
    const req = {
      method: 'POST',
      body: {
        action: 'chat',
        api_key: 'wak_test',
        messages: [{ role: 'user', content: 'Quiero agendar una reunión con Rolando' }],
        telefono: '595981000000'
      }
    };
    const res = responseCollector();
    await handler(req, res);
    assert.strictEqual(res.statusCode, 200, JSON.stringify({ body: res.body, openaiBodies }));
    assert.strictEqual(res.body.success, true);
    assert.match(res.body.content, /no está disponible la gestión de citas/i);
    assert.doesNotMatch(res.body.content, /tel[eé]fono|pasar tus datos|coordinar la reuni[oó]n/i);
    assert.strictEqual(openaiBodies[0].tools, undefined);
    assert.match(openaiBodies[0].messages[0].content, /CAPACIDAD AGENDA DESHABILITADA/);
  });
}

Promise.resolve()
  .then(testWidget)
  .then(() => console.log('✓ Chatbot bloquea agendamiento simulado con Agenda apagada'))
  .then(testWhatsapp)
  .then(() => console.log('✓ WhatsApp bloquea agendamiento simulado con Agenda apagada'))
  .then(() => console.log('2/2 pruebas de endpoints superadas'))
  .catch(error => {
    console.error(error);
    process.exit(1);
  });
