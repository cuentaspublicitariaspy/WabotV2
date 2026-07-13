const jwt = require('jsonwebtoken');
const crypto = require('crypto');
const { getClient, setClient, deleteClient, getAllClients, saveClientsIndex } = require('../_lib/kv');

function auth(req, res) {
  const header = req.headers['authorization'] || '';
  const token = header.replace('Bearer ', '');
  if (!token) {
    res.status(401).json({ success: false, error: 'Token requerido' });
    return null;
  }
  try {
    const decoded = jwt.verify(token, process.env.ABM_SECRET);
    return decoded;
  } catch {
    res.status(401).json({ success: false, error: 'Token inválido o expirado' });
    return null;
  }
}

function generateApiKey() {
  return 'wak_' + crypto.randomBytes(24).toString('hex');
}

function generateLicenseKey() {
  return 'lic_' + crypto.randomBytes(16).toString('hex');
}

module.exports = async (req, res) => {
  if (req.method === 'OPTIONS') {
    res.status(200).end();
    return;
  }

  const user = auth(req, res);
  if (!user) return;

  try {
    switch (req.method) {
      case 'GET':
        return handleGet(req, res);
      case 'POST':
        return handlePost(req, res);
      case 'DELETE':
        return handleDelete(req, res);
      default:
        res.status(405).json({ success: false, error: 'Method not allowed' });
    }
  } catch (err) {
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};

async function handleGet(req, res) {
  const clients = await getAllClients();
  const full = {};
  for (const [key] of Object.entries(clients)) {
    const client = await getClient(key);
    if (client) full[key] = client;
  }
  res.json({ success: true, clients: full });
}

async function handlePost(req, res) {
  const { api_key, name, client_url, enabled, rate_limits } = req.body || {};

  if (api_key) {
    const existing = await getClient(api_key);
    if (!existing) {
      res.status(404).json({ success: false, error: 'Cliente no encontrado' });
      return;
    }

    const updated = {
      ...existing,
      name: name !== undefined ? name : existing.name,
      client_url: client_url !== undefined ? client_url : existing.client_url,
      enabled: enabled !== undefined ? enabled : existing.enabled,
      rate_limits: rate_limits || existing.rate_limits || { chat: 60, transcribe: 6 }
    };

    await setClient(api_key, updated);
    res.json({ success: true });
    return;
  }

  if (!name || !client_url) {
    res.status(400).json({ success: false, error: 'Nombre y URL del cliente son requeridos' });
    return;
  }

  const key = generateApiKey();
  const licenseKey = generateLicenseKey();

  const clientData = {
    name,
    client_url,
    enabled: enabled !== undefined ? enabled : true,
    license_key: licenseKey,
    api_key: key,
    rate_limits: rate_limits || { chat: 60, transcribe: 6 },
    created_at: new Date().toISOString()
  };

  await setClient(key, clientData);

  const index = await getAllClients();
  index[key] = { name, created_at: clientData.created_at };
  await saveClientsIndex(index);

  res.json({
    success: true,
    api_key: key,
    license_key: licenseKey,
    client: clientData
  });
}

async function handleDelete(req, res) {
  const { api_key } = req.body || {};
  if (!api_key) {
    res.status(400).json({ success: false, error: 'api_key requerida' });
    return;
  }

  await deleteClient(api_key);

  const index = await getAllClients();
  delete index[api_key];
  await saveClientsIndex(index);

  res.json({ success: true });
}
