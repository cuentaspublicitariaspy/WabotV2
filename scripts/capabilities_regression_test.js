const assert = require('assert');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const { normalizeCapabilities, hasCapability, createCapabilityManifest } = require('../wabot-cdn/api/_lib/capabilities');

const tests = [];
function test(name, fn) { tests.push({ name, fn }); }

test('clientes antiguos conservan Agenda durante la migración', () => {
  assert.deepStrictEqual(normalizeCapabilities(undefined, { legacyEnabled: true }), { agenda: true });
  assert.strictEqual(hasCapability({}, 'agenda'), true);
});

test('clientes nuevos no reciben Agenda de forma implícita', () => {
  assert.deepStrictEqual(normalizeCapabilities(undefined), { agenda: false });
  assert.deepStrictEqual(normalizeCapabilities({ agenda: false }), { agenda: false });
});

test('manifiesto WS tiene firma HMAC verificable', () => {
  const key = 'lic_prueba_segura';
  const signed = createCapabilityManifest({ capabilities: { agenda: true } }, key);
  const material = `v1|${signed.manifest.issued_at}|agenda=1`;
  const expected = crypto.createHmac('sha256', key).update(material).digest('hex');
  assert.strictEqual(signed.signature, expected);
});

test('licencia WS entrega capacidades y firma', () => {
  const source = read('wabot-cdn/api/license/check.js');
  assert.match(source, /capability_manifest/);
  assert.match(source, /capability_signature/);
  assert.match(source, /normalizeCapabilities\(foundClient\.capabilities/);
});

test('ABM WS guarda capacidades por cliente', () => {
  const source = read('wabot-cdn/api/abm/clients.js');
  assert.match(source, /capabilities !== undefined/);
  assert.match(source, /normalizeCapabilities\(capabilities\)/);
});

test('WC oculta Agenda del menú si no está autorizada', () => {
  const source = read('includes/layout_tailwind.php');
  assert.match(source, /License::hasCapability\('agenda'\)/);
  assert.match(source, /unset\(\$navLinks\['agenda'\]\)/);
});

test('página y APIs de Agenda están protegidas en servidor', () => {
  assert.match(read('agenda.php'), /License::requireCapability\('agenda'\)/);
  assert.match(read('ajax/agenda.php'), /License::requireCapability\('agenda', true\)/);
  assert.match(read('api/agenda/assistant.php'), /License::requireCapability\('agenda', true\)/);
});

test('WhatsApp y Chatbot no reciben herramientas Agenda si está deshabilitada', () => {
  for (const file of ['wabot-cdn/api/proxy/openai.js', 'wabot-cdn/api/widget/send.js']) {
    const source = read(file);
    assert.match(source, /hasCapability\(client, 'agenda'\)/);
    assert.match(source, /client\.client_url && agendaEnabled/);
  }
});

test('deshabilitar capacidades nunca borra estructura ni datos de Agenda', () => {
  const capabilitySources = [
    read('includes/License.php'),
    read('wabot-cdn/api/_lib/capabilities.js'),
    read('wabot-cdn/api/abm/clients.js'),
    read('wabot-cdn/api/license/check.js')
  ].join('\n');
  assert.doesNotMatch(capabilitySources, /\b(?:DROP|TRUNCATE)\b/i);
  assert.doesNotMatch(capabilitySources, /DELETE\s+FROM\s+agenda_/i);
});

let failures = 0;
for (const item of tests) {
  try {
    item.fn();
    console.log(`✓ ${item.name}`);
  } catch (error) {
    failures++;
    console.error(`✗ ${item.name}`);
    console.error(error.message);
  }
}

console.log(`\n${tests.length - failures}/${tests.length} pruebas superadas`);
if (failures) process.exit(1);
