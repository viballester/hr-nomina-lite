import express from 'express';
import { requireToken } from './auth.js';

// App separada del arranque (server.js) para poder testearla sin abrir un puerto
export function createApp({ token }) {
  if (!token) throw new Error('Falta el token compartido (PDF_SERVICE_TOKEN)');

  const app = express();
  app.use(express.json({ limit: '100kb' }));

  // Público: lo usa Docker para saber si el servicio está vivo
  app.get('/health', (req, res) => res.json({ status: 'ok' }));

  // Todo lo registrado a partir de aquí exige el token
  app.use(requireToken(token));

  return app;
}
