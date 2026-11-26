import http from '@/api/http'

// El PDF va protegido con el token, así que no basta un <a href>: se pide como blob
// con axios (que añade la cabecera Authorization) y se descarga con un enlace temporal.
export async function downloadPayslipPdf(payslip) {
  const response = await http.get(`/payslips/${payslip.id}/pdf`, { responseType: 'blob' })
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = `nomina-${payslip.period.year}-${String(payslip.period.month).padStart(2, '0')}-${payslip.id}.pdf`
  link.click()
  URL.revokeObjectURL(url)
}

// Con responseType 'blob' el JSON de error también llega como blob: hay que leerlo
export async function pdfErrorMessage(e) {
  try {
    return JSON.parse(await e.response.data.text()).message
  } catch {
    return 'No se ha podido descargar el PDF.'
  }
}
