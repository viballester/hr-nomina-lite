<script setup>
import { ref, onMounted } from 'vue'
import http from '@/api/http'
import { useAuthStore } from '@/stores/auth'
import { formatMoney, formatPeriod } from '@/utils/format'

const auth = useAuthStore()
const isAdmin = auth.user?.role === 'admin'

const kpis = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    kpis.value = (await http.get('/dashboard')).data.data
  } catch (e) {
    error.value = e.response?.data?.message ?? 'No se han podido cargar los indicadores.'
  }
})
</script>

<template>
  <h1>Hola, {{ auth.user?.name }}</h1>

  <p v-if="error" class="error" role="alert">{{ error }}</p>

  <!-- La API devuelve null en los KPIs que no aplican al rol: esas tarjetas no se pintan -->
  <div v-if="kpis" class="kpis">
    <RouterLink v-if="kpis.active_employees !== null" to="/employees" class="card kpi">
      <span class="kpi-label">Empleados activos</span>
      <span class="kpi-value">{{ kpis.active_employees }}</span>
    </RouterLink>

    <RouterLink to="/leave-requests" class="card kpi">
      <span class="kpi-label">{{ auth.user?.role === 'employee' ? 'Mis solicitudes pendientes' : 'Solicitudes pendientes' }}</span>
      <span class="kpi-value">{{ kpis.pending_requests }}</span>
    </RouterLink>

    <RouterLink to="/leave-requests" class="card kpi">
      <span class="kpi-label">Ausentes hoy</span>
      <span class="kpi-value">{{ kpis.absent_today }}</span>
    </RouterLink>

    <RouterLink v-if="kpis.leave_days_available !== null" to="/leave-requests" class="card kpi">
      <span class="kpi-label">Mis días disponibles</span>
      <span class="kpi-value">{{ kpis.leave_days_available }}</span>
    </RouterLink>

    <RouterLink
      v-if="kpis.last_payroll"
      :to="isAdmin ? '/payroll-periods' : { name: 'payslip-detail', params: { id: String(kpis.last_payroll.id) } }"
      class="card kpi"
    >
      <span class="kpi-label">
        {{ isAdmin ? 'Último periodo' : 'Mi última nómina' }} · {{ formatPeriod(kpis.last_payroll) }}
      </span>
      <span class="kpi-value">{{ formatMoney(kpis.last_payroll.net) }}</span>
      <span v-if="isAdmin" class="hint">
        {{ kpis.last_payroll.payslips_count }} nóminas · {{ kpis.last_payroll.status === 'closed' ? 'Cerrado' : 'Borrador' }} · neto total
      </span>
    </RouterLink>
  </div>
</template>
