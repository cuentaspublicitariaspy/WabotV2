const { kv } = require('@vercel/kv');

const CACHE_TTL = 60;

async function getClient(apiKey) {
  try {
    return await kv.get(`client:${apiKey}`);
  } catch {
    return null;
  }
}

async function setClient(apiKey, data) {
  await kv.set(`client:${apiKey}`, data);
}

async function deleteClient(apiKey) {
  await kv.del(`client:${apiKey}`);
}

async function getAllClients() {
  return await kv.get('clients_index') || {};
}

async function saveClientsIndex(index) {
  await kv.set('clients_index', index);
}

async function getCachedConfig(apiKey) {
  return await kv.get(`config_cache:${apiKey}`);
}

async function setCachedConfig(apiKey, data) {
  await kv.set(`config_cache:${apiKey}`, data, { ex: CACHE_TTL });
}

function normalizeDomain(value) {
  if (!value) return '';
  let candidate = String(value).trim().toLowerCase();
  if (!/^https?:\/\//.test(candidate)) candidate = `https://${candidate}`;
  try {
    return new URL(candidate).hostname.replace(/^www\./, '');
  } catch {
    return '';
  }
}

function getRequestDomain(req) {
  const origin = req.headers?.origin || req.query?.origin || '';
  return normalizeDomain(origin);
}

function isAuthorizedDomain(req, client) {
  const allowed = normalizeDomain(client?.authorized_domain || client?.client_url);
  const requested = getRequestDomain(req);
  return Boolean(allowed && requested && allowed === requested);
}

module.exports = {
  getClient, setClient, deleteClient,
  getAllClients, saveClientsIndex,
  getCachedConfig, setCachedConfig,
  normalizeDomain, getRequestDomain, isAuthorizedDomain,
  CACHE_TTL
};
