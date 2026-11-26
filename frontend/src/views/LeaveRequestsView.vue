<script setup>
import { ref, watch, onMounted } from 'vue'
import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import AppPagination from '@/components/AppPagination.vue'
import { formatDate } from '@/utils/format'

const auth = useAuthStore()
const isEmployee = auth.user?.role === 'employee' // solo ve sus propias solicitudes: sobra la columna Empleado

const statusLabels = { pending: 'Pendiente', approved: 'Aprobada', rejected: 'Rechazada', cancelled: 'Cancelada' }

// --- Saldo ---
const balance = ref(null)
const balanceError = ref('')

async function loadBalance() {
  try {
    balance.value = (await http.get('/leave-balance')).data.data
  } catch (e) {
    balanceError.value = e.response?.data?.message ?? 'No se ha podido cargar el saldo.'
  }
}

// --- Nueva solicitud ---
const leaveTypes = ref([])
const form = ref({ leave_type_id: '', start_date: '', end_date: '' })
const formErrors = ref({})
const formMessage = ref('')
const saving = ref(false)

async function submit() {
  formErrors.value = {}
  formMessage.value = ''
  saving.value = true

  try {
    const created = (await http.post('/leave-requests', form.value)).data.data
    // Los tipos sin aprobación (p. ej. baja médica) se aprueban solos en el servicio
    formMessage.value = created.status === 'approved' ? 'Ausencia registrada.' : 'Solicitud enviada. Queda pendiente de aprobación.'
    form.value = { leave_type_id: '', start_date: '', end_date: '' }
    refresh()
  } catch (e) {
    if (e.response?.status === 422) {
      formErrors.value = e.response.data.errors // incluye reglas de negocio: solapes, saldo…
    } else {
      formMessage.value = e.response?.data?.message ?? 'No se ha podido conectar con el servidor.'
    }
  } finally {
    saving.value = false
  }
}

// --- Listado ---
const requests = ref([])
const meta = ref(null)
const statusFilter = ref('')
const loading = ref(false)
const error = ref('')

async function load(page = 1) {
  loading.value = true
  error.value = ''
  try {
    const response = await http.get('/leave-requests', {
      params: { status: statusFilter.value || undefined, page },
    })
    requests.value = response.data.data
    meta.value = response.data.meta
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se ha podido cargar el listado.'
  } finally {
    loading.value = false
  }
}

watch(statusFilter, () => load(1))

// Tras crear o revisar cambian el listado y el saldo: se recargan ambos
function refresh() {
  load(meta.value?.current_page ?? 1)
  loadBalance()
}

// --- Acciones: aprobar, rechazar, cancelar ---
async function act(request, action) {
  let body = {}
  if (action === 'reject') {
    const comment = prompt('Motivo del rechazo (obligatorio):')
    if (comment === null) return // ha pulsado Cancelar
    body = { comment }
  }
  if (action === 'cancel' && !confirm('¿Cancelar esta solicitud? Si estaba aprobada, se devuelven los días.')) return

  error.value = ''
  try {
    await http.patch(`/leave-requests/${request.id}/${action}`, body)
    refresh()
  } catch (e) {
    // 422 de negocio ("Solo se pueden revisar solicitudes pendientes") o de validación (comentario vacío)
    const errors = e.response?.data?.errors
    error.value = errors ? Object.values(errors)[0][0] : (e.response?.data?.message ?? 'No se ha podido completar la acción.')
  }
}

onMounted(async () => {
  load()
  loadBalance()
  try {
    leaveTypes.value = (await http.get('/leave-types')).data.data
  } catch {
    formMessage.value = 'No se han podido cargar los tipos de ausencia.'
  }
})
</script>

