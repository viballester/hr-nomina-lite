// La API devuelve "2019-11-25"; se formatea sin pasar por Date para evitar desfases de zona horaria
export const formatDate = (iso) => iso.split('-').reverse().join('/')
