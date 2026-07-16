const { getClient, getAllClients } = require('../_lib/kv');
const { createCapabilityManifest, normalizeCapabilities } = require('../_lib/capabilities');

module.exports = async (req, res) => {
  if (req.method === 'OPTIONS') {
    res.status(200).end();
    return;
  }

  if (req.method !== 'GET') {
    res.status(405).json({ success: false, error: 'Method not allowed' });
    return;
  }

  try {
    const { domain, key } = req.query || {};

    if (!domain || !key) {
      res.status(400).json({ success: false, error: 'domain y key requeridos' });
      return;
    }

    const clients = await getAllClients();
    let foundClient = null;
    let foundApiKey = null;

    for (const [apiKey] of Object.entries(clients)) {
      const client = await getClient(apiKey);
      if (client && client.license_key === key) {
        foundClient = client;
        foundApiKey = apiKey;
        break;
      }
    }

    if (!foundClient) {
      res.json({ success: true, activo: false, nombre: '' });
      return;
    }

    const activo = foundClient.enabled === true || foundClient.enabled === '1' || foundClient.enabled === 1;

    const signed = createCapabilityManifest(foundClient, foundClient.license_key);
    res.json({
      success: true,
      activo,
      nombre: foundClient.name || '',
      api_key: foundApiKey,
      client_url: foundClient.client_url || '',
      capabilities: normalizeCapabilities(foundClient.capabilities, { legacyEnabled: true }),
      capability_manifest: signed.manifest,
      capability_signature: signed.signature
    });
  } catch (err) {
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
