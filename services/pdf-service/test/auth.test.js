import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createApp } from '../src/app.js';
import { withServer, TOKEN } from './helpers.js';

const post = (url, headers = {}) => fetch(`${url}/payslips/pdf`, { method: 'POST', headers });

test('sin cabecera Authorization devuelve 401', () => withServer(async (url) => {
  assert.equal((await post(url)).status, 401);
}));

test('con token incorrecto devuelve 401', () => withServer(async (url) => {
  assert.equal((await post(url, { Authorization: 'Bearer otro' })).status, 401);
}));

test('con token correcto pasa el middleware y llega a la validación (422)', () => withServer(async (url) => {
  assert.equal((await post(url, { Authorization: `Bearer ${TOKEN}` })).status, 422);
}));

test('no arranca sin token configurado', () => {
  assert.throws(() => createApp({ token: undefined }), /PDF_SERVICE_TOKEN/);
});
