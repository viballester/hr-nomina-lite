import express from 'express';

// App separada del arranque (server.js) para poder testearla sin abrir un puerto
export function createApp() {
  const app = express();
  app.use(express.json({ limit: '100kb' }));

  app.get('/health', (req, res) => res.json({ status: 'ok' }));

  return app;
}
