// La API devuelve "2019-11-25"; se formatea sin pasar por Date para evitar desfases de zona horaria
export const formatDate = (iso) => iso.split('-').reverse().join('/')

// La API devuelve importes como texto ("1234.50"): se muestran en formato español
const euros = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' })
export const formatMoney = (amount) => euros.format(Number(amount))

export const months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
export const formatPeriod = ({ year, month }) => `${months[month - 1]} ${year}`
