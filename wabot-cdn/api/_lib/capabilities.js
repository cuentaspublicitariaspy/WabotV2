const crypto = require('crypto');

const CAPABILITY_DEFINITIONS = Object.freeze({
  agenda: Object.freeze({ key: 'agenda', label: 'Agenda' })
});

function asBoolean(value) {
  return value === true || value === 1 || value === '1';
}

/**
 * Los clientes creados antes del sistema de capacidades ya usaban Agenda.
 * legacyEnabled evita cortarles el servicio durante la migración. Para altas
 * nuevas se usa false y la capacidad debe habilitarse expresamente desde WS.
 */
function normalizeCapabilities(value, { legacyEnabled = false } = {}) {
  const source = value && typeof value === 'object' ? value : null;
  return {
    agenda: source && Object.prototype.hasOwnProperty.call(source, 'agenda')
      ? asBoolean(source.agenda)
      : Boolean(legacyEnabled)
  };
}

function hasCapability(client, capability) {
  if (!Object.prototype.hasOwnProperty.call(CAPABILITY_DEFINITIONS, capability)) return false;
  return normalizeCapabilities(client?.capabilities, { legacyEnabled: true })[capability] === true;
}

function createCapabilityManifest(client, licenseKey) {
  const capabilities = normalizeCapabilities(client?.capabilities, { legacyEnabled: true });
  const manifest = {
    version: 1,
    issued_at: new Date().toISOString(),
    capabilities
  };
  const material = `v1|${manifest.issued_at}|agenda=${capabilities.agenda ? '1' : '0'}`;
  const signature = crypto.createHmac('sha256', String(licenseKey || '')).update(material).digest('hex');
  return { manifest, signature };
}

module.exports = {
  CAPABILITY_DEFINITIONS,
  normalizeCapabilities,
  hasCapability,
  createCapabilityManifest
};
