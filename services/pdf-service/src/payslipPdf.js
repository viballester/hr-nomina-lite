import PDFDocument from 'pdfkit';

const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

// "1234.56" -> "1.234,56 €" trabajando con el texto: sin pasar por float (D-005)
export function formatEuros(amount) {
  const [int, dec] = amount.replace('-', '').split('.');
  const grouped = int.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  return `${amount.startsWith('-') ? '-' : ''}${grouped},${dec} €`;
}

// Escribe el recibo en `stream` (la respuesta HTTP). Solo presenta datos:
// todos los importes llegan ya calculados por Laravel.
export function renderPayslip(data, stream) {
  const doc = new PDFDocument({ size: 'A4', margin: 50, info: { Title: 'Recibo de nómina' } });
  doc.pipe(stream);

  const left = 50;
  const right = doc.page.width - 50;
  const amountX = right - 120;

  // Fila "texto ........ importe" alineada a ambos lados en la misma altura
  const row = (label, amount, font = 'Helvetica') => {
    const y = doc.y;
    doc.font(font).text(label, left, y, { width: amountX - left - 10 });
    const afterLabel = doc.y;
    doc.text(formatEuros(amount), amountX, y, { width: 120, align: 'right' });
    doc.y = Math.max(afterLabel, doc.y);
  };
  const rule = (color) => {
    doc.moveTo(left, doc.y).lineTo(right, doc.y).strokeColor(color).stroke().moveDown(0.3);
  };

  doc.fontSize(18).font('Helvetica-Bold').text('Recibo de nómina');
  doc.fontSize(11).font('Helvetica').fillColor('#555')
    .text(`Periodo: ${MONTHS[data.month - 1]} de ${data.year}`).moveDown();

  doc.fillColor('#000').font('Helvetica-Bold').text('Empleado');
  doc.font('Helvetica').text(data.employee).text(`${data.position} · ${data.department}`).moveDown();

  for (const [title, type] of [['Devengos', 'earning'], ['Deducciones', 'deduction']]) {
    doc.font('Helvetica-Bold').text(title, left);
    rule('#ccc');
    data.lines.filter((l) => l.type === type).forEach((l) => row(l.concept, l.amount));
    doc.moveDown();
  }

  rule('#000');
  row('Total devengado', data.gross);
  row('Total deducciones', data.deductions);
  doc.moveDown(0.3);
  row('Líquido a percibir', data.net, 'Helvetica-Bold');

  doc.moveDown(3).fontSize(8).font('Helvetica').fillColor('#888').text(
    'Documento de demostración con datos ficticios y cálculo simplificado. '
    + 'No es un recibo de salarios válido ni aplica la normativa de IRPF ni de Seguridad Social.',
    left, doc.y, { width: right - left, align: 'center' },
  );

  doc.end();
}
