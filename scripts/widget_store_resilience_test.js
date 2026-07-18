const fs = require('fs');
const assert = require('assert');

const source = fs.readFileSync('api/widget/store.php', 'utf8');
let passed = 0;

function test(name, fn) {
  fn();
  passed += 1;
  console.log(`PASS ${name}`);
}

test('el mensaje se guarda antes de enriquecer prospectos', () => {
  assert(source.indexOf('INSERT IGNORE INTO widget_messages') > -1);
  assert(source.indexOf('INSERT IGNORE INTO widget_messages') < source.indexOf('new ProspectManager'));
});

test('ProspectManager no es una dependencia fatal del endpoint', () => {
  assert(source.includes("if (is_file($prospectManagerPath)) require_once $prospectManagerPath"));
  assert(source.includes("if (class_exists('ProspectManager'))"));
});

test('no ejecuta migraciones DDL durante cada mensaje', () => {
  assert(!/ALTER\s+TABLE/i.test(source));
  assert(!/CREATE\s+TABLE/i.test(source));
});

test('funciona con bases anteriores sin client_message_id', () => {
  assert(source.includes('INSERT INTO widget_messages (chat_id, role, content) VALUES (?, ?, ?)'));
});

test('funciona con bases anteriores sin memory_message_count', () => {
  assert(source.includes("UPDATE widget_chats SET unread = ?, updated_at = NOW() WHERE id = ?"));
});

test('un error de prospectos no altera el resultado del guardado', () => {
  assert(source.includes("'] prospect: '"));
  assert(source.lastIndexOf("widgetStoreReply(200") > source.indexOf("'] prospect: '"));
});

console.log(`${passed}/6 widget store resilience tests passed`);
