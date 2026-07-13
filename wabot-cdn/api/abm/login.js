const jwt = require('jsonwebtoken');

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
    const { user, password } = req.body || {};

    if (!user || !password) {
      res.status(400).json({ success: false, error: 'Usuario y contraseña requeridos' });
      return;
    }

    const validUser = process.env.ABM_USER || 'admin';
    const validPassword = process.env.ABM_PASSWORD;
    const secret = process.env.ABM_SECRET;

    if (!validPassword || !secret) {
      res.status(500).json({ success: false, error: 'Error de configuración del servidor' });
      return;
    }

    if (user !== validUser || password !== validPassword) {
      res.status(401).json({ success: false, error: 'Credenciales inválidas' });
      return;
    }

    const token = jwt.sign(
      { user, role: 'admin', iat: Math.floor(Date.now() / 1000) },
      secret,
      { expiresIn: '24h' }
    );

    res.json({ success: true, token, user });
  } catch (err) {
    res.status(500).json({ success: false, error: 'Error interno del servidor' });
  }
};
