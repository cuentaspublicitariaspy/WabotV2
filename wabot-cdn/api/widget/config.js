const { getClient, getCachedConfig, setCachedConfig, isAuthorizedDomain } = require('../_lib/kv');

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
    const apiKey = req.query.key || '';
    if (!apiKey) {
      res.status(400).json({ success: false, error: 'api_key requerida' });
      return;
    }

    const client = await getClient(apiKey);
    if (!client) {
      res.status(404).json({ success: false, error: 'Configuración no encontrada' });
      return;
    }

    if (!isAuthorizedDomain(req, client)) {
      res.status(403).json({ success: false, code: 'DOMAIN_NOT_AUTHORIZED', error: 'Este Chatbot no está autorizado para funcionar en este dominio.' });
      return;
    }

    if (client.client_url) {
      const cached = await getCachedConfig(apiKey);
      if (cached) {
        res.json({ success: true, config: cached });
        return;
      }

      try {
        const baseUrl = client.client_url.replace(/\/+$/, '');
        const candidateUrls = [
          `${baseUrl}/api/widget-config.php?key=${encodeURIComponent(apiKey)}`,
          `${baseUrl}/wabot/api/widget-config.php?key=${encodeURIComponent(apiKey)}`
        ];
        let response = null;
        for (const phpUrl of candidateUrls) {
          const controller = new AbortController();
          const timeout = setTimeout(() => controller.abort(), 5000);
          const attempt = await fetch(phpUrl, { signal: controller.signal });
          clearTimeout(timeout);
          if (attempt.ok) { response = attempt; break; }
        }
        if (response && response.ok) {
          const data = await response.json();
          if (data.success && data.config) {
            await setCachedConfig(apiKey, data.config);
            res.json({ success: true, config: data.config });
            return;
          }
        }
      } catch {
        // Fallback a defaults si PHP no responde
      }
    }

    const defaults = {
      primary_color: process.env.WIDGET_PRIMARY_COLOR || '#2F63E9',
      welcome_title: process.env.WIDGET_WELCOME_TITLE || 'Asistente',
      welcome_subtitle: process.env.WIDGET_WELCOME_SUBTITLE || 'Online',
      whatsapp_number: process.env.WIDGET_WHATSAPP || '',
      response_mode: 'ai'
    };

    if (client.widget_config) {
      res.json({ success: true, config: { ...defaults, ...client.widget_config } });
      return;
    }

    res.json({ success: true, config: defaults });
  } catch (err) {
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
