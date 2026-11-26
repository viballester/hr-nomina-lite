// Longitud acotada (hasta 10 dígitos enteros): evita que un texto enorme dispare el coste de las regex
const AMOUNT = /^-?\d{1,10}\.\d{2}$/; // mismo formato que el cast decimal:2 de Laravel
const isAmount = (v) => typeof v === 'string' && AMOUNT.test(v);
const isText = (v) => typeof v === 'string' && v.trim() !== '' && v.length <= 200;

// Devuelve la lista de errores; vacía si el payload es válido.
// Escrito a mano: son pocos campos y no justifican una dependencia (Joi, Zod...).
export function validatePayslip(body) {
  if (typeof body !== 'object' || body === null) return ['El cuerpo debe ser un objeto JSON'];
  const errors = [];

  for (const field of ['employee', 'department', 'position']) {
    if (!isText(body[field])) errors.push(`${field}: texto obligatorio`);
  }
  if (!Number.isInteger(body.year) || body.year < 2000 || body.year > 2100) errors.push('year: entero entre 2000 y 2100');
  if (!Number.isInteger(body.month) || body.month < 1 || body.month > 12) errors.push('month: entero entre 1 y 12');
  for (const field of ['gross', 'deductions', 'net']) {
    if (!isAmount(body[field])) errors.push(`${field}: importe con formato 0.00`);
  }

  if (!Array.isArray(body.lines) || body.lines.length === 0 || body.lines.length > 50) {
    errors.push('lines: lista de 1 a 50 líneas');
  } else {
    body.lines.forEach((line, i) => {
      if (!isText(line?.concept)) errors.push(`lines.${i}.concept: texto obligatorio`);
      if (!['earning', 'deduction'].includes(line?.type)) errors.push(`lines.${i}.type: earning o deduction`);
      if (!isAmount(line?.amount)) errors.push(`lines.${i}.amount: importe con formato 0.00`);
    });
  }
  return errors;
}
