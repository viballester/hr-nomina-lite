import { describe, it, expect } from 'vitest'
import { formatDate, formatMoney, formatPeriod } from '@/utils/format'

// Intl usa un espacio duro (U+00A0) antes del símbolo €: se normaliza para comparar.
const plain = (s) => s.replace(/ /g, ' ')

describe('formatDate', () => {
  it('convierte la fecha ISO de la API a dd/mm/aaaa', () => {
    expect(formatDate('2026-11-05')).toBe('05/11/2026')
  })
})

describe('formatMoney', () => {
  it('formatea en euros con coma decimal y punto de miles', () => {
    expect(plain(formatMoney('12345.5'))).toBe('12.345,50 €')
  })

  it('no separa los miles en números de 4 cifras (norma RAE que aplica es-ES)', () => {
    expect(plain(formatMoney('1234.50'))).toBe('1234,50 €')
  })

  it('acepta el importe como texto, tal como llega de la API', () => {
    expect(plain(formatMoney('0.00'))).toBe('0,00 €')
  })
})

describe('formatPeriod', () => {
  it('muestra el mes en español (los meses van de 1 a 12)', () => {
    expect(formatPeriod({ year: 2026, month: 1 })).toBe('Enero 2026')
    expect(formatPeriod({ year: 2026, month: 12 })).toBe('Diciembre 2026')
  })
})