<template>
  <div class="page-header">
    <h1>Ausencias</h1>
  </div>

  <div class="leave-top">
    <!-- Saldo -->
    <section class="card" aria-labelledby="balance-title">
      <h2 id="balance-title">Mi saldo {{ new Date().getFullYear() }}</h2>
      <p v-if="balanceError" class="error">{{ balanceError }}</p>
      <dl v-else-if="balance" class="stats">
        <div><dt>Disponibles</dt><dd class="stat-main">{{ balance.days_available }}</dd></div>
        <div><dt>Totales</dt><dd>{{ balance.days_total }}</dd></div>
        <div><dt>Disfrutados</dt><dd>{{ balance.days_used }}</dd></div>
        <div><dt>Pendientes</dt><dd>{{ balance.days_pending }}</dd></div>
      </dl>
      <p class="hint">Días laborables (lunes a viernes). Los pendientes ya están reservados.</p>
    </section>

    <!-- Nueva solicitud -->
    <section class="card" aria-labelledby="new-title">
      <h2 id="new-title">Solicitar ausencia</h2>
      <form novalidate @submit.prevent="submit">
        <div class="field">
          <label for="leave_type_id">Tipo</label>
          <select id="leave_type_id" v-model="form.leave_type_id" :aria-invalid="!!formErrors.leave_type_id" aria-describedby="leave_type_id-error">
            <option value="" disabled>Selecciona un tipo</option>
            <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
          </select>
          <p v-if="formErrors.leave_type_id" id="leave_type_id-error" class="field-error">{{ formErrors.leave_type_id[0] }}</p>
        </div>

        <div class="field-row">
          <div class="field">
            <label for="start_date">Desde</label>
            <input id="start_date" v-model="form.start_date" type="date" :aria-invalid="!!formErrors.start_date" aria-describedby="start_date-error">
            <p v-if="formErrors.start_date" id="start_date-error" class="field-error">{{ formErrors.start_date[0] }}</p>
          </div>
          <div class="field">
            <label for="end_date">Hasta</label>
            <input id="end_date" v-model="form.end_date" type="date" :min="form.start_date" :aria-invalid="!!formErrors.end_date" aria-describedby="end_date-error">
            <p v-if="formErrors.end_date" id="end_date-error" class="field-error">{{ formErrors.end_date[0] }}</p>
          </div>
        </div>

        <button type="submit" class="btn" :disabled="saving">{{ saving ? 'Enviando…' : 'Solicitar' }}</button>
        <p v-if="formMessage" class="form-message" role="status">{{ formMessage }}</p>
      </form>
    </section>
  </div>

  <!-- Listado -->
  <div class="page-header">
    <h2>{{ isEmployee ? 'Mis solicitudes' : 'Solicitudes' }}</h2>
    <select v-model="statusFilter" class="search" aria-label="Filtrar por estado">
      <option value="">Todos los estados</option>
      <option v-for="(label, value) in statusLabels" :key="value" :value="value">{{ label }}</option>
    </select>
  </div>

  <p v-if="error" class="error" role="alert">{{ error }}</p>

  <div class="card table-wrapper" :aria-busy="loading">
    <table class="table">
      <thead>
        <tr>
          <th v-if="!isEmployee">Empleado</th>
          <th>Tipo</th>
          <th>Desde</th>
          <th>Hasta</th>
          <th>Días</th>
          <th>Estado</th>
          <th>Comentario</th>
          <th><span class="visually-hidden">Acciones</span></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in requests" :key="r.id">
          <td v-if="!isEmployee">{{ r.employee }}</td>
          <td>{{ r.leave_type }}</td>
          <td>{{ formatDate(r.start_date) }}</td>
          <td>{{ formatDate(r.end_date) }}</td>
          <td>{{ r.days }}</td>
          <td><span class="badge" :class="`badge-${r.status}`">{{ statusLabels[r.status] }}</span></td>
          <td class="cell-comment">{{ r.review_comment }}</td>
          <td class="actions">
            <!-- r.can lo calcula la API con la LeaveRequestPolicy -->
            <template v-if="r.can.review && r.status === 'pending'">
              <button type="button" class="btn btn-small" @click="act(r, 'approve')">Aprobar</button>
              <button type="button" class="btn btn-small btn-danger" @click="act(r, 'reject')">Rechazar</button>
            </template>
            <button
              v-if="r.can.cancel && ['pending', 'approved'].includes(r.status)"
              type="button"
              class="btn btn-small btn-secondary"
              @click="act(r, 'cancel')"
            >
              Cancelar
            </button>
          </td>
        </tr>
        <tr v-if="!loading && requests.length === 0">
          <td :colspan="isEmployee ? 7 : 8" class="empty">No hay solicitudes.</td>
        </tr>
      </tbody>
    </table>
  </div>

  <AppPagination :meta="meta" :loading="loading" @change="load" />
</template>
