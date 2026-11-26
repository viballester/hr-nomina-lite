import { createApp } from '../src/app.js';

export const TOKEN = 'token-de-test';

// Arranca la app en un puerto libre y devuelve su URL base
export async function withServer(fn) {
  const server = createApp({ token: TOKEN }).listen(0);
  try {
    await fn(`http://127.0.0.1:${server.address().port}`);
  } finally {
    server.close();
  }
}
