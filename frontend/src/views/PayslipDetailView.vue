<script setup>
import { ref, onMounted } from 'vue'
import http from '@/api/http'
import { downloadPayslipPdf, pdfErrorMessage } from '@/api/payslips'
import { formatDate, formatMoney, formatPeriod } from '@/utils/format'

const props = defineProps({ id: { type: String, required: true } })

const payslip = ref(null)
const error = ref('')
const downloading = ref(false)

async function download() {
  error.value = ''
  downloading.value = true
  try {
    await downloadPayslipPdf(payslip.value)
  } catch (e) {
    error.value = await pdfErrorMessage(e)
  } finally {
    downloading.value = false
  }
}

onMounted(async () => {
  try {
    payslip.value = (await http.get(`/payslips/${props.id}`)).data.data
  } catch (e) {
    // 403: nómina ajena o de un periodo aún en borrador · 404: no existe
    error.value = e.response?.status === 403
      ? 'No tienes permiso para ver esta nómina.' // el mensaje por defecto de Laravel viene en inglés
      : (e.response?.data?.message ?? 'No se ha podido cargar la nómina.')
  }
})
</script>

<template>
  <div class="page-header">
    <h1>Nómina{{ payslip ? ` · ${formatPeriod(payslip.period)}` : '' }}</h1>
    <div class="actions">
      <RouterLink :to="{ name: 'payslips' }" class="btn btn-secondary">Volver</RouterLink>
      <button v-if="payslip" type="button" class="btn" :disabled="downloading" @click="download">
        {{ downloading ? 'Generando…' : 'Descargar PDF' }}
      </button>
    </div>
  </div>

  <p v-if="error" class="error" role="alert">{{ error }}</p>

  <template v-if="payslip">
    <!-- Datos del snapshot: tal como estaban al generar la nómina, aunque luego cambie la ficha -->
    <section class="card" aria-labelledby="employee-title">
      <h2 id="employee-title">{{ payslip.snapshot.employee_name }}</h2>
      <dl class="stats">
        <div><dt>Departamento</dt><dd>{{ payslip.snapshot.department }}</dd></div>
        <div><dt>Puesto</dt><dd>{{ payslip.snapshot.position }}</dd></div>
        <div><dt>Alta</dt><dd>{{ formatDate(payslip.snapshot.hire_date) }}</dd></div>
        <div><dt>Antigüedad</dt><dd>{{ payslip.snapshot.seniority_years }} años</dd></div>
      </dl>
    </section>

    <div class="card table-wrapper">
      <table class="table">
        <thead>
          <tr>
            <th>Concepto</th>
            <th class="amount">Devengos</th>
            <th class="amount">Deducciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(line, i) in payslip.lines" :key="i">
            <td>{{ line.concept }}</td>
            <td class="amount">{{ line.type === 'earning' ? formatMoney(line.amount) : '' }}</td>
            <td class="amount">{{ line.type === 'deduction' ? formatMoney(line.amount) : '' }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <th>Totales</th>
            <td class="amount">{{ formatMoney(payslip.gross) }}</td>
            <td class="amount">{{ formatMoney(payslip.deductions) }}</td>
          </tr>
          <tr>
            <th>Líquido a percibir</th>
            <td colspan="2" class="amount"><strong>{{ formatMoney(payslip.net) }}</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <p class="hint">Simulación didáctica: IRPF y Seguridad Social con porcentajes fijos, sin validez legal.</p>
  </template>
</template>
