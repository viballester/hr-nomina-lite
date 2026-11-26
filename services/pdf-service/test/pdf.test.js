import { test } from 'node:test';
import assert from 'node:assert/strict';
import { withServer, TOKEN } from './helpers.js';
import { validPayslip } from './fixtures.js';
import { formatEuros } from '../src/payslipPdf.js';

const post = (url, body) => fetch(`${url}/payslips/pdf`, {
  method: 'POST',
  headers: { Authorization: `Bearer ${TOKEN}`, 'Content-Type': 'application/json' },
  body: typeof body === 'string' ? body : JSON.stringify(body),
});

test('genera un PDF con un payload válido', () => withServer(async (url) => {
  const res = await post(url, validPayslip());
  assert.equal(res.status, 200);
  assert.equal(res.headers.get('content-type'), 'application/pdf');
  const bytes = Buffer.from(await res.arrayBuffer());
  assert.equal(bytes.subarray(0, 5).toString(), '%PDF-');
}));

test('rechaza con 422 importes mal formados y lista vacía', () => withServer(async (url) => {
  const res = await post(url, { ...validPayslip(), net: 2012.4, lines: [] });
  assert.equal(res.status, 422);
  const { errors } = await res.json();
  assert.ok(errors.includes('net: importe con formato 0.00'));
  assert.ok(errors.includes('lines: lista de 1 a 50 líneas'));
}));

test('rechaza con 422 una línea con tipo desconocido', () => withServer(async (url) => {
  const body = validPayslip();
  body.lines[0].type = 'bonus';
  const res = await post(url, body);
  assert.equal(res.status, 422);
  assert.deepEqual((await res.json()).errors, ['lines.0.type: earning o deduction']);
}));

test('JSON mal formado devuelve 400 sin stack trace', () => withServer(async (url) => {
  const res = await post(url, '{roto');
  assert.equal(res.status, 400);
  assert.deepEqual(await res.json(), { message: 'Petición no válida' });
}));

test('formatEuros usa formato español sin pasar por float', () => {
  assert.equal(formatEuros('1234567.89'), '1.234.567,89 €');
  assert.equal(formatEuros('0.05'), '0,05 €');
  assert.equal(formatEuros('-150.00'), '-150,00 €');
});
