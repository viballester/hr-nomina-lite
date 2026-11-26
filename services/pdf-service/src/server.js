import { createApp } from './app.js';

const port = Number(process.env.PORT ?? 3000);
// Sin token, createApp lanza un error y el proceso no arranca (fallo cerrado)
createApp({ token: process.env.PDF_SERVICE_TOKEN })
  .listen(port, () => console.log(`pdf-service escuchando en :${port}`));
