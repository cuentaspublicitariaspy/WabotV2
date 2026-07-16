#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');
const parser = require('/tmp/node_modules/php-parser');

const root = path.resolve(__dirname, '..');
const files = {
  manager: path.join(root, 'includes', 'AppointmentManager.php'),
  assistant: path.join(root, 'api', 'agenda', 'assistant.php'),
};

const source = Object.fromEntries(
  Object.entries(files).map(([key, file]) => [key, fs.readFileSync(file, 'utf8')])
);

const engine = new parser.Engine({
  parser: { extractDoc: true, suppressErrors: false },
  ast: { withPositions: true },
});

const checks = [];
function check(name, condition) {
  checks.push({ name, condition: Boolean(condition) });
}

for (const [name, code] of Object.entries(source)) {
  try {
    engine.parseCode(code, files[name]);
    check(`${name}: sintaxis PHP válida`, true);
  } catch (error) {
    check(`${name}: sintaxis PHP válida (${error.message})`, false);
  }
}

const createBlock = source.assistant.match(/if\(\$action==='crear'\)[\s\S]*?if\(\$action==='estado'\)/)?.[0] || '';
const rescheduleBlock = source.assistant.match(/if\(\$action==='reprogramar'\)[\s\S]*?if\(\$action==='cancelar'\)/)?.[0] || '';

check('crear no exige confirmación adicional', !createBlock.includes("$input['confirmada']"));
check('reprogramar no exige confirmación adicional', !rescheduleBlock.includes("$input['confirmada']"));
check('disponibilidad conversacional ofrece máximo dos horarios', source.assistant.includes("'slots'=>array_slice($slots,0,2)"));
check('próximos horarios ofrece máximo dos alternativas totales', source.manager.includes('$i<$days&&$totalSlots<2') && source.manager.includes('array_slice($slots,0,2-$totalSlots)'));
check('reprogramación ignora la propia cita al consultar', source.manager.includes("'cita_id'=>$id"));
check('fallo de reprogramación devuelve causa real', rescheduleBlock.includes("'error'=>$changeError->getMessage()"));
check('anticipación mínima tiene explicación explícita', source.manager.includes("' horas de anticipación mínima.'"));

const failed = checks.filter((item) => !item.condition);
for (const item of checks) {
  process.stdout.write(`${item.condition ? 'PASS' : 'FAIL'}  ${item.name}\n`);
}

if (failed.length) {
  process.stderr.write(`\n${failed.length} comprobación(es) fallaron.\n`);
  process.exit(1);
}

process.stdout.write(`\n${checks.length} comprobaciones superadas.\n`);
