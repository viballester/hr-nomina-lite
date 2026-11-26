export const validPayslip = () => ({
  employee: 'Lucía Fernández',
  department: 'Recursos Humanos',
  position: 'Técnica de nóminas',
  year: 2026,
  month: 10,
  gross: '2150.00',
  deductions: '137.60',
  net: '2012.40',
  lines: [
    { concept: 'Salario base', type: 'earning', amount: '2000.00' },
    { concept: 'Antigüedad', type: 'earning', amount: '150.00' },
    { concept: 'Contingencias comunes', type: 'deduction', amount: '101.05' },
    { concept: 'Desempleo', type: 'deduction', amount: '33.33' },
    { concept: 'Formación profesional', type: 'deduction', amount: '3.22' },
  ],
});
