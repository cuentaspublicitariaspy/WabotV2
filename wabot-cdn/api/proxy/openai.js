const { getClient, getAllClients } = require('../_lib/kv');

const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_TRANSCRIBE_URL = 'https://api.openai.com/v1/audio/transcriptions';

module.exports = async (req, res) => {
  if (req.method === 'OPTIONS') {
    res.status(200).end();
    return;
  }

  if (req.method !== 'POST') {
    res.status(405).json({ success: false, error: 'Method not allowed' });
    return;
  }

  try {
    const { action, license_key, api_key, messages, audio_base64, model } = req.body || {};

    if (!action) {
      res.status(400).json({ success: false, error: 'action requerida (chat o transcribe)' });
      return;
    }

    // Toda llamada de WC debe pertenecer a un cliente activo. En la parte
    // pública del Chatbot usamos su API Key; en WhatsApp/administración se
    // valida la License Key. Ningún mensaje se persiste en WS.
    let client = null;
    if (api_key) client = await getClient(api_key);
    if (!client && license_key) {
      const clients = await getAllClients();
      for (const candidate of Object.keys(clients)) {
        const possibleClient = await getClient(candidate);
        if (possibleClient?.license_key === license_key) {
          client = possibleClient;
          break;
        }
      }
    }
    if (!client || (license_key && client.license_key !== license_key)) {
      res.status(403).json({ success: false, error: 'Licencia o cliente no autorizado' });
      return;
    }

    const openaiKey = process.env.OPENAI_API_KEY || process.env.OpenAIKey;
    if (!openaiKey) {
      res.status(500).json({ success: false, error: 'Error de configuración del servidor' });
      return;
    }

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 25000);

    if (action === 'chat') {
      if (!messages) {
        clearTimeout(timeout);
        res.status(400).json({ success: false, error: 'messages requerido' });
        return;
      }

      const openaiRes = await fetch(OPENAI_CHAT_URL, {
        method: 'POST',
        signal: controller.signal,
        headers: {
          'Authorization': `Bearer ${openaiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          model: model || 'gpt-4o-mini',
          messages,
          max_tokens: 1024,
          temperature: 0.7
        })
      });
      clearTimeout(timeout);

      const data = await openaiRes.json();
      if (!openaiRes.ok) {
        res.status(openaiRes.status).json({
          success: false,
          error: 'OpenAI no pudo generar una respuesta',
          detail: data?.error?.message || null
        });
        return;
      }

      const content = data?.choices?.[0]?.message?.content?.trim();
      if (!content) {
        res.status(502).json({ success: false, error: 'OpenAI devolvió una respuesta vacía' });
        return;
      }

      // Contrato estable para WC. No se filtra la respuesta completa de OpenAI.
      res.json({ success: true, content });
      return;
    }

    if (action === 'transcribe') {
      if (!audio_base64) {
        clearTimeout(timeout);
        res.status(400).json({ success: false, error: 'audio_base64 requerido' });
        return;
      }

      const buffer = Buffer.from(audio_base64, 'base64');
      const blob = new Blob([buffer], { type: 'audio/ogg' });
      const formData = new FormData();
      formData.append('file', blob, 'audio.ogg');
      formData.append('model', 'whisper-1');

      const openaiRes = await fetch(OPENAI_TRANSCRIBE_URL, {
        method: 'POST',
        signal: controller.signal,
        headers: { 'Authorization': `Bearer ${openaiKey}` },
        body: formData
      });
      clearTimeout(timeout);

      const data = await openaiRes.json();
      res.json(data);
      return;
    }

    clearTimeout(timeout);
    res.status(400).json({ success: false, error: 'acción no válida' });
  } catch (err) {
    if (err.name === 'AbortError') {
      res.status(504).json({ success: false, error: 'Tiempo de espera agotado' });
      return;
    }
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
