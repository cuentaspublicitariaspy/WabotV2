const { getClient } = require('../_lib/kv');

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
    const { action, license_key, messages, audio_base64, model } = req.body || {};

    if (!action) {
      res.status(400).json({ success: false, error: 'action requerida (chat o transcribe)' });
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
      res.json(data);
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
