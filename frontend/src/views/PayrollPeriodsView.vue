<script setup>
import { ref, onMounted } from 'vue'
import http from '@/api/http'
import AppPagination from '@/components/AppPagination.vue'
import { formatPeriod, months } from '@/utils/format'

const statusLabels = { draft: 'Borrador', closed: 'Cerrado' }

// --- Nuevo periodo ---
const today = new Date()
const form = ref({ year: today.getFullYear(), month: today.getMonth() + 1 })
const formErrors = ref({})
const formMessage = ref('')
const saving = ref(false)

async function submit() {
  formErrors.value = {}
  formMessage.value = ''
  saving.value = true
  try {
    await http.post('/payroll-periods', form.value)
    formMessage.value = 'Periodo creado. Ya puedes generar sus nóminas.'
    load(1)
  } catch (e) {
    if (e.response?.status === 422) {
      formErrors.value = e.response.data.errors // p. ej. "Ya existe un periodo para ese mes."
    } else {
      formMessage.value = e.response?.data?.message ?? 'No se ha podido conectar con el servidor.'
    }
  } finally {
    saving.value = false
  }
}

// --- Listado ---
const periods = ref([])
const meta = ref(null)
const loading = ref(false)
const error = ref('')
const busyId = ref(null) // periodo con una acción en curso: evita dobles clics

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const response = await http.get('/payroll-periods', { params: { page } })
    periods.value = response.data.data
    meta.value = response.data.meta
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se ha podido cargar el listado.'
  } finally {
    loading.value = false
  }
}

// generate: (re)calcula las nóminas del borrador · close: las congela para siempre
async function act(period, action) {
  const question = action === 'close'
    ? `¿Cerrar ${formatPeriod(period)}? Las nóminas quedarán definitivas y visibles para los empleados.`
    : `¿Generar las nóminas de ${formatPeriod(period)}? Si ya existían, se recalculan.`
  if (!confirm(question)) return

  error.value = ''
  busyId.value = period.id
  try {
    const updated = (await http[action === 'close' ? 'patch' : 'post'](`/payroll-periods/${period.id}/${action}`)).data.data
    Object.assign(period, updated) // la API devuelve el periodo actualizado: no hace falta recargar
  } catch (e) {
    // 422 de negocio: "El periodo está cerrado", "No se puede cerrar un periodo sin nóminas"
    const errors = e.response?.data?.errors
    error.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'No se ha podido completar la acción.')
  } finally {
    busyId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="page-header">
    <h1>Periodos de nómina</h1>
  </div>

  <section class="card" aria-labelledby="new-period-title">
    <h2 id="new-period-title">Nuevo periodo</h2>
    <form novalidate @submit.prevent="submit">
      <div class="field-row">
        <div class="field">
          <label for="month">Mes</label>
          <select id="month" v-model.number="form.month" :aria-invalid="!!formErrors.month" aria-describedby="month-error">
            <option v-for="m in 12" :key="m" :value="m">{{ months[m - 1] }}</option>
          </select>
          <p v-if="formErrors.month" id="month-error" class="field-error">{{ formErrors.month[0] }}</p>
        </div>
        <div class="field">
          <label for="year">Año</label>
          <input id="year" v-model.number="form.year" type="number" min="2000" max="2100" :aria-invalid="!!formErrors.year" aria-describedby="year-error">
          <p v-if="formErrors.year" id="year-error" class="field-error">{{ formErrors.year[0] }}</p>
        </div>
      </div>
      <button type="submit" class="btn" :disabled="saving">{{ saving ? 'Creando…' : 'Crear periodo' }}</button>
      <p v-if="formMessage" class="form-message" role="status">{{ formMessage }}</p>
    </form>
  </section>

  <p v-if="error" class="error" role="alert">{{ error }}</p>

  <div class="card table-wrapper" :aria-busy="loading">
    <table class="table">
      <thead>
        <tr>
          <th>Periodo</th>
          <th>Estado</th>
          <th>Nóminas</th>
          <th><span class="visually-hidden">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="p in periods" :key="p.id">
          <td>{{ formatPeriod(p) }}</td>
          <td><span class="badge" :class="`badge-${p.status}`">{{ statusLabels[p.status] }}</span></td>
          <td>{{ p.payslips_count }}</td>
          <td class="actions">
            <template v-if="p.status === 'draft'">
              <button type="button" class="btn btn-small" :disabled="busyId === p.id" @click="act(p, 'generate')">
                {{ p.payslips_count ? 'Recalcular' : 'Generar' }}
              </button>
              <button type="button" class="btn btn-small btn-secondary" :disabled="busyId === p.id || !p.payslips_count" @click="act(p, 'close')">
                Cerrar
              </button>
            </template>
            <RouterLink v-if="p.payslips_count" class="btn btn-small btn-secondary" :to="{ name: 'payslips', query: { period_id: p.id } }">
              Ver nóminas
            </RouterLink>
          </td>
        </tr>
        <tr v-if="!loading && periods.length === 0">
          <td colspan="4" class="empty">No hay periodos.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <AppPagination :meta="meta" :loading="loading" @change="load" />
</template>
