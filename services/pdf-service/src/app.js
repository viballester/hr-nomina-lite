import express from 'express';
import { requireToken } from './auth.js';
import { validatePayslip } from './validate.js';
import { renderPayslip } from './payslipPdf.js';

// App separada del arranque (server.js) para poder testearla sin abrir un puerto
export function createApp({ token }) {
  if (!token) throw new Error('Falta el token compartido (PDF_SERVICE_TOKEN)');

  const app = express();
  app.use(express.json({ limit: '100kb' }));

  // Público: lo usa Docker para saber si el servicio está vivo
  app.get('/health', (req, res) => res.json({ status: 'ok' }));

  // Todo lo registrado a partir de aquí exige el token
  app.use(requireToken(token));

  app.post('/payslips/pdf', (req, res) => {
    const errors = validatePayslip(req.body);
    if (errors.length) return res.status(422).json({ message: 'Datos de nómina no válidos', errors });

    res.type('application/pdf');
    renderPayslip(req.body, res);
  });

  // Manejador de errores (4 argumentos): JSON mal formado, etc. Sin stack trace al cliente
  app.use((err, req, res, next) => {
    const status = err.status ?? 500;
    if (status >= 500) console.error(err);
    res.status(status).json({ message: status >= 500 ? 'Error interno' : 'Petición no válida' });
  });

  return app;
}
