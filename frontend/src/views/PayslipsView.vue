<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import AppPagination from '@/components/AppPagination.vue'
import { downloadPayslipPdf, pdfErrorMessage } from '@/api/payslips'
import { formatMoney, formatPeriod } from '@/utils/format'

const auth = useAuthStore()
const route = useRoute()
// Solo el admin ve las de todos; el resto, las suyas de periodos cerrados (lo filtra la API)
const isAdmin = auth.user?.role === 'admin'

const payslips = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')
const downloadingId = ref(null)

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const response = await http.get('/payslips', { params: { period_id: route.query.period_id, page } })
    payslips.value = response.data.data
    meta.value = response.data.meta
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se ha podido cargar el listado.'
  } finally {
    loading.value = false
  }
}

async function download(payslip) {
  error.value = ''
  downloadingId.value = payslip.id
  try {
    await downloadPayslipPdf(payslip)
  } catch (e) {
    error.value = await pdfErrorMessage(e) // p. ej. 503 si el servicio PDF no responde
  } finally {
    downloadingId.value = null
  }
}

// El filtro vive en la URL (?period_id=): se recarga al llegar desde "Ver nóminas" o al quitarlo
watch(() => route.query.period_id, () => load(1), { immediate: true })
</script>

<template>
  <div class="page-header">
    <h1>{{ isAdmin ? 'Nóminas' : 'Mis nóminas' }}</h1>
    <RouterLink v-if="route.query.period_id" :to="{ name: 'payslips' }" class="btn btn-secondary">Quitar filtro de periodo</RouterLink>
  </div>

  <p v-if="error" class="error" role="alert">{{ error }}</p>

  <div class="card table-wrapper" :aria-busy="loading">
    <table class="table">
      <thead>
        <tr>
          <th>Periodo</th>
          <th v-if="isAdmin">Empleado</th>
          <th class="amount">Bruto</th>
          <th class="amount">Deducciones</th>
          <th class="amount">Neto</th>
          <th><span class="visually-hidden">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in payslips" :key="p.id">
          <td>
            {{ formatPeriod(p.period) }}
            <span v-if="p.period.status === 'draft'" class="badge badge-draft">Borrador</span>
          </td>
          <td v-if="isAdmin">{{ p.employee }}</td>
          <td class="amount">{{ formatMoney(p.gross) }}</td>
          <td class="amount">{{ formatMoney(p.deductions) }}</td>
          <td class="amount"><strong>{{ formatMoney(p.net) }}</strong></td>
          <td class="actions">
            <RouterLink :to="{ name: 'payslip-detail', params: { id: p.id } }" class="btn btn-small btn-secondary">Detalle</RouterLink>
            <button type="button" class="btn btn-small" :disabled="downloadingId === p.id" @click="download(p)">
              {{ downloadingId === p.id ? 'Generando…' : 'PDF' }}
            </button>
          </td>
        </tr>
        <tr v-if="!loading && payslips.length === 0">
          <td :colspan="isAdmin ? 6 : 5" class="empty">No hay nóminas.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <AppPagination :meta="meta" :loading="loading" @change="load" />
</template>
